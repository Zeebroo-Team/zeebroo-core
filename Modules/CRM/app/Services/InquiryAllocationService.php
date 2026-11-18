<?php

namespace Modules\CRM\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\InquiryAllocation;
use Modules\CRM\Models\InquiryLimitException;
use Modules\CRM\Models\Lead;

/**
 * Daily inquiry allocation: how many leads a user may be handed per day (CRM settings, default
 * 10), plus any extra an administrator grants that user for that day, and the record of every
 * lead handed to every user (crm_inquiry_allocations). Days follow the business's calendar.
 *
 * LeadAssignmentService calls assertWithinLimit() before adding people to a lead and record()
 * / release() inside its transaction, so every assignment path — lead window, board, edit
 * form, desktop and web — is counted and limited the same way.
 */
class InquiryAllocationService
{
    public function __construct(private readonly CrmSettingsService $settings) {}

    /** Today's date (Y-m-d) on the business's calendar. */
    public function today(Business|int $business): string
    {
        return now()->setTimezone($this->settings->timezone($business))->toDateString();
    }

    public function limit(Business|int $business): int
    {
        return $this->settings->dailyInquiryLimit($business);
    }

    /** Extra inquiries granted to the user for that day. */
    public function extraAllowed(Business|int $business, int $userId, string $date): int
    {
        return (int) InquiryLimitException::query()
            ->where('business_id', $this->businessId($business))
            ->where('user_id', $userId)
            ->whereDate('exception_date', $date)
            ->value('extra_allowed');
    }

    /**
     * Active allocations per user on the day.
     *
     * @param  int[]  $userIds
     * @return array<int, int> user id => count
     */
    public function usedByUsers(Business|int $business, array $userIds, string $date): array
    {
        if ($userIds === []) {
            return [];
        }

        $counts = InquiryAllocation::query()
            ->where('business_id', $this->businessId($business))
            ->whereIn('user_id', $userIds)
            ->whereDate('allocation_date', $date)
            ->active()
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as c')
            ->pluck('c', 'user_id');

        $out = [];
        foreach ($userIds as $id) {
            $out[(int) $id] = (int) ($counts[$id] ?? 0);
        }

        return $out;
    }

    /**
     * Each user's load for the day: used, the limit, extra granted and what is left.
     *
     * @param  int[]  $userIds
     * @return array<int, array{used:int, limit:int, extra:int, allowed:int, remaining:int}>
     */
    public function capacity(Business|int $business, array $userIds, ?string $date = null): array
    {
        $date  ??= $this->today($business);
        $limit = $this->limit($business);
        $used  = $this->usedByUsers($business, $userIds, $date);
        $extra = InquiryLimitException::query()
            ->where('business_id', $this->businessId($business))
            ->whereIn('user_id', $userIds ?: [0])
            ->whereDate('exception_date', $date)
            ->pluck('extra_allowed', 'user_id');

        $out = [];
        foreach ($userIds as $id) {
            $id      = (int) $id;
            $allowed = $limit + (int) ($extra[$id] ?? 0);
            $out[$id] = [
                'used'      => $used[$id] ?? 0,
                'limit'     => $limit,
                'extra'     => (int) ($extra[$id] ?? 0),
                'allowed'   => $allowed,
                'remaining' => max(0, $allowed - ($used[$id] ?? 0)),
            ];
        }

        return $out;
    }

    /**
     * Throws (422, key daily_limit) when handing this lead to any of these users would take them
     * past today's allowance. A user who already holds today's slot for this lead is not counted
     * again.
     *
     * @param  int[]  $userIds  the people being added to the lead
     */
    public function assertWithinLimit(Lead $lead, array $userIds): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            return;
        }

        $date    = $this->today($lead->business_id);
        $holding = InquiryAllocation::query()
            ->where('lead_id', $lead->id)
            ->whereIn('user_id', $userIds)
            ->whereDate('allocation_date', $date)
            ->active()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $userIds = array_values(array_diff($userIds, $holding));
        if ($userIds === []) {
            return;
        }

        $full = array_filter(
            $this->capacity($lead->business_id, $userIds, $date),
            fn (array $c) => $c['remaining'] < 1,
        );
        if ($full === []) {
            return;
        }

        $names = User::whereIn('id', array_keys($full))->pluck('name', 'id');
        $parts = [];
        foreach ($full as $id => $c) {
            $parts[] = ($names[$id] ?? "User #{$id}") . " has already been given {$c['used']} of {$c['allowed']} inquiries today";
        }

        throw ValidationException::withMessages([
            'daily_limit' => implode('; ', $parts) . '. An administrator can grant an exception for today.',
        ]);
    }

    /**
     * Records the lead as handed to these users today (or takes back a slot released earlier
     * today). Call inside the assignment's transaction.
     *
     * @param  int[]  $userIds  the people added to the lead
     */
    public function record(Lead $lead, array $userIds, ?int $byUserId): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            return;
        }

        $lead->loadMissing('project');
        $date         = $this->today($lead->business_id);
        $limit        = $this->limit($lead->business_id);
        $used         = $this->usedByUsers($lead->business_id, $userIds, $date);
        $departmentId = $lead->department_id ?? $lead->project?->department_id;

        foreach ($userIds as $userId) {
            $row = InquiryAllocation::query()
                ->where('lead_id', $lead->id)
                ->where('user_id', $userId)
                ->whereDate('allocation_date', $date)
                ->first();

            if ($row !== null && $row->released_at === null) {
                continue;
            }

            $values = [
                'business_id'   => $lead->business_id,
                'department_id' => $departmentId,
                'assigned_by'   => $byUserId,
                'over_limit'    => ($used[$userId] ?? 0) >= $limit,
                'released_at'   => null,
            ];

            $row === null
                ? InquiryAllocation::create(['lead_id' => $lead->id, 'user_id' => $userId, 'allocation_date' => $date] + $values)
                : $row->update($values);
        }
    }

    /**
     * A user taken off the lead the same day they were given it gets the slot back. Earlier days
     * stay as they were — that allocation happened.
     *
     * @param  int[]  $userIds  the people removed from the lead
     */
    public function release(Lead $lead, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        InquiryAllocation::query()
            ->where('lead_id', $lead->id)
            ->whereIn('user_id', array_map('intval', $userIds))
            ->whereDate('allocation_date', $this->today($lead->business_id))
            ->active()
            ->update(['released_at' => now()]);
    }

    // ── Exceptions ───────────────────────────────────────────────────────

    /** Allow the user `extra` inquiries on top of the limit on that day (replaces an earlier grant). */
    public function grantException(Business $business, int $userId, string $date, int $extra, ?string $reason, ?int $byUserId): InquiryLimitException
    {
        if ($extra < 1 || $extra > 200) {
            throw ValidationException::withMessages(['extra_allowed' => 'Allow between 1 and 200 extra inquiries.']);
        }

        return DB::transaction(function () use ($business, $userId, $date, $extra, $reason, $byUserId) {
            $row = InquiryLimitException::query()
                ->where('business_id', $business->id)
                ->where('user_id', $userId)
                ->whereDate('exception_date', $date)
                ->first();

            $values = [
                'extra_allowed' => $extra,
                'reason'        => filled($reason) ? mb_substr(trim($reason), 0, 500) : null,
                'granted_by'    => $byUserId,
            ];

            if ($row === null) {
                return InquiryLimitException::create(['business_id' => $business->id, 'user_id' => $userId, 'exception_date' => $date] + $values);
            }
            $row->update($values);

            return $row;
        });
    }

    public function exceptionForBusiness(Business $business, int $id): InquiryLimitException
    {
        return InquiryLimitException::query()->where('business_id', $business->id)->findOrFail($id);
    }

    /** @return Collection<int, InquiryLimitException> */
    public function exceptionsOn(Business $business, string $date): Collection
    {
        return InquiryLimitException::query()
            ->where('business_id', $business->id)
            ->whereDate('exception_date', $date)
            ->with(['user:id,name', 'grantedBy:id,name'])
            ->orderBy('id')
            ->get();
    }

    public function presentException(InquiryLimitException $e): array
    {
        return [
            'id'             => $e->id,
            'user'           => ['id' => (int) $e->user_id, 'name' => $e->user?->name ?? 'Deleted user'],
            'exception_date' => $e->exception_date?->toDateString(),
            'extra_allowed'  => (int) $e->extra_allowed,
            'reason'         => $e->reason,
            'granted_by'     => $e->granted_by ? ['id' => (int) $e->granted_by, 'name' => $e->grantedBy?->name ?? 'Deleted user'] : null,
            'created_at'     => $e->created_at?->toIso8601String(),
        ];
    }

    // ── Reading the record ───────────────────────────────────────────────

    /**
     * Allocations between two days (inclusive), newest first.
     *
     * @param  int[]|null  $userIds        null = everyone
     * @param  int[]|null  $departmentIds  null = every department
     * @return Collection<int, InquiryAllocation>
     */
    public function allocations(Business $business, string $from, string $to, ?array $userIds = null, ?array $departmentIds = null, bool $includeReleased = true, int $limit = 1000): Collection
    {
        return InquiryAllocation::query()
            ->where('business_id', $business->id)
            ->whereDate('allocation_date', '>=', $from)
            ->whereDate('allocation_date', '<=', $to)
            ->when($userIds !== null, fn ($q) => $q->whereIn('user_id', $userIds ?: [0]))
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('department_id', $departmentIds ?: [0]))
            ->when(! $includeReleased, fn ($q) => $q->whereNull('released_at'))
            ->with(['user:id,name', 'assignedBy:id,name', 'department:id,name', 'lead:id,project_id,name,company,stage_id', 'lead.stage:id,name,color,system_key,is_won,is_lost'])
            ->orderByDesc('allocation_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function presentAllocation(InquiryAllocation $a): array
    {
        return [
            'id'              => $a->id,
            'allocation_date' => $a->allocation_date?->toDateString(),
            'user'            => ['id' => (int) $a->user_id, 'name' => $a->user?->name ?? 'Deleted user'],
            'assigned_by'     => $a->assigned_by ? ['id' => (int) $a->assigned_by, 'name' => $a->assignedBy?->name ?? 'Deleted user'] : null,
            'department'      => $a->department_id ? ['id' => (int) $a->department_id, 'name' => $a->department?->name] : null,
            'lead'            => $a->lead ? [
                'id'         => (int) $a->lead->id,
                'project_id' => (int) $a->lead->project_id,
                'name'       => $a->lead->name,
                'company'    => $a->lead->company,
                'stage'      => $a->lead->stage?->name,
                'color'      => $a->lead->stage?->color,
            ] : null,
            'over_limit'      => (bool) $a->over_limit,
            'released_at'     => $a->released_at?->toIso8601String(),
            'created_at'      => $a->created_at?->toIso8601String(),
        ];
    }

    private function businessId(Business|int $business): int
    {
        return $business instanceof Business ? (int) $business->id : $business;
    }
}
