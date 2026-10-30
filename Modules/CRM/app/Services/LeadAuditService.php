<?php

namespace Modules\CRM\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadAuditLog;
use Modules\CRM\Models\LeadCustomField;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\Project;

/**
 * Writes and reads the lead audit trail (crm_lead_audit_logs). LeadService calls in here
 * from every mutation, so no entry point — desktop, web, public form, automation — can
 * change a lead without leaving a record of who, when, from where and what changed.
 */
class LeadAuditService
{
    /** Core lead columns tracked field-by-field on edit, with their display labels. */
    private const TRACKED_FIELDS = [
        'name'                  => 'Name',
        'company'               => 'Company',
        'email'                 => 'Email',
        'phone'                 => 'Phone',
        'source'                => 'Source',
        'estimated_value'       => 'Estimated Value',
        'expected_close_date'   => 'Expected Close',
        'notes'                 => 'Notes',
        'assigned_to'           => 'Assigned To',
        'lost_reason'           => 'Lost Reason',
        'score'                 => 'Score',
        'division_id'           => 'Division',
        'product_interest_note' => 'Product Interest',
    ];

    /**
     * Normalised copy of everything diffable on a lead (core fields + custom field values),
     * taken before an edit so the after-state can be compared against it.
     *
     * @return array<string, string|null>
     */
    public function snapshot(Lead $lead): array
    {
        $values = [];
        foreach (array_keys(self::TRACKED_FIELDS) as $field) {
            $values[$field] = $this->normalise($lead->getAttribute($field));
        }

        $custom = $lead->customFieldValues()->pluck('value', 'custom_field_id');
        foreach ($custom as $fieldId => $value) {
            $values["custom:{$fieldId}"] = $this->normalise($value);
        }

        return $values;
    }

    public function recordCreated(Lead $lead, ?int $userId): void
    {
        $lead->loadMissing('stage');

        $this->write($lead, LeadAuditLog::EVENT_CREATED, $userId, [
            'to_stage_id'   => $lead->stage_id,
            'to_stage_name' => $lead->stage?->name,
            'meta'          => array_filter(['form_id' => $lead->form_id, 'lead_source' => $lead->source]),
        ]);
    }

    /** Records field-level edits; a no-op when nothing actually changed. */
    public function recordUpdated(Lead $lead, array $before, ?int $userId): void
    {
        $changes = $this->diff($before, $this->snapshot($lead->fresh()));
        if ($changes === []) {
            return;
        }

        $this->write($lead, LeadAuditLog::EVENT_UPDATED, $userId, ['changes' => $changes]);
    }

    /**
     * Records a move between stages, including how long the lead sat in the stage it left
     * (time since it last entered a stage, falling back to when the lead was created).
     */
    public function recordStageChange(
        Lead $lead,
        ?int $fromStageId,
        ?int $toStageId,
        ?int $userId,
        string $event = LeadAuditLog::EVENT_STAGE_CHANGED,
        array $meta = [],
    ): void {
        $stages = LeadStage::whereIn('id', array_filter([$fromStageId, $toStageId]))->pluck('name', 'id');

        $enteredAt = LeadAuditLog::query()
            ->where('lead_id', $lead->id)
            ->whereNotNull('to_stage_id')
            ->latest('id')
            ->value('created_at') ?? $lead->created_at;

        $this->write($lead, $event, $userId, [
            'from_stage_id'          => $fromStageId,
            'from_stage_name'        => $fromStageId ? $stages->get($fromStageId) : null,
            'to_stage_id'            => $toStageId,
            'to_stage_name'          => $toStageId ? $stages->get($toStageId) : null,
            'stage_duration_seconds' => $enteredAt ? max(0, now()->getTimestamp() - \Illuminate\Support\Carbon::parse($enteredAt)->getTimestamp()) : null,
            'meta'                   => $meta ?: null,
        ]);
    }

    public function recordDeleted(Lead $lead, ?int $userId): void
    {
        $lead->loadMissing('stage');

        $this->write($lead, LeadAuditLog::EVENT_DELETED, $userId, [
            'from_stage_id'   => $lead->stage_id,
            'from_stage_name' => $lead->stage?->name,
            'meta'            => array_filter([
                'email'           => $lead->email,
                'phone'           => $lead->phone,
                'company'         => $lead->company,
                'estimated_value' => $this->normalise($lead->estimated_value),
            ]),
        ]);
    }

    // ── Reading ──────────────────────────────────────────────────────────

    /** @return Collection<int, LeadAuditLog> newest first */
    public function forLead(Lead $lead): Collection
    {
        return LeadAuditLog::query()
            ->where('lead_id', $lead->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Relation-wide activity feed, newest first.
     *
     * @param  array{event?:string|null, user_id?:int|null, from?:string|null, to?:string|null, q?:string|null}  $filters
     * @param  list<int>|null  $visibleStageIds  null = every stage visible
     */
    public function forProject(Project $project, array $filters = [], ?array $visibleStageIds = null, int $perPage = 50): LengthAwarePaginator
    {
        return $this->projectQuery($project, $filters, $visibleStageIds)
            ->when(filled($filters['event'] ?? null), fn ($q) => $q->where('event', $filters['event']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Stage-movement graph for a relation: every stage as a node, every observed
     * from → to move as an edge (count + average time spent before moving), and each
     * lead's ordered journey through the stages. Feeds the History "flow" view.
     * Honours the same user / date / lead-name filters as the feed (not the event filter —
     * the flow is built from stage events only).
     *
     * @param  list<int>|null  $visibleStageIds  null = every stage visible
     * @return array{stages: list<array>, transitions: list<array>, journeys: list<array>, meta: array}
     */
    public function flowForProject(Project $project, array $filters = [], ?array $visibleStageIds = null, int $maxLeads = 150, int $maxLogs = 5000): array
    {
        $logs = $this->projectQuery($project, $filters, $visibleStageIds)
            ->whereIn('event', LeadAuditLog::STAGE_EVENTS)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($maxLogs)
            ->get();

        $stageCounts = Lead::where('project_id', $project->id)
            ->when(filled($filters['lead_source'] ?? null), fn ($q) => $q->where('source', $filters['lead_source']))
            ->selectRaw('stage_id, COUNT(*) as total')
            ->groupBy('stage_id')
            ->pluck('total', 'stage_id');

        $stages = LeadStage::where('project_id', $project->id)
            ->when($visibleStageIds !== null, fn ($q) => $q->whereIn('id', $visibleStageIds))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (LeadStage $s) => [
                'id'          => $s->id,
                'name'        => $s->name,
                'color'       => $s->color ?: '#94a3b8',
                'is_won'      => $s->is_won,
                'is_lost'     => $s->is_lost,
                'deleted'     => false,
                'leads_count' => (int) ($stageCounts[$s->id] ?? 0),
            ]);

        // Stages that appear in the trail but no longer exist still need a node
        // (stages that exist but are hidden from this user stay hidden).
        $known = LeadStage::where('project_id', $project->id)->pluck('id')->flip();
        $logs->flatMap(fn ($l) => [[$l->from_stage_id, $l->from_stage_name], [$l->to_stage_id, $l->to_stage_name]])
            ->filter(fn ($pair) => $pair[0] && ! $known->has($pair[0]))
            ->unique(fn ($pair) => $pair[0])
            ->each(function ($pair) use ($stages) {
                $stages->push([
                    'id' => $pair[0], 'name' => $pair[1] ?? 'Deleted stage', 'color' => '#94a3b8',
                    'is_won' => false, 'is_lost' => false, 'deleted' => true, 'leads_count' => 0,
                ]);
            });

        // from → to edges; null from = lead entered the relation, null to = lead deleted.
        $transitions = $logs
            ->groupBy(fn ($l) => ($l->from_stage_id ?? 'start') . '>' . ($l->to_stage_id ?? 'end'))
            ->map(function (Collection $group) {
                $first     = $group->first();
                $durations = $group->pluck('stage_duration_seconds')->filter(fn ($d) => $d !== null);

                return [
                    'from_stage_id'        => $first->from_stage_id,
                    'to_stage_id'          => $first->to_stage_id,
                    'count'                => $group->count(),
                    'leads_count'          => $group->pluck('lead_id')->unique()->count(),
                    'events'               => $group->countBy('event')->all(),
                    'avg_duration_seconds' => $durations->isNotEmpty() ? (int) round($durations->avg()) : null,
                    'last_at'              => $group->max('created_at')?->toIso8601String(),
                ];
            })
            ->values();

        // Most recently active leads first, each journey oldest → newest.
        $byLead    = $logs->groupBy('lead_id')->sortByDesc(fn (Collection $g) => $g->last()->id);
        $leadTotal = $byLead->count();
        $byLead    = $byLead->take($maxLeads);
        $liveLeads = Lead::whereIn('id', $byLead->keys())->get(['id', 'stage_id', 'source'])->keyBy('id');
        $current   = $liveLeads->map(fn (Lead $l) => $l->stage_id);
        $presented = collect($this->present($byLead->flatten(1)))->groupBy(fn ($e) => $e['lead']['id']);

        $journeys = $byLead->map(fn (Collection $g, $leadId) => [
            'lead'             => ['id' => (int) $leadId, 'name' => $g->last()->lead_name, 'source' => $liveLeads->get($leadId)?->source],
            'exists'           => $current->has($leadId),
            'current_stage_id' => $current->get($leadId),
            'steps'            => $presented->get($leadId, collect())->values()->all(),
        ])->values();

        return [
            'stages'      => $stages->values()->all(),
            'transitions' => $transitions->all(),
            'journeys'    => $journeys->all(),
            'meta'        => [
                'leads_total'   => $leadTotal,
                'leads_shown'   => $journeys->count(),
                'moves_total'   => $logs->count(),
                'truncated'     => $logs->count() >= $maxLogs,
            ],
        ];
    }

    /** Relation-scoped trail with stage visibility + user / date / lead-name filters applied. */
    private function projectQuery(Project $project, array $filters, ?array $visibleStageIds): \Illuminate\Database\Eloquent\Builder
    {
        return LeadAuditLog::query()
            ->where('project_id', $project->id)
            ->when($visibleStageIds !== null, fn ($q) => $q->where(fn ($q) => $q
                ->whereIn('to_stage_id', $visibleStageIds)
                ->orWhereIn('from_stage_id', $visibleStageIds)
                ->orWhereIn('lead_id', Lead::where('project_id', $project->id)->whereIn('stage_id', $visibleStageIds)->select('id'))))
            ->when(filled($filters['user_id'] ?? null), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->where('created_at', '>=', \Illuminate\Support\Carbon::parse($filters['from'])->startOfDay()))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->where('created_at', '<=', \Illuminate\Support\Carbon::parse($filters['to'])->endOfDay()))
            ->when(filled($filters['q'] ?? null), fn ($q) => $q->where('lead_name', 'like', '%' . $filters['q'] . '%'))
            // Where the lead came from (Facebook, WhatsApp, …) — not the audit `source` column,
            // which records where the change was made. Deleted leads can't be matched.
            ->when(filled($filters['lead_source'] ?? null), fn ($q) => $q->whereIn('lead_id',
                Lead::where('project_id', $project->id)->where('source', $filters['lead_source'])->select('id')));
    }

    /** Users who appear anywhere in this relation's trail — feeds the "changed by" filter. */
    public function actorsForProject(Project $project): Collection
    {
        return LeadAuditLog::query()
            ->where('project_id', $project->id)
            ->whereNotNull('user_id')
            ->select('user_id', 'user_name')
            ->distinct()
            ->orderBy('user_name')
            ->get()
            ->unique('user_id')
            ->map(fn ($row) => ['id' => $row->user_id, 'name' => $row->user_name])
            ->values();
    }

    /**
     * JSON shape shared by the lead and relation history endpoints. Stage colours come from
     * the live stage when it still exists; names always come from the snapshot.
     *
     * @param  Collection<int, LeadAuditLog>  $logs
     * @return list<array<string, mixed>>
     */
    public function present(Collection $logs): array
    {
        $stageIds = $logs->flatMap(fn ($l) => [$l->from_stage_id, $l->to_stage_id])->filter()->unique();
        $colors   = LeadStage::whereIn('id', $stageIds)->pluck('color', 'id');
        $avatars  = \App\Models\User::whereIn('id', $logs->pluck('user_id')->filter()->unique())
            ->get()
            ->mapWithKeys(fn ($u) => [$u->id => $u->avatarUrl()]);
        $labels   = LeadAuditLog::events();
        $stage    = fn ($id, $name) => $id || $name ? ['id' => $id, 'name' => $name ?? 'Deleted stage', 'color' => $colors->get($id) ?: '#94a3b8'] : null;

        return $logs->map(fn (LeadAuditLog $log) => [
            'id'                     => $log->id,
            'event'                  => $log->event,
            'event_label'            => $labels[$log->event] ?? ucfirst(str_replace('_', ' ', $log->event)),
            'lead'                   => ['id' => $log->lead_id, 'name' => $log->lead_name],
            'user'                   => $log->user_id || $log->user_name ? [
                'id'         => $log->user_id,
                'name'       => $log->user_name ?? 'Deleted user',
                'avatar_url' => $avatars->get($log->user_id),
            ] : null,
            'source'                 => $log->source,
            'ip_address'             => $log->ip_address,
            'from_stage'             => $stage($log->from_stage_id, $log->from_stage_name),
            'to_stage'               => $stage($log->to_stage_id, $log->to_stage_name),
            'stage_duration_seconds' => $log->stage_duration_seconds,
            'changes'                => $log->changes ?? [],
            'meta'                   => $log->meta ?? (object) [],
            'created_at'             => $log->created_at?->toIso8601String(),
        ])->values()->all();
    }

    // ── Internals ────────────────────────────────────────────────────────

    private function write(Lead $lead, string $event, ?int $userId, array $attributes = []): void
    {
        [$source, $ip] = $this->context($userId, $event, $lead);

        LeadAuditLog::create([
            'business_id' => $lead->business_id,
            'project_id'  => $lead->project_id,
            'lead_id'     => $lead->id,
            'lead_name'   => $lead->name,
            'event'       => $event,
            'user_id'     => $userId,
            'user_name'   => $userId ? \App\Models\User::whereKey($userId)->value('name') : null,
            'source'      => $source,
            'ip_address'  => $ip,
        ] + $attributes);
    }

    /**
     * Where the change came from. Authenticated API calls are the desktop app; authenticated
     * web requests are the browser UI. With no user, a new lead carries its own source
     * (e.g. "public-form"); anything else unattended is an automation/system action.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function context(?int $userId, string $event, Lead $lead): array
    {
        if (app()->runningInConsole()) {
            return [LeadAuditLog::SOURCE_SYSTEM, null];
        }

        $request = request();

        if ($userId === null) {
            $source = $event === LeadAuditLog::EVENT_CREATED ? ($lead->source ?: LeadAuditLog::SOURCE_SYSTEM) : LeadAuditLog::SOURCE_SYSTEM;
            return [$source, $request->ip()];
        }

        return [$request->is('api/*') ? LeadAuditLog::SOURCE_DESKTOP : LeadAuditLog::SOURCE_WEB, $request->ip()];
    }

    /**
     * @return list<array{field: string, label: string, old: string|null, new: string|null}>
     */
    private function diff(array $before, array $after): array
    {
        $changes = [];
        $keys    = array_unique(array_merge(array_keys($before), array_keys($after)));

        $customIds    = collect($keys)->filter(fn ($k) => str_starts_with($k, 'custom:'))->map(fn ($k) => (int) substr($k, 7));
        $customLabels = $customIds->isNotEmpty() ? LeadCustomField::whereIn('id', $customIds)->pluck('label', 'id') : collect();

        foreach ($keys as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old === $new) {
                continue;
            }

            if ($key === 'assigned_to') {
                $names = \App\Models\User::whereIn('id', array_filter([$old, $new]))->pluck('name', 'id');
                $old   = $old !== null ? ($names->get((int) $old) ?? "User #{$old}") : null;
                $new   = $new !== null ? ($names->get((int) $new) ?? "User #{$new}") : null;
            } elseif ($key === 'source') {
                $old = Lead::sourceLabel($old);
                $new = Lead::sourceLabel($new);
            } elseif ($key === 'score') {
                $old = Lead::scoreLabel($old);
                $new = Lead::scoreLabel($new);
            } elseif ($key === 'division_id') {
                $names = \Modules\CRM\Models\Division::whereIn('id', array_filter([$old, $new]))->pluck('name', 'id');
                $old   = $old !== null ? ($names->get((int) $old) ?? "Division #{$old}") : null;
                $new   = $new !== null ? ($names->get((int) $new) ?? "Division #{$new}") : null;
            }

            $label = str_starts_with($key, 'custom:')
                ? ($customLabels->get((int) substr($key, 7)) ?? 'Custom field')
                : self::TRACKED_FIELDS[$key];

            $changes[] = ['field' => $key, 'label' => $label, 'old' => $old, 'new' => $new];
        }

        return $changes;
    }

    private function normalise(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
