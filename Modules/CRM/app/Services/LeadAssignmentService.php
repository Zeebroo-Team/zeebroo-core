<?php

namespace Modules\CRM\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadAssignee;
use Modules\CRM\Models\LeadAssignmentLog;
use Modules\CRM\Models\LeadAuditLog;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\Project;

/**
 * Who works a lead: one or more people from its department, one of them primary. The only
 * writer of crm_lead_assignees; keeps crm_leads.assigned_to equal to the primary assignee and
 * logs every change to crm_lead_assignment_logs (assignees before and after, primary first).
 *
 * Whether the caller may assign at all is LeadPolicy::canAssign (department head / admin) and
 * is checked by the controllers; this service only lets people of the lead's department in.
 */
class LeadAssignmentService
{
    public function __construct(
        private readonly CrmOrgService $org,
        private readonly LeadService $leads,
        private readonly LeadAuditService $audit,
        private readonly LeadStageService $stages,
    ) {}

    /** Users the lead may be assigned to: its department's staff with a login plus the head. */
    public function assignableUsers(Lead $lead): Collection
    {
        $lead->loadMissing(['project', 'business']);

        return $this->org->assignableUsers($lead->business, $lead->project?->department_id);
    }

    /** @return Collection<int, LeadAssignee> primary first, with their users */
    public function assignees(Lead $lead): Collection
    {
        return $lead->assignees()->with('user:id,name,email')->get();
    }

    /** Assignee user ids, primary first. @return int[] */
    public function assigneeIds(Lead $lead): array
    {
        return $lead->assignees()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Throws unless every user can be assigned to this lead. */
    public function assertAssignable(Lead $lead, array $userIds): void
    {
        $lead->loadMissing('project');

        $this->assertAssignableIn($lead->project, $userIds);
    }

    /** Throws unless every user can be assigned to the leads of this pipeline. */
    public function assertAssignableIn(Project $project, array $userIds): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            return;
        }

        $project->loadMissing('business');
        $allowed  = $this->org->assignableUsers($project->business, $project->department_id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $outsider = array_values(array_diff($userIds, $allowed));
        if ($outsider !== []) {
            $name = User::whereKey($outsider[0])->value('name') ?? 'This person';
            throw ValidationException::withMessages([
                'user_ids' => $name . ' is not a member of the lead\'s department.',
            ]);
        }
    }

    // ── Changes ──────────────────────────────────────────────────────────

    /** Replace the assignees with exactly these users; the primary defaults to the first one. */
    public function sync(Lead $lead, array $userIds, ?int $primaryUserId, ?int $byUserId, ?string $note = null): Lead
    {
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        if ($primaryUserId !== null) {
            if (! in_array($primaryUserId, $ids, true)) {
                throw ValidationException::withMessages(['primary_user_id' => 'The primary assignee must be one of the assignees.']);
            }
            $ids = array_values(array_unique([$primaryUserId, ...$ids]));
        }

        return $this->apply($lead, $ids, $byUserId, $note);
    }

    /** Add people to the lead, keeping who is already on it (and the primary). */
    public function add(Lead $lead, array $userIds, ?int $byUserId, ?string $note = null): Lead
    {
        return $this->apply($lead, [...$this->assigneeIds($lead), ...array_map('intval', $userIds)], $byUserId, $note);
    }

    /** Take one person off the lead; the next one becomes primary if they were. */
    public function remove(Lead $lead, int $userId, ?int $byUserId, ?string $note = null): Lead
    {
        $current = $this->assigneeIds($lead);
        if (! in_array($userId, $current, true)) {
            throw ValidationException::withMessages(['user_id' => 'This person is not assigned to the lead.']);
        }

        return $this->apply($lead, array_values(array_diff($current, [$userId])), $byUserId, $note);
    }

    public function setPrimary(Lead $lead, int $userId, ?int $byUserId): Lead
    {
        $current = $this->assigneeIds($lead);
        if (! in_array($userId, $current, true)) {
            throw ValidationException::withMessages(['user_id' => 'Only an assignee can be made primary.']);
        }

        return $this->apply($lead, [$userId, ...array_diff($current, [$userId])], $byUserId, null);
    }

    /**
     * The single "Assigned to" field of the older edit forms: null takes everyone off the lead,
     * a user becomes the primary assignee (added if needed) and the others stay on it.
     */
    public function setPrimaryAssignee(Lead $lead, ?int $userId, ?int $byUserId): Lead
    {
        if ($userId === null) {
            return $this->apply($lead, [], $byUserId, null);
        }

        return $this->apply($lead, [$userId, ...array_diff($this->assigneeIds($lead), [$userId])], $byUserId, null);
    }

    /**
     * Take everyone off the lead without an assignment log entry — for moves that log
     * themselves (forwarding the lead to another department).
     */
    public function clear(Lead $lead): void
    {
        DB::transaction(function () use ($lead) {
            $lead->assignees()->get()->each->delete();
            if ($lead->assigned_to !== null) {
                $lead->update(['assigned_to' => null]);
            }
        });
    }

    /**
     * @param  int[]  $ids  the assignees after the change, primary first
     */
    private function apply(Lead $lead, array $ids, ?int $byUserId, ?string $note): Lead
    {
        $ids    = array_values(array_unique(array_map('intval', $ids)));
        $before = $this->assigneeIds($lead);
        if ($ids === $before) {
            return $lead;
        }

        // People already on the lead may stay even if they left the department since
        $this->assertAssignable($lead, array_diff($ids, $before));

        $note   = filled($note) ? mb_substr(trim($note), 0, 2000) : null;
        $action = $this->action($before, $ids);

        DB::transaction(function () use ($lead, $ids, $before, $byUserId, $note, $action) {
            $lead->assignees()->whereNotIn('user_id', $ids ?: [0])->get()->each->delete();

            $existing = $lead->assignees()->get()->keyBy('user_id');
            foreach ($ids as $position => $userId) {
                $row = $existing->get($userId);
                if ($row === null) {
                    LeadAssignee::create([
                        'business_id' => $lead->business_id,
                        'lead_id'     => $lead->id,
                        'user_id'     => $userId,
                        'is_primary'  => $position === 0,
                        'assigned_by' => $byUserId,
                        'assigned_at' => now(),
                    ]);
                } elseif ($row->is_primary !== ($position === 0)) {
                    $row->update(['is_primary' => $position === 0]);
                }
            }

            $primary = $ids[0] ?? null;
            if ($primary !== ($lead->assigned_to === null ? null : (int) $lead->assigned_to)) {
                $lead->update(['assigned_to' => $primary]);
            }

            LeadAssignmentLog::create([
                'business_id'        => $lead->business_id,
                'lead_id'            => $lead->id,
                'action'             => $action,
                'from_department_id' => $lead->department_id,
                'to_department_id'   => $lead->department_id,
                'from_division_id'   => $lead->division_id,
                'to_division_id'     => $lead->division_id,
                'user_ids'           => $ids,
                'from_user_ids'      => $before,
                'by_user_id'         => $byUserId,
                'note'               => $note,
            ]);
        });

        $names = User::whereIn('id', [...$ids, ...$before])->pluck('name', 'id');
        $this->audit->recordEvent($lead, LeadAuditLog::EVENT_ASSIGNED, $byUserId, array_filter([
            'assignees' => collect($ids)->map(fn ($id) => $names->get($id) ?? "User #{$id}")->all(),
            'previous'  => collect($before)->map(fn ($id) => $names->get($id) ?? "User #{$id}")->all(),
            'note'      => $note,
        ], fn ($v) => $v !== null));

        // Someone is now working a new lead
        $lead->loadMissing(['stage', 'project']);
        if ($ids !== [] && $lead->statusKey() === LeadStage::STATUS_NEW
            && $this->stages->stageForStatus($lead->project, LeadStage::STATUS_ASSIGNED) !== null) {
            $this->leads->moveToStatus($lead, LeadStage::STATUS_ASSIGNED, $byUserId);
        }

        return $lead->fresh();
    }

    /** assigned: only people added; unassigned: only people removed; reassigned: anything else. */
    private function action(array $before, array $after): string
    {
        $added   = array_diff($after, $before);
        $removed = array_diff($before, $after);
        $samePrimary = ($before[0] ?? null) === ($after[0] ?? null) || $before === [] || $after === [];

        return match (true) {
            $removed === [] && $samePrimary => LeadAssignmentLog::ACTION_ASSIGNED,
            $added === [] && $samePrimary   => LeadAssignmentLog::ACTION_UNASSIGNED,
            default                         => LeadAssignmentLog::ACTION_REASSIGNED,
        };
    }

    // ── Reading ──────────────────────────────────────────────────────────

    /** @return list<array{id:int, name:string, is_primary:bool, assigned_at:?string, assigned_by:?string}> */
    public function present(Collection $assignees): array
    {
        return $assignees->map(fn (LeadAssignee $a) => [
            'id'          => (int) $a->user_id,
            'name'        => $a->user?->name ?? 'Deleted user',
            'is_primary'  => (bool) $a->is_primary,
            'assigned_at' => $a->assigned_at?->toIso8601String(),
        ])->values()->all();
    }

    /** @return Collection<int, LeadAssignmentLog> newest first */
    public function history(Lead $lead): Collection
    {
        return $lead->assignmentLogs()->with(['byUser:id,name', 'fromDepartment:id,name', 'toDepartment:id,name', 'fromDivision:id,name', 'toDivision:id,name'])->get();
    }

    /**
     * One history entry as text: what changed and who did it.
     *
     * @param  Collection<int, string>|null  $names  user id => name, for many entries at once
     * @return array{id:int, action:string, title:string, added:string[], removed:string[], assignees:string[], primary:?string, note:?string, by:?array, created_at:?string}
     */
    public function presentLog(LeadAssignmentLog $log, ?Collection $names = null): array
    {
        $after  = array_map('intval', $log->user_ids ?? []);
        $before = array_map('intval', $log->from_user_ids ?? []);
        $names ??= User::whereIn('id', [...$after, ...$before])->pluck('name', 'id');
        $name   = fn (int $id) => $names->get($id) ?? "User #{$id}";

        $added   = array_map($name, array_values(array_diff($after, $before)));
        $removed = array_map($name, array_values(array_diff($before, $after)));
        $primary = isset($after[0]) && ($before[0] ?? null) !== $after[0] && $before !== [] ? $name($after[0]) : null;

        $title = match ($log->action) {
            LeadAssignmentLog::ACTION_FORWARDED => 'Forwarded'
                . ($log->fromDepartment ? ' from ' . $log->fromDepartment->name : '')
                . ' to ' . ($log->toDepartment?->name ?? 'another department')
                . ($log->toDivision ? ' / ' . $log->toDivision->name : ''),
            default => $this->assignmentTitle($added, $removed, $primary),
        };

        return [
            'id'         => $log->id,
            'action'     => $log->action,
            'title'      => $title,
            'added'      => $added,
            'removed'    => $removed,
            'assignees'  => array_map($name, $after),
            'primary'    => $primary,
            'note'       => $log->note,
            'by'         => $log->by_user_id ? ['id' => $log->by_user_id, 'name' => $log->byUser?->name ?? 'Deleted user'] : null,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    /** @return list<array> newest first */
    public function presentHistory(Collection $logs): array
    {
        $ids   = $logs->flatMap(fn (LeadAssignmentLog $l) => [...($l->user_ids ?? []), ...($l->from_user_ids ?? [])])->unique()->values();
        $names = User::whereIn('id', $ids)->pluck('name', 'id');

        return $logs->map(fn (LeadAssignmentLog $l) => $this->presentLog($l, $names))->values()->all();
    }

    private function assignmentTitle(array $added, array $removed, ?string $primary): string
    {
        $parts = [];
        if ($added !== [])   $parts[] = 'Assigned ' . implode(', ', $added);
        if ($removed !== []) $parts[] = ($added !== [] ? 'took off ' : 'Took off ') . implode(', ', $removed);
        if ($primary !== null && ! in_array($primary, $added, true)) {
            $parts[] = ($parts === [] ? 'Made ' : 'made ') . $primary . ' the primary assignee';
        } elseif ($primary !== null && $parts !== []) {
            $parts[] = $primary . ' is primary';
        }

        return $parts === [] ? 'Assignees changed' : implode('; ', $parts);
    }
}
