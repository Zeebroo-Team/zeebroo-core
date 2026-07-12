<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\Project;

class LeadStageService
{
    private const DEFAULTS = [
        ['name' => 'New',         'color' => '#64748b'],
        ['name' => 'Contacted',   'color' => '#3b82f6'],
        ['name' => 'Qualified',   'color' => '#8b5cf6'],
        ['name' => 'Proposal',    'color' => '#f59e0b'],
        ['name' => 'Negotiation', 'color' => '#f97316'],
        ['name' => 'Won',         'color' => '#10b981', 'is_won' => true],
        ['name' => 'Lost',        'color' => '#ef4444', 'is_lost' => true],
    ];

    public function listForProject(Project $project): Collection
    {
        $this->ensureDefaults($project);

        return LeadStage::query()
            ->where('project_id', $project->id)
            ->orderBy('sort_order')
            ->get();
    }

    public function ensureDefaults(Project $project): void
    {
        if (LeadStage::query()->where('project_id', $project->id)->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $i => $stage) {
            LeadStage::create($stage + [
                'project_id' => $project->id,
                'sort_order' => $i + 1,
            ]);
        }
    }

    public function create(Project $project, array $data): LeadStage
    {
        $nextOrder = (int) LeadStage::query()->where('project_id', $project->id)->max('sort_order') + 1;

        return LeadStage::create([
            'project_id' => $project->id,
            'name'       => $data['name'],
            'color'      => filled($data['color'] ?? '') ? $data['color'] : '#64748b',
            'is_won'     => (bool) ($data['is_won'] ?? false),
            'is_lost'    => (bool) ($data['is_lost'] ?? false),
            'sort_order' => $nextOrder,
        ]);
    }

    public function update(LeadStage $stage, array $data): LeadStage
    {
        $stage->update([
            'name'    => $data['name'],
            'color'   => filled($data['color'] ?? '') ? $data['color'] : $stage->color,
            'is_won'  => (bool) ($data['is_won'] ?? false),
            'is_lost' => (bool) ($data['is_lost'] ?? false),
        ]);

        return $stage->fresh();
    }

    public function delete(LeadStage $stage): void
    {
        if ($stage->leads()->exists()) {
            throw ValidationException::withMessages(['stage' => 'Move or remove leads in this stage before deleting it.']);
        }

        $stage->delete();
    }

    public function reorder(Project $project, array $ids): void
    {
        foreach ($ids as $order => $id) {
            LeadStage::query()
                ->where('id', $id)
                ->where('project_id', $project->id)
                ->update(['sort_order' => $order + 1]);
        }
    }

    public function stageForProject(Project $project, LeadStage $stage): ?LeadStage
    {
        return $stage->project_id === $project->id ? $stage : null;
    }

    public function defaultOpenStage(Project $project): ?LeadStage
    {
        $this->ensureDefaults($project);

        return LeadStage::query()
            ->where('project_id', $project->id)
            ->where('is_won', false)
            ->where('is_lost', false)
            ->orderBy('sort_order')
            ->first();
    }

    public function wonStage(Project $project): ?LeadStage
    {
        $this->ensureDefaults($project);

        return LeadStage::query()
            ->where('project_id', $project->id)
            ->where('is_won', true)
            ->orderBy('sort_order')
            ->first();
    }

    public function lostStage(Project $project): ?LeadStage
    {
        $this->ensureDefaults($project);

        return LeadStage::query()
            ->where('project_id', $project->id)
            ->where('is_lost', true)
            ->orderBy('sort_order')
            ->first();
    }
}
