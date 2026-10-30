<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\AutomationEditor\Models\AutomationFlow;
use Modules\CRM\Models\Division;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadAuditLog;
use Modules\CRM\Models\LeadCustomFieldValue;
use Modules\CRM\Models\LeadForm;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\LeadStageLog;
use Modules\CRM\Models\Project;
use Modules\Pos\Models\Customer;
use Modules\Product\Models\Product;

class LeadService
{
    public function __construct(
        private readonly LeadStageService $stages,
        private readonly LeadStageMailTemplateService $stageMailTemplates,
        private readonly LeadAuditService $audit,
        private readonly LeadSourceService $sources,
        private readonly LostReasonService $lostReasons,
        private readonly CrmSettingsService $settings,
    ) {}

    public function listForProject(
        Project $project,
        ?string $search = null,
        ?int $stageId = null,
        ?string $status = null,
        ?string $source = null,
    ): Collection {
        $query = Lead::query()
            ->where('project_id', $project->id)
            ->with(['assignedTo', 'assignees.user:id,name', 'customer', 'stage']);

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($stageId !== null) {
            $query->where('stage_id', $stageId);
        }

        if (filled($source)) {
            $query->where('source', $source);
        }

        if (filled($status) && $status !== 'all') {
            match ($status) {
                'won'  => $query->whereHas('stage', fn ($q) => $q->where('is_won', true)),
                'lost' => $query->whereHas('stage', fn ($q) => $q->where('is_lost', true)),
                'open' => $query->whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false)),
                default => null,
            };
        }

        return $query->orderByDesc('id')->get();
    }

    public function projectHasLeads(Project $project): bool
    {
        return Lead::query()->where('project_id', $project->id)->exists();
    }

    /**
     * Leads submitted through a "Custom Data Entry Form" (LeadForm::TYPE_CUSTOM_DATA) — shown
     * as a spreadsheet-style table (grouped by the form they came from, since each form has its
     * own columns) in the Relation's "Custom Data" tab, separate from the kanban Pipeline view.
     * These leads still also appear on the Pipeline like any other lead.
     */
    public function customDataLeadsForProject(Project $project): Collection
    {
        return Lead::query()
            ->where('project_id', $project->id)
            ->whereHas('form', fn ($q) => $q->where('type', LeadForm::TYPE_CUSTOM_DATA))
            ->with(['stage', 'form', 'customFieldValues:id,lead_id,custom_field_id,value'])
            ->orderByDesc('id')
            ->get();
    }

    public function customDataLeadCount(Project $project): int
    {
        return Lead::query()
            ->where('project_id', $project->id)
            ->whereHas('form', fn ($q) => $q->where('type', LeadForm::TYPE_CUSTOM_DATA))
            ->count();
    }

    public function pipelineSummary(Project $project): array
    {
        $openStages = $this->stages->listForProject($project)->reject(fn ($s) => $s->isTerminal());

        $rows = Lead::query()
            ->where('project_id', $project->id)
            ->whereIn('stage_id', $openStages->pluck('id'))
            ->selectRaw('stage_id, count(*) as leads_count, sum(estimated_value) as value_total')
            ->groupBy('stage_id')
            ->get()
            ->keyBy('stage_id');

        $summary = [];
        foreach ($openStages as $stage) {
            $row = $rows->get($stage->id);
            $summary[$stage->id] = [
                'label' => $stage->name,
                'color' => $stage->color,
                'count' => (int) ($row->leads_count ?? 0),
                'value' => (float) ($row->value_total ?? 0),
            ];
        }

        return $summary;
    }

    /**
     * Cumulative lead count per stage at each day, counted from lead creation date
     * through the current stage assignment. Stage moves are not history-tracked —
     * only the current stage is used, same limitation as the HR headcount chart.
     *
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>, hasData: bool, note: string}
     */
    public function stageTrend(Project $project, int $maxDays = 60): array
    {
        $note = 'Each line is cumulative leads currently in that stage, counted from creation date. Stage moves are not history-tracked — only the current stage is used.';

        $stages = $this->stages->listForProject($project);

        $leads = Lead::query()
            ->where('project_id', $project->id)
            ->whereNotNull('stage_id')
            ->get(['id', 'stage_id', 'created_at']);

        if ($leads->isEmpty()) {
            return ['labels' => [], 'datasets' => [], 'hasData' => false, 'note' => $note];
        }

        $minCreated = $leads->min('created_at');
        $days       = $this->dayGridFromEarliest($minCreated, $maxDays);
        $labels     = array_map(fn ($d) => $d->format('M j'), $days);

        $palette = [
            ['border' => '#2a78d6', 'background' => 'rgba(42,120,214,.12)'],
            ['border' => '#1baf7a', 'background' => 'rgba(27,175,122,.12)'],
            ['border' => '#eda100', 'background' => 'rgba(237,161,0,.12)'],
            ['border' => '#008300', 'background' => 'rgba(0,131,0,.12)'],
            ['border' => '#4a3aa7', 'background' => 'rgba(74,58,167,.12)'],
            ['border' => '#e34948', 'background' => 'rgba(227,73,72,.12)'],
            ['border' => '#e87ba4', 'background' => 'rgba(232,123,164,.12)'],
            ['border' => '#eb6834', 'background' => 'rgba(235,104,52,.12)'],
        ];

        $datasets = [];
        $idx      = 0;
        foreach ($stages as $stage) {
            $data = [];
            foreach ($days as $day) {
                $cutoff  = $day->copy()->endOfDay();
                $data[]  = $leads->filter(fn ($l) => (int) $l->stage_id === $stage->id && $l->created_at->lte($cutoff))->count();
            }

            if (array_sum($data) === 0) {
                continue;
            }

            $color      = $palette[$idx % count($palette)];
            $datasets[] = [
                'label'           => $stage->name,
                'data'            => $data,
                'fill'            => false,
                'tension'         => 0.3,
                'borderWidth'     => 2,
                'pointRadius'     => 3,
                'pointHoverRadius' => 5,
                'borderColor'     => $color['border'],
                'backgroundColor' => $color['background'],
            ];
            $idx++;
        }

        return [
            'labels'  => $labels,
            'datasets' => $datasets,
            'hasData' => $datasets !== [],
            'note'    => $note,
        ];
    }

    /**
     * @return list<\Illuminate\Support\Carbon>
     */
    private function dayGridFromEarliest(\Illuminate\Support\Carbon $minCreated, int $maxDays): array
    {
        $start = $minCreated->copy()->startOfDay();
        $end   = now()->startOfDay();

        if ($start->gt($end)) {
            $start = $end->copy();
        }

        $days   = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        if (count($days) > $maxDays) {
            $days = array_slice($days, -$maxDays);
        }

        return $days;
    }

    /**
     * @return Collection<int, Collection<int, Lead>>
     */
    public function groupedByStage(Project $project): Collection
    {
        return Lead::query()
            ->where('project_id', $project->id)
            ->with(['assignedTo', 'assignees.user:id,name'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('stage_id');
    }

    /**
     * Leads submitted through any of the given (typically "generic"/custom-data)
     * forms, grouped by form_id — one spreadsheet-style table per form, each with
     * its own custom field values loaded for column rendering.
     *
     * @param  \Illuminate\Support\Collection<int, \Modules\CRM\Models\LeadForm>  $forms
     * @return Collection<int, Collection<int, Lead>>
     */
    public function groupedByForm(Project $project, Collection $forms): Collection
    {
        if ($forms->isEmpty()) {
            return collect();
        }

        return Lead::query()
            ->where('project_id', $project->id)
            ->whereIn('form_id', $forms->pluck('id'))
            ->with('customFieldValues')
            ->orderByDesc('id')
            ->get()
            ->groupBy('form_id');
    }

    public function create(Project $project, array $data, ?int $userId = null): Lead
    {
        $stageId = $this->nullableInt($data['stage_id'] ?? null) ?? $this->stages->defaultOpenStage($project)?->id;
        $stage   = $stageId !== null ? $this->stageOfProject($project->id, $stageId) : null;

        [$stageAttributes, $reason] = $stage ? $this->stageEntry(null, $project->business_id, null, $stage, $data) : [[], null];
        $source = $this->sources->resolve($project->business_id, $data['source'] ?? null, $data['source_id'] ?? null);

        // The assignee is set through LeadAssignmentService once the lead exists — check it first
        $assignee = $this->nullableInt($data['assigned_to'] ?? null);
        if ($assignee !== null) {
            $this->assignments()->assertAssignableIn($project, [$assignee]);
        }

        $lead = DB::transaction(function () use ($project, $data, $userId, $stageId, $stageAttributes, $source) {
            $lead = Lead::create([
                'business_id'           => $project->business_id,
                'project_id'            => $project->id,
                'department_id'         => $project->department_id,
                'division_id'           => $this->cleanDivisionId($project->business_id, $project->department_id, $data['division_id'] ?? null),
                'form_id'               => $this->nullableInt($data['form_id'] ?? null),
                'name'                  => $data['name'],
                'company'               => filled($data['company'] ?? '') ? $data['company'] : null,
                'email'                 => filled($data['email'] ?? '') ? $data['email'] : null,
                'phone'                 => filled($data['phone'] ?? '') ? $data['phone'] : null,
                'normalized_phone'      => $this->settings->normalizePhone($project->business_id, $data['phone'] ?? null),
                'source'                => $source['source'],
                'source_id'             => $source['source_id'],
                'external_id'           => filled($data['external_id'] ?? '') ? $data['external_id'] : null,
                // Set by LeadIntakeService when the contact is already known
                'customer_id'           => $this->nullableInt($data['customer_id'] ?? null),
                'needs_review'          => (bool) ($data['needs_review'] ?? false),
                'review_reason'         => filled($data['review_reason'] ?? '') ? $data['review_reason'] : null,
                'duplicate_of_lead_id'  => $this->nullableInt($data['duplicate_of_lead_id'] ?? null),
                'requested_department_id' => $this->nullableInt($data['requested_department_id'] ?? null),
                'stage_id'              => $stageId,
                'estimated_value'       => $this->nullableDecimal($data['estimated_value'] ?? null),
                'score'                 => $this->cleanScore($data['score'] ?? null),
                'expected_close_date'   => filled($data['expected_close_date'] ?? '') ? $data['expected_close_date'] : null,
                'notes'                 => filled($data['notes'] ?? '') ? $data['notes'] : null,
                'product_interest_note' => filled($data['product_interest_note'] ?? '') ? $data['product_interest_note'] : null,
                'created_by'            => $userId,
            ] + $stageAttributes);

            if (array_key_exists('products', $data)) {
                $this->syncProducts($lead, (array) $data['products']);
            }

            return $lead;
        });

        $this->syncCustomFieldValues($lead, $data['custom_fields'] ?? []);
        $this->audit->recordCreated($lead, $userId);
        $this->logStageChange($lead, null, $stageId, $userId, reason: $reason);
        $this->dispatchLeadCreatedAutomation($lead);

        // logStageChange() skips the auto-send-mail check for a brand-new lead's
        // initial stage (fromStageId is null there), so cover that moment here —
        // a lead landing straight into a templated stage should mail it too.
        if ($stageId) {
            $lead->loadMissing('stage');
            if ($lead->stage) {
                $this->autoSendStageMail($lead, $lead->stage);
            }
        }

        if ($assignee !== null) {
            $lead = $this->assignments()->sync($lead, [$assignee], $assignee, $userId);
        }

        return $lead;
    }

    /** Resolved lazily — LeadAssignmentService itself depends on this service. */
    private function assignments(): LeadAssignmentService
    {
        return app(LeadAssignmentService::class);
    }

    /**
     * Fire the "Lead Created (in Relation)" automation trigger. Lives here — the single
     * place all lead-creation entry points (desktop New Lead form, public web form, JSON
     * API) funnel through — so no entry point can silently skip it.
     */
    private function dispatchLeadCreatedAutomation(Lead $lead): void
    {
        try {
            $lead->loadMissing('stage', 'project');

            app(\Modules\AutomationEditor\Services\AutomationRunnerService::class)->dispatch(
                'crm.lead.created',
                $lead->project->business,
                [
                    'event' => 'crm.lead.created',
                    'lead'  => [
                        'id'         => $lead->id,
                        'name'       => $lead->name,
                        'email'      => $lead->email,
                        'phone'      => $lead->phone,
                        'project_id' => $lead->project_id,
                        'stage_id'   => $lead->stage_id,
                        'stage_name' => $lead->stage?->name,
                        'created_at' => $lead->created_at?->toIso8601String(),
                    ],
                    'relation' => ['id' => $lead->project->id, 'name' => $lead->project->name],
                ],
            );
        } catch (\Throwable) {}
    }

    public function update(Lead $lead, array $data, ?int $userId = null): Lead
    {
        $oldStageId = $lead->stage_id;
        $newStageId = $this->nullableInt($data['stage_id'] ?? null) ?? $lead->stage_id;
        $before     = $this->audit->snapshot($lead);

        $stageAttributes = [];
        $reason          = null;
        if ($newStageId !== $oldStageId) {
            [$stageAttributes, $reason] = $this->stageEntry($lead, $lead->business_id, $lead->stage, $this->stageOfProject($lead->project_id, $newStageId), $data);
        } elseif ($lead->isLost() && filled($data['lost_reason_id'] ?? null)) {
            // Changing the reason of a lead that stays lost
            $stageAttributes = [
                'lost_reason_id' => $this->lostReasons->requireActive($lead->business_id, $data['lost_reason_id'])->id,
                'lost_reason'    => array_key_exists('lost_reason', $data) ? $this->lostNote($data['lost_reason']) : $lead->lost_reason,
            ];
        }

        $source = array_key_exists('source', $data) || array_key_exists('source_id', $data)
            ? $this->sources->resolve($lead->business_id, $data['source'] ?? null, $data['source_id'] ?? null)
            : ['source' => $lead->source, 'source_id' => $lead->source_id];

        DB::transaction(function () use ($lead, $data, $newStageId, $stageAttributes, $source) {
            $lead->update([
                'name'                  => $data['name'],
                'company'               => filled($data['company'] ?? '') ? $data['company'] : null,
                'email'                 => filled($data['email'] ?? '') ? $data['email'] : null,
                'phone'                 => filled($data['phone'] ?? '') ? $data['phone'] : null,
                'normalized_phone'      => $this->settings->normalizePhone($lead->business_id, $data['phone'] ?? null),
                // Desktop's Edit Lead modal doesn't expose every field (value / close date / …),
                // so its requests omit those keys entirely — preserve the lead's current value
                // rather than wiping it to null. Callers that do send the key (e.g. the web edit
                // form) still control it.
                'source'                => $source['source'],
                'source_id'             => $source['source_id'],
                'stage_id'              => $newStageId,
                'estimated_value'       => array_key_exists('estimated_value', $data)
                    ? $this->nullableDecimal($data['estimated_value'])
                    : $lead->estimated_value,
                'score'                 => array_key_exists('score', $data)
                    ? $this->cleanScore($data['score'])
                    : $lead->score,
                'division_id'           => array_key_exists('division_id', $data)
                    ? $this->cleanDivisionId($lead->business_id, $lead->department_id, $data['division_id'])
                    : $lead->division_id,
                'expected_close_date'   => array_key_exists('expected_close_date', $data)
                    ? (filled($data['expected_close_date']) ? $data['expected_close_date'] : null)
                    : $lead->expected_close_date,
                'notes'                 => filled($data['notes'] ?? '') ? $data['notes'] : null,
                'product_interest_note' => array_key_exists('product_interest_note', $data)
                    ? (filled($data['product_interest_note']) ? $data['product_interest_note'] : null)
                    : $lead->product_interest_note,
                // assigned_to is not edited here: it follows the primary assignee (LeadAssignmentService)
            ] + $stageAttributes);

            if (array_key_exists('products', $data)) {
                $this->syncProducts($lead, (array) $data['products']);
            }
        });

        $this->syncCustomFieldValues($lead, $data['custom_fields'] ?? []);
        $this->audit->recordUpdated($lead, $before, $userId);
        $this->logStageChange($lead, $oldStageId, $newStageId, $userId, reason: $reason);

        return $lead->fresh();
    }

    /** Move the lead to its pipeline's lost stage; a lost reason is required. */
    public function markLost(Lead $lead, mixed $lostReasonId, ?string $note = null, ?int $userId = null): Lead
    {
        $lostStage = $this->stages->lostStage($lead->project);
        if ($lostStage === null) {
            throw ValidationException::withMessages(['stage_id' => 'This pipeline has no lost stage.']);
        }

        return $this->moveTo($lead, $lostStage, $userId, ['lost_reason_id' => $lostReasonId, 'lost_reason' => $note], event: LeadAuditLog::EVENT_LOST);
    }

    public function reopen(Lead $lead, ?int $userId = null): Lead
    {
        $openStage = $this->stages->defaultOpenStage($lead->project);
        if ($openStage === null) {
            throw ValidationException::withMessages(['stage_id' => 'This pipeline has no open stage.']);
        }

        return $this->moveTo($lead, $openStage, $userId, system: true, event: LeadAuditLog::EVENT_REOPENED);
    }

    /** Put the lead on hold; resume() later returns it to the stage it was in. */
    public function putOnHold(Lead $lead, ?string $reason = null, ?int $userId = null): Lead
    {
        $stage = $this->stages->stageForStatus($lead->project, LeadStage::STATUS_ON_HOLD);
        if ($stage === null) {
            throw ValidationException::withMessages(['stage_id' => 'This pipeline has no "On Hold" stage.']);
        }

        return $this->moveTo($lead, $stage, $userId, ['reason' => $reason]);
    }

    /** Take an on-hold lead back to the stage it was in before (or the default open stage). */
    public function resume(Lead $lead, ?int $userId = null): Lead
    {
        $lead->loadMissing('stage');
        if (! $lead->isOnHold()) {
            throw ValidationException::withMessages(['stage_id' => 'Only a lead on hold can be resumed.']);
        }

        $target = $lead->on_hold_from_stage_id
            ? LeadStage::query()->where('project_id', $lead->project_id)->find($lead->on_hold_from_stage_id)
            : null;
        $target ??= $this->stages->defaultOpenStage($lead->project);

        // The stage it left may be one only the system sets (e.g. quotation sent) — returning there is fine
        return $this->moveTo($lead, $target, $userId, system: true);
    }

    /**
     * Move the lead to the pipeline's stage for a standard status. For the workflows that own the
     * reserved statuses (quotation approval / sending), which pass $system = true.
     */
    public function moveToStatus(Lead $lead, string $statusKey, ?int $userId = null, array $context = [], bool $system = false): Lead
    {
        $stage = $this->stages->stageForStatus($lead->project, $statusKey);
        if ($stage === null) {
            throw ValidationException::withMessages(['stage_id' => 'This pipeline has no "' . (LeadStage::statusLabel($statusKey) ?? $statusKey) . '" stage.']);
        }

        return $this->moveTo($lead, $stage, $userId, $context, $system);
    }

    /**
     * Move a lead into another pipeline's "new" stage (forwarding from the marketing inbox).
     * Writes the stage log and a "forwarded" audit entry; the lead enters the new pipeline
     * like a new arrival, so that pipeline's stage mail and automations apply.
     *
     * @param  array<string, mixed>  $attributes  extra lead attributes saved with the move
     */
    public function moveToProject(Lead $lead, Project $project, ?int $userId, array $attributes = [], array $meta = []): Lead
    {
        $to = $this->stages->defaultOpenStage($project);
        if ($to === null) {
            throw ValidationException::withMessages(['department_id' => 'The pipeline "' . $project->name . '" has no open stage.']);
        }

        $lead->loadMissing(['stage', 'project']);
        $from        = $lead->stage;
        $fromProject = $lead->project;

        $lead->update([
            'project_id'            => $project->id,
            'department_id'         => $project->department_id,
            'stage_id'              => $to->id,
            'on_hold_from_stage_id' => null,
        ] + $attributes);
        $lead->setRelation('stage', $to);
        $lead->setRelation('project', $project);

        LeadStageLog::create([
            'lead_id'       => $lead->id,
            'project_id'    => $project->id,
            'from_stage_id' => $from?->id,
            'to_stage_id'   => $to->id,
            'changed_by'    => $userId,
            'reason'        => 'Forwarded from ' . ($fromProject?->name ?? 'another pipeline'),
        ]);

        $this->audit->recordForwarded($lead, $to, $userId, $meta + array_filter([
            'from_project'    => $fromProject?->name,
            'from_stage_name' => $from?->name,
        ]));
        $this->dispatchLeadCreatedAutomation($lead);
        $this->autoSendStageMail($lead, $to);

        return $lead;
    }

    public function convertToCustomer(Lead $lead, ?int $userId = null): Lead
    {
        if (!$lead->isOpen()) {
            throw ValidationException::withMessages(['lead' => 'Only open leads can be converted.']);
        }

        return DB::transaction(function () use ($lead, $userId) {
            $oldStageId = $lead->stage_id;

            $customer = Customer::create([
                'business_id' => $lead->business_id,
                'name'        => $lead->company ?: $lead->name,
                'phone'       => $lead->phone,
                'email'       => $lead->email,
                'notes'       => $lead->notes,
            ]);

            $wonStage = $this->stages->wonStage($lead->project);
            [$stageAttributes] = $wonStage ? $this->stageEntry($lead, $lead->business_id, $lead->stage, $wonStage, [], true) : [[]];

            $lead->update([
                'stage_id'     => $wonStage?->id,
                'customer_id'  => $customer->id,
                'converted_at' => now(),
            ] + $stageAttributes);

            $this->logStageChange($lead, $oldStageId, $wonStage?->id, $userId, LeadAuditLog::EVENT_CONVERTED, ['customer_id' => $customer->id]);

            return $lead->fresh();
        });
    }

    /**
     * Move a lead to another stage of its pipeline by hand.
     *
     * @param  array{lost_reason_id?: int|string|null, lost_reason?: ?string, reason?: ?string}  $context
     */
    public function moveStage(Lead $lead, int $stageId, ?int $userId = null, array $context = []): Lead
    {
        return $this->moveTo($lead, $this->stageOfProject($lead->project_id, $stageId), $userId, $context);
    }

    private function moveTo(
        Lead $lead,
        LeadStage $to,
        ?int $userId,
        array $context = [],
        bool $system = false,
        string $event = LeadAuditLog::EVENT_STAGE_CHANGED,
    ): Lead {
        $lead->loadMissing('stage');
        $oldStageId = $lead->stage_id;
        if ((int) $oldStageId === (int) $to->id) {
            return $lead;
        }

        [$attributes, $reason] = $this->stageEntry($lead, $lead->business_id, $lead->stage, $to, $context, $system);

        $lead->update(['stage_id' => $to->id] + $attributes);
        $lead->setRelation('stage', $to);

        $meta = $event === LeadAuditLog::EVENT_LOST ? array_filter(['reason' => $reason]) : [];
        $this->logStageChange($lead, $oldStageId, $to->id, $userId, $event, $meta, $reason);

        return $lead;
    }

    /**
     * The status rules for a lead entering a stage, as lead attributes to save with the move plus
     * the reason to log. Reserved statuses are only set by the system; a lost stage needs a lost
     * reason and other reason-requiring stages a free-text reason; won/lost close the lead;
     * on hold remembers the stage the lead came from.
     *
     * @return array{0: array<string, mixed>, 1: ?string}
     */
    private function stageEntry(?Lead $lead, int $businessId, ?LeadStage $from, LeadStage $to, array $context, bool $system = false): array
    {
        if ($to->isReserved() && ! $system) {
            throw ValidationException::withMessages([
                'stage_id' => '"' . $to->name . '" is set by the quotation workflow and cannot be chosen by hand.',
            ]);
        }

        $attributes = [];
        $reason     = null;

        if ($to->isLostStage()) {
            $lostReason = $this->lostReasons->requireActive($businessId, $context['lost_reason_id'] ?? null);
            $note       = $this->lostNote($context['lost_reason'] ?? null);

            $attributes['lost_reason_id'] = $lostReason->id;
            $attributes['lost_reason']    = $note;
            $reason = $lostReason->label . ($note !== null ? ': ' . $note : '');
        } else {
            if ($to->requires_reason) {
                $reason = trim((string) ($context['reason'] ?? ''));
                if ($reason === '') {
                    throw ValidationException::withMessages(['reason' => 'Give a reason for moving this lead to "' . $to->name . '".']);
                }
                $reason = mb_substr($reason, 0, 500);
            }
            if ($from?->isLostStage() || $lead?->lost_reason_id) {
                $attributes['lost_reason_id'] = null;
                $attributes['lost_reason']    = null;
            }
        }

        // Won / lost close the lead; moving between the two keeps the original close time
        if ($to->isTerminal()) {
            if (! $from?->isTerminal() || ! $lead?->closed_at) {
                $attributes['closed_at'] = now();
            }
        } elseif ($lead?->closed_at) {
            $attributes['closed_at'] = null;
        }

        if ($to->isOnHold()) {
            if (! $from?->isOnHold()) {
                $attributes['on_hold_from_stage_id'] = $from?->id;
            }
        } elseif ($lead?->on_hold_from_stage_id) {
            $attributes['on_hold_from_stage_id'] = null;
        }

        return [$attributes, $reason];
    }

    private function stageOfProject(int $projectId, int $stageId): LeadStage
    {
        $stage = LeadStage::query()->where('project_id', $projectId)->find($stageId);
        if ($stage === null) {
            throw ValidationException::withMessages(['stage_id' => 'This stage does not belong to the lead\'s pipeline.']);
        }

        return $stage;
    }

    /**
     * @return Collection<int, LeadStageLog>
     */
    public function stageLogsForProject(Project $project, int $limit = 20): Collection
    {
        return LeadStageLog::query()
            ->where('project_id', $project->id)
            ->with(['lead', 'fromStage', 'toStage', 'changedBy'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, LeadStageLog>
     */
    public function stageLogsForLead(Lead $lead): Collection
    {
        return LeadStageLog::query()
            ->where('lead_id', $lead->id)
            ->with(['fromStage', 'toStage', 'changedBy'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @param  string  $event  audit event for the move — lost/reopened/converted moves are
     *                         recorded under their own event so the history reads naturally
     */
    private function logStageChange(
        Lead $lead,
        ?int $fromStageId,
        ?int $toStageId,
        ?int $userId,
        string $event = LeadAuditLog::EVENT_STAGE_CHANGED,
        array $meta = [],
        ?string $reason = null,
    ): void {
        if ($fromStageId === $toStageId) {
            return;
        }

        LeadStageLog::create([
            'lead_id'       => $lead->id,
            'project_id'    => $lead->project_id,
            'from_stage_id' => $fromStageId,
            'to_stage_id'   => $toStageId,
            'changed_by'    => $userId,
            'reason'        => $reason,
        ]);

        // A brand-new lead's first stage is already covered by its "created" audit entry.
        if ($fromStageId !== null) {
            $this->audit->recordStageChange($lead, $fromStageId, $toStageId, $userId, $event, $meta);
        }

        if ($fromStageId !== null && $toStageId !== null) {
            $this->dispatchLeadStageChangedAutomation($lead, $fromStageId, $toStageId);

            $toStage = LeadStage::find($toStageId);
            if ($toStage) {
                $this->autoSendStageMail($lead, $toStage);
            }
        }
    }

    /**
     * When Pipeline Automation is OFF for the lead's relation and the stage
     * it just entered has an active mail template, send it to just that
     * lead. While Pipeline Automation is ON, the linked flow owns stage-entry
     * side effects instead — the two are mutually exclusive so a lead never
     * gets emailed twice for the same stage move.
     */
    private function autoSendStageMail(Lead $lead, LeadStage $toStage): void
    {
        try {
            $lead->loadMissing('project');
            if (!$lead->project) {
                return;
            }

            $pipelineActive = AutomationFlow::pipelineAutomationActive($lead->project->business_id, $lead->project->id);
            if ($pipelineActive) {
                return;
            }

            $template = $this->stageMailTemplates->forStage($toStage);
            if (!$template || !$template->is_active) {
                return;
            }

            $this->stageMailTemplates->sendToLead($lead, $template);
        } catch (\Throwable) {}
    }

    /**
     * Fire the "Lead Stage Changed (in Relation)" automation trigger whenever an
     * existing lead moves between two stages — e.g. dragged to another column on
     * the Pipeline board. Deliberately skipped for the lead's initial stage
     * assignment at creation (fromStageId null) — dispatchLeadCreatedAutomation()
     * already covers that moment.
     */
    private function dispatchLeadStageChangedAutomation(Lead $lead, int $fromStageId, int $toStageId): void
    {
        try {
            $lead->loadMissing('project');

            $fromStage = LeadStage::find($fromStageId);
            $toStage   = LeadStage::find($toStageId);
            if (!$toStage) {
                return;
            }

            app(\Modules\AutomationEditor\Services\AutomationRunnerService::class)->dispatch(
                'crm.lead.stage_changed',
                $lead->project->business,
                [
                    'event' => 'crm.lead.stage_changed',
                    'lead'  => [
                        'id'         => $lead->id,
                        'name'       => $lead->name,
                        'email'      => $lead->email,
                        'phone'      => $lead->phone,
                        'project_id' => $lead->project_id,
                        'stage_id'   => $toStage->id,
                        'stage_name' => $toStage->name,
                    ],
                    'from_stage' => ['id' => $fromStage?->id, 'name' => $fromStage?->name],
                    'to_stage'   => ['id' => $toStage->id, 'name' => $toStage->name],
                    'relation'   => ['id' => $lead->project->id, 'name' => $lead->project->name],
                ],
            );
        } catch (\Throwable) {}
    }

    public function delete(Lead $lead, ?int $userId = null, array $auditMeta = []): void
    {
        $this->audit->recordDeleted($lead, $userId, $auditMeta);
        // The attachment rows go with the lead; their stored files would be left behind
        app(LeadAttachmentService::class)->deleteAllFor($lead);
        $lead->delete();
    }

    public function leadForProject(Project $project, Lead $lead): ?Lead
    {
        return $lead->project_id === $project->id ? $lead : null;
    }

    private function syncCustomFieldValues(Lead $lead, array $customFieldInputs): void
    {
        foreach ($customFieldInputs as $fieldId => $value) {
            if ($value === null || $value === '') {
                LeadCustomFieldValue::where('lead_id', $lead->id)->where('custom_field_id', $fieldId)->delete();
                continue;
            }

            LeadCustomFieldValue::updateOrCreate(
                ['lead_id' => $lead->id, 'custom_field_id' => $fieldId],
                ['value' => $value],
            );
        }
    }

    /**
     * Replace the lead's products of interest. Items are product ids or
     * ['product_id' => id, 'quantity' => n|null]; every product must belong to the lead's business.
     */
    private function syncProducts(Lead $lead, array $items): void
    {
        $rows = [];
        foreach ($items as $item) {
            $id  = (int) (is_array($item) ? ($item['product_id'] ?? $item['id'] ?? 0) : $item);
            $qty = is_array($item) ? $this->nullableDecimal($item['quantity'] ?? null) : null;
            if ($id > 0) {
                $rows[$id] = ['quantity' => $qty];
            }
        }

        $found = $rows === [] ? 0 : Product::query()
            ->where('business_id', $lead->business_id)
            ->whereIn('id', array_keys($rows))
            ->count();
        if ($found !== count($rows)) {
            throw ValidationException::withMessages(['products' => 'One or more of the selected products were not found.']);
        }

        $lead->products()->sync($rows);
    }

    private function cleanScore(mixed $score): ?string
    {
        if ($score === null || $score === '') {
            return null;
        }
        if (! array_key_exists($score, Lead::SCORES)) {
            throw ValidationException::withMessages(['score' => 'The score must be hot, warm or cold.']);
        }

        return $score;
    }

    /** A division of the lead's department, or null. */
    private function cleanDivisionId(int $businessId, ?int $departmentId, mixed $divisionId): ?int
    {
        $divisionId = $this->nullableInt($divisionId);
        if ($divisionId === null) {
            return null;
        }

        $exists = $departmentId !== null && Division::query()
            ->where('business_id', $businessId)
            ->where('department_id', $departmentId)
            ->whereKey($divisionId)
            ->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['division_id' => 'Pick a division of the lead\'s department.']);
        }

        return $divisionId;
    }

    private function lostNote(mixed $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : mb_substr($note, 0, 255);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }
}
