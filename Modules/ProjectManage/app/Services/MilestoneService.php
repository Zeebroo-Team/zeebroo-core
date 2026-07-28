<?php

namespace Modules\ProjectManage\Services;

use Illuminate\Support\Collection;
use Modules\ProjectManage\Models\Milestone;
use Modules\ProjectManage\Models\Project;

class MilestoneService
{
    public function listForProject(Project $project): Collection
    {
        return Milestone::query()
            ->where('project_id', $project->id)
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->get();
    }

    public function create(Project $project, array $data): Milestone
    {
        return Milestone::create([
            'project_id'  => $project->id,
            'name'        => $data['name'],
            'description' => filled($data['description'] ?? '') ? $data['description'] : null,
            'due_date'    => filled($data['due_date'] ?? '') ? $data['due_date'] : null,
            'status'      => Milestone::STATUS_PENDING,
        ]);
    }

    public function update(Milestone $milestone, array $data): Milestone
    {
        $milestone->update([
            'name'        => $data['name'],
            'description' => filled($data['description'] ?? '') ? $data['description'] : null,
            'due_date'    => filled($data['due_date'] ?? '') ? $data['due_date'] : null,
        ]);

        return $milestone->fresh();
    }

    public function complete(Milestone $milestone): Milestone
    {
        $milestone->update([
            'status'       => Milestone::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return $milestone;
    }

    public function reopen(Milestone $milestone): Milestone
    {
        $milestone->update([
            'status'       => Milestone::STATUS_PENDING,
            'completed_at' => null,
        ]);

        return $milestone;
    }

    public function delete(Milestone $milestone): void
    {
        $milestone->delete();
    }
}
