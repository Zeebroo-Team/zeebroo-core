<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadCustomFieldValue;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\LeadStageLog;
use Modules\CRM\Models\Project;
use Modules\Pos\Models\Customer;

class LeadService
{
    public function __construct(
        private readonly LeadStageService $stages,
        private readonly LeadStageAutomationService $stageAutomations,
    ) {}

    public function listForProject(
        Project $project,
        ?string $search = null,
        ?int $stageId = null,
        ?string $status = null,
    ): Collection {
        $query = Lead::query()
            ->where('project_id', $project->id)
            ->with(['assignedTo', 'customer', 'stage']);

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
            ->with(['assignedTo'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('stage_id');
    }

    public function create(Project $project, array $data, ?int $userId = null): Lead
    {
        $stageId = $this->nullableInt($data['stage_id'] ?? null) ?? $this->stages->defaultOpenStage($project)?->id;

        $lead = Lead::create([
            'business_id'         => $project->business_id,
            'project_id'          => $project->id,
            'name'                => $data['name'],
            'company'             => filled($data['company'] ?? '') ? $data['company'] : null,
            'email'               => filled($data['email'] ?? '') ? $data['email'] : null,
            'phone'               => filled($data['phone'] ?? '') ? $data['phone'] : null,
            'source'              => filled($data['source'] ?? '') ? $data['source'] : null,
            'stage_id'            => $stageId,
            'estimated_value'     => $this->nullableDecimal($data['estimated_value'] ?? null),
            'expected_close_date' => filled($data['expected_close_date'] ?? '') ? $data['expected_close_date'] : null,
            'notes'               => filled($data['notes'] ?? '') ? $data['notes'] : null,
            'assigned_to'         => $this->nullableInt($data['assigned_to'] ?? null),
        ]);

        $this->syncCustomFieldValues($lead, $data['custom_fields'] ?? []);
        $this->logStageChange($lead, null, $stageId, $userId);

        return $lead;
    }

    public function update(Lead $lead, array $data, ?int $userId = null): Lead
    {
        $oldStageId = $lead->stage_id;
        $newStageId = $this->nullableInt($data['stage_id'] ?? null) ?? $lead->stage_id;

        $lead->update([
            'name'                => $data['name'],
            'company'             => filled($data['company'] ?? '') ? $data['company'] : null,
            'email'               => filled($data['email'] ?? '') ? $data['email'] : null,
            'phone'               => filled($data['phone'] ?? '') ? $data['phone'] : null,
            'source'              => filled($data['source'] ?? '') ? $data['source'] : null,
            'stage_id'            => $newStageId,
            'estimated_value'     => $this->nullableDecimal($data['estimated_value'] ?? null),
            'expected_close_date' => filled($data['expected_close_date'] ?? '') ? $data['expected_close_date'] : null,
            'notes'               => filled($data['notes'] ?? '') ? $data['notes'] : null,
            'assigned_to'         => $this->nullableInt($data['assigned_to'] ?? null),
        ]);

        $this->syncCustomFieldValues($lead, $data['custom_fields'] ?? []);
        $this->logStageChange($lead, $oldStageId, $newStageId, $userId);

        return $lead->fresh();
    }

    public function markLost(Lead $lead, ?string $reason = null, ?int $userId = null): Lead
    {
        $oldStageId = $lead->stage_id;
        $lostStage  = $this->stages->lostStage($lead->project);

        $lead->update([
            'stage_id'    => $lostStage?->id,
            'lost_reason' => filled($reason) ? $reason : null,
        ]);

        $this->logStageChange($lead, $oldStageId, $lostStage?->id, $userId);

        return $lead;
    }

    public function reopen(Lead $lead, ?int $userId = null): Lead
    {
        $oldStageId = $lead->stage_id;
        $openStage  = $this->stages->defaultOpenStage($lead->project);

        $lead->update([
            'stage_id'    => $openStage?->id,
            'lost_reason' => null,
        ]);

        $this->logStageChange($lead, $oldStageId, $openStage?->id, $userId);

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

            $lead->update([
                'stage_id'     => $wonStage?->id,
                'customer_id'  => $customer->id,
                'converted_at' => now(),
            ]);

            $this->logStageChange($lead, $oldStageId, $wonStage?->id, $userId);

            return $lead->fresh();
        });
    }

    public function moveStage(Lead $lead, int $stageId, ?int $userId = null): Lead
    {
        $oldStageId = $lead->stage_id;

        $lead->update(['stage_id' => $stageId]);

        $this->logStageChange($lead, $oldStageId, $stageId, $userId);

        return $lead;
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

    private function logStageChange(Lead $lead, ?int $fromStageId, ?int $toStageId, ?int $userId): void
    {
        if ($fromStageId === $toStageId) {
            return;
        }

        LeadStageLog::create([
            'lead_id'       => $lead->id,
            'project_id'    => $lead->project_id,
            'from_stage_id' => $fromStageId,
            'to_stage_id'   => $toStageId,
            'changed_by'    => $userId,
        ]);

        if ($toStageId !== null) {
            $toStage = LeadStage::find($toStageId);
            if ($toStage) {
                $this->stageAutomations->runForStageChange($lead, $toStage);
            }
        }
    }

    public function delete(Lead $lead): void
    {
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
