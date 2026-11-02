<?php

namespace Modules\CRM\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Events\FollowUpDue;
use Modules\CRM\Events\FollowUpOverdue;
use Modules\CRM\Models\DepartmentSetting;
use Modules\CRM\Models\FollowUp;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Project;
use Modules\Sales\Models\Quotation;

/**
 * "No quotation is forgotten": when a lead quotation version first reaches the customer,
 * follow-ups are planned for the lead's primary assignee at the department's followup_days
 * (default [2, 5]). A newer version being sent replaces the pending ones; winning or losing
 * the lead cancels them (CrmEventSubscriber). Logging a follow-up records the outcome and can
 * plan the next one. crm:process-follow-ups (every 15 minutes) emits FollowUpDue once a
 * follow-up's time comes and FollowUpOverdue once it is still pending the day after
 * (days by the business's own timezone — CrmSettingsService::startOfToday).
 */
class FollowUpService
{
    public function __construct(
        private readonly LeadAssignmentService $assignments,
        private readonly CrmSettingsService $settings,
    ) {}

    /**
     * Plan the follow-ups for a quotation version that was just sent. A re-send of a version
     * that already has follow-ups changes nothing.
     *
     * @return Collection<int, FollowUp> the new follow-ups
     */
    public function scheduleForQuotation(Lead $lead, Quotation $quotation, ?int $byUserId = null): Collection
    {
        $lead->loadMissing('stage');
        if (! $lead->isOpen() || FollowUp::query()->where('quotation_id', $quotation->id)->exists()) {
            return collect();
        }

        $userId = $lead->assigned_to !== null ? (int) $lead->assigned_to : $byUserId;
        $sentAt = Carbon::instance($quotation->sent_at ?? now());

        return DB::transaction(function () use ($lead, $quotation, $userId, $byUserId, $sentAt) {
            // The customer now has the newer version — chase that one instead
            $this->cancelPending($lead);

            return collect($this->followupDays($lead))->map(fn (int $days) => FollowUp::create([
                'business_id'  => $lead->business_id,
                'lead_id'      => $lead->id,
                'quotation_id' => $quotation->id,
                'user_id'      => $userId,
                'status'       => FollowUp::STATUS_PENDING,
                'due_at'       => $sentAt->copy()->addDays($days),
                'notes'        => 'Follow up on quotation ' . $quotation->quote_number . ($quotation->version > 1 ? ' v' . $quotation->version : ''),
                'created_by'   => $byUserId,
            ]));
        });
    }

    /** @return int[] days after sending, from the lead's department settings */
    public function followupDays(Lead $lead): array
    {
        $setting = $lead->department_id === null ? null : DepartmentSetting::query()
            ->where('business_id', $lead->business_id)
            ->where('department_id', $lead->department_id)
            ->first();

        $days = $setting?->followupDays() ?? DepartmentSetting::DEFAULT_FOLLOWUP_DAYS;

        return collect($days)->map(fn ($d) => (int) $d)->filter(fn (int $d) => $d > 0)->unique()->sort()->values()->all();
    }

    /**
     * A follow-up planned by hand.
     *
     * @param  array{due_at: string, user_id?: int|string|null, channel?: ?string, notes?: ?string, quotation_id?: int|string|null}  $data
     */
    public function create(Lead $lead, array $data, ?int $byUserId): FollowUp
    {
        $dueAt = $this->futureDate($data['due_at'] ?? null, 'due_at');

        $userId = filled($data['user_id'] ?? null) ? (int) $data['user_id'] : ($lead->assigned_to ?? $byUserId);
        if ($userId !== null && ! $this->isAssignable($lead, (int) $userId)) {
            throw ValidationException::withMessages(['user_id' => 'Pick someone who works this lead\'s department.']);
        }

        $quotationId = filled($data['quotation_id'] ?? null) ? (int) $data['quotation_id'] : null;
        if ($quotationId !== null && ! Quotation::query()->where('lead_id', $lead->id)->whereKey($quotationId)->exists()) {
            throw ValidationException::withMessages(['quotation_id' => 'That quotation is not on this lead.']);
        }

        return FollowUp::create([
            'business_id'  => $lead->business_id,
            'lead_id'      => $lead->id,
            'quotation_id' => $quotationId,
            'user_id'      => $userId,
            'status'       => FollowUp::STATUS_PENDING,
            'due_at'       => $dueAt,
            'channel'      => $this->channel($data['channel'] ?? null),
            'notes'        => $this->text($data['notes'] ?? null),
            'created_by'   => $byUserId,
        ]);
    }

    /**
     * Record how a follow-up went; a next date plans the next follow-up (same person and quotation).
     *
     * @param  array{outcome: string, channel?: ?string, notes?: ?string, next_follow_up_at?: ?string}  $data
     * @return array{0: FollowUp, 1: ?FollowUp} the logged follow-up and the next one
     */
    public function log(FollowUp $followUp, array $data, ?int $byUserId): array
    {
        if (! $followUp->isPending()) {
            throw ValidationException::withMessages(['follow_up' => 'This follow-up is already ' . $followUp->status . '.']);
        }

        $outcome = (string) ($data['outcome'] ?? '');
        if (! array_key_exists($outcome, FollowUp::OUTCOMES)) {
            throw ValidationException::withMessages(['outcome' => 'Pick what came of the follow-up.']);
        }
        $next = filled($data['next_follow_up_at'] ?? null) ? $this->futureDate($data['next_follow_up_at'], 'next_follow_up_at') : null;

        [$done, $nextFollowUp] = DB::transaction(function () use ($followUp, $data, $outcome, $next, $byUserId) {
            $followUp->update([
                'status'            => FollowUp::STATUS_DONE,
                'done_at'           => now(),
                'outcome'           => $outcome,
                'channel'           => $this->channel($data['channel'] ?? null) ?? $followUp->channel,
                'notes'             => $this->text($data['notes'] ?? null) ?? $followUp->notes,
                'next_follow_up_at' => $next,
            ]);

            $nextFollowUp = $next === null ? null : FollowUp::create([
                'business_id'  => $followUp->business_id,
                'lead_id'      => $followUp->lead_id,
                'quotation_id' => $followUp->quotation_id,
                'user_id'      => $followUp->user_id ?? $byUserId,
                'status'       => FollowUp::STATUS_PENDING,
                'due_at'       => $next,
                'channel'      => $followUp->channel,
                'notes'        => 'Next after: ' . FollowUp::outcomeLabel($outcome),
                'created_by'   => $byUserId,
            ]);

            return [$followUp, $nextFollowUp];
        });

        $followUp->lead?->recordFirstResponse();

        return [$done->fresh(), $nextFollowUp];
    }

    /** Cancel the lead's pending follow-ups (won, lost, a newer quotation sent). @return int how many */
    public function cancelPending(Lead $lead): int
    {
        return FollowUp::query()
            ->where('lead_id', $lead->id)
            ->pending()
            ->update(['status' => FollowUp::STATUS_CANCELLED, 'cancelled_at' => now(), 'updated_at' => now()]);
    }

    public function cancel(FollowUp $followUp): FollowUp
    {
        if (! $followUp->isPending()) {
            throw ValidationException::withMessages(['follow_up' => 'Only a pending follow-up can be cancelled.']);
        }
        $followUp->update(['status' => FollowUp::STATUS_CANCELLED, 'cancelled_at' => now()]);

        return $followUp;
    }

    /** @return Collection<int, FollowUp> pending first (soonest first), then the rest newest first */
    public function forLead(Lead $lead): Collection
    {
        $all = FollowUp::query()->where('lead_id', $lead->id)->with(['user:id,name', 'quotation:id,quote_number,version'])->get();

        return $all->filter->isPending()->sortBy('due_at')->values()
            ->concat($all->reject->isPending()->sortByDesc(fn (FollowUp $f) => $f->done_at ?? $f->cancelled_at ?? $f->updated_at)->values());
    }

    /** One follow-up of this business, or a 404. */
    public function followUpForBusiness(Business $business, int $id): FollowUp
    {
        return FollowUp::query()->where('business_id', $business->id)->with('lead')->findOrFail($id);
    }

    /**
     * "My follow-ups" (scope mine) or, for heads and admins, "Team" — every follow-up on the
     * leads of the departments / divisions they head (all of the business for admins).
     *
     * @param  string  $status  pending | overdue | today | done | all
     * @return Collection<int, FollowUp>
     */
    public function listFor(Business $business, CrmAccess $access, string $scope = 'mine', string $status = 'pending', int $limit = 300): Collection
    {
        $query = FollowUp::query()
            ->where('crm_follow_ups.business_id', $business->id)
            ->whereIn('crm_follow_ups.lead_id', Lead::query()->where('crm_leads.business_id', $business->id)->visibleWith($access)->select('crm_leads.id'))
            ->with(['user:id,name', 'lead.stage', 'lead.project:id,name', 'quotation:id,quote_number,version,total']);

        if ($scope === 'team') {
            if (! $access->worksAllLeads()) {
                $query->whereIn('crm_follow_ups.lead_id', $this->headedLeadIds($business, $access));
            }
        } else {
            $query->where('crm_follow_ups.user_id', $access->userId ?? 0);
        }

        $startOfToday = $this->settings->startOfToday($business);

        match ($status) {
            'overdue' => $query->overdue($business->id),
            'today'   => $query->pending()->where('crm_follow_ups.due_at', '>=', $startOfToday)->where('crm_follow_ups.due_at', '<', $startOfToday->copy()->addDay()),
            'done'    => $query->where('crm_follow_ups.status', FollowUp::STATUS_DONE),
            'all'     => null,
            default   => $query->pending(),
        };

        return $status === 'done' || $status === 'all'
            ? $query->orderByDesc('crm_follow_ups.due_at')->limit($limit)->get()
            : $query->orderBy('crm_follow_ups.due_at')->limit($limit)->get();
    }

    /** Sees the team list: admins, "All Departments" and heads. */
    public function canSeeTeam(CrmAccess $access): bool
    {
        return $access->worksAllLeads() || $access->isHead();
    }

    /**
     * Next pending follow-up and overdue count per lead, for the board cards — one query.
     *
     * @param  int[]  $leadIds
     * @return array<int, array{next_due_at: ?string, pending: int, overdue: int}>
     */
    public function summaryForLeads(Business $business, array $leadIds): array
    {
        if ($leadIds === []) {
            return [];
        }

        $startOfToday = $this->settings->startOfToday($business);

        return FollowUp::query()
            ->whereIn('lead_id', $leadIds)
            ->pending()
            ->selectRaw('lead_id, min(due_at) as next_due_at, count(*) as pending_count, sum(case when due_at < ? then 1 else 0 end) as overdue_count', [$startOfToday])
            ->groupBy('lead_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->lead_id => [
                'next_due_at' => $row->next_due_at ? Carbon::parse($row->next_due_at)->toIso8601String() : null,
                'pending'     => (int) $row->pending_count,
                'overdue'     => (int) $row->overdue_count,
            ]])
            ->all();
    }

    /**
     * The scheduler's pass: overdue first (so a follow-up found already overdue does not also get
     * a "due" reminder), then due. Each follow-up fires each event at most once.
     *
     * @return array{due: int, overdue: int}
     */
    public function process(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $counts = ['due' => 0, 'overdue' => 0];

        // Each business's day starts at its own midnight, so overdue is decided per business
        $businessIds = FollowUp::query()->pending()->whereNull('overdue_at')->where('due_at', '<', $now)->distinct()->pluck('business_id');
        foreach ($businessIds as $businessId) {
            FollowUp::query()
                ->pending()
                ->where('business_id', $businessId)
                ->whereNull('overdue_at')
                ->where('due_at', '<', $this->settings->startOfToday((int) $businessId, $now))
                ->chunkById(200, function (Collection $rows) use ($now, &$counts) {
                    foreach ($rows as $followUp) {
                        $followUp->update(['overdue_at' => $now, 'due_notified_at' => $followUp->due_notified_at ?? $now]);
                        FollowUpOverdue::dispatch($followUp);
                        $counts['overdue']++;
                    }
                });
        }

        FollowUp::query()
            ->pending()
            ->whereNull('due_notified_at')
            ->where('due_at', '<=', $now)
            ->chunkById(200, function (Collection $rows) use ($now, &$counts) {
                foreach ($rows as $followUp) {
                    $followUp->update(['due_notified_at' => $now]);
                    FollowUpDue::dispatch($followUp);
                    $counts['due']++;
                }
            });

        return $counts;
    }

    /** @return array<string, mixed> */
    public function present(FollowUp $f, bool $canWork = false): array
    {
        $lead = $f->relationLoaded('lead') ? $f->lead : null;

        return [
            'id'                => (int) $f->id,
            'lead_id'           => (int) $f->lead_id,
            'status'            => $f->status,
            'due_at'            => $f->due_at?->toIso8601String(),
            'done_at'           => $f->done_at?->toIso8601String(),
            'cancelled_at'      => $f->cancelled_at?->toIso8601String(),
            'is_overdue'        => $f->isOverdue(),
            'is_due_today'      => $f->isDueToday(),
            'channel'           => $f->channel,
            'channel_label'     => FollowUp::channelLabel($f->channel),
            'outcome'           => $f->outcome,
            'outcome_label'     => FollowUp::outcomeLabel($f->outcome),
            'notes'             => $f->notes,
            'next_follow_up_at' => $f->next_follow_up_at?->toIso8601String(),
            'user'              => $f->user ? ['id' => (int) $f->user->id, 'name' => $f->user->name] : null,
            'quotation'         => $f->quotation ? ['id' => (int) $f->quotation->id, 'quote_number' => $f->quotation->quote_number, 'version' => (int) $f->quotation->version] : null,
            'lead'              => $lead ? [
                'id'           => (int) $lead->id,
                'name'         => $lead->name,
                'company'      => $lead->company,
                'phone'        => $lead->phone,
                'project_id'   => (int) $lead->project_id,
                'project_name' => $lead->project?->name,
                'stage_name'   => $lead->stageLabel(),
                'stage_color'  => $lead->stageColor(),
            ] : null,
            'can_log'           => $canWork && $f->isPending(),
        ];
    }

    /** @return list<array{key: string, label: string}> */
    public static function options(array $map): array
    {
        return collect($map)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all();
    }

    /** Leads in the departments / divisions this user heads. */
    private function headedLeadIds(Business $business, CrmAccess $access): \Illuminate\Database\Eloquent\Builder
    {
        $projectIds = $access->headDepartmentIds === [] ? [] : Project::query()
            ->where('business_id', $business->id)
            ->whereIn('department_id', $access->headDepartmentIds)
            ->pluck('id')
            ->all();
        $divisionIds = array_keys($access->headDivisions);

        return Lead::query()
            ->where('crm_leads.business_id', $business->id)
            ->where(fn ($q) => $q->whereIn('crm_leads.project_id', $projectIds ?: [0])
                ->orWhereIn('crm_leads.division_id', $divisionIds ?: [0]))
            ->select('crm_leads.id');
    }

    private function isAssignable(Lead $lead, int $userId): bool
    {
        return in_array($userId, $this->assignments->assigneeIds($lead), true)
            || $this->assignments->assignableUsers($lead)->contains('id', $userId);
    }

    private function futureDate(mixed $value, string $field): Carbon
    {
        try {
            $date = Carbon::parse((string) $value);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Enter a valid date and time.']);
        }
        if (blank($value) || $date->lt(now()->subMinute())) {
            throw ValidationException::withMessages([$field => 'Pick a date and time from now on.']);
        }

        return $date;
    }

    private function channel(mixed $channel): ?string
    {
        if (blank($channel)) {
            return null;
        }
        if (! array_key_exists($channel, FollowUp::CHANNELS)) {
            throw ValidationException::withMessages(['channel' => 'Pick how the customer was contacted.']);
        }

        return $channel;
    }

    private function text(mixed $text): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : mb_substr($text, 0, 2000);
    }
}
