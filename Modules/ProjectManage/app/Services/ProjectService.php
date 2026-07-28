<?php

namespace Modules\ProjectManage\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\ProjectManage\Models\Project;

class ProjectService
{
    public function listForBusiness(Business $business): Collection
    {
        return Project::query()
            ->where('business_id', $business->id)
            ->withCount('tasks')
            ->orderBy('status')
            ->orderBy('name')
            ->get();
    }

    public function businessHasProjects(Business $business): bool
    {
        return Project::query()->where('business_id', $business->id)->exists();
    }

    public function create(Business $business, array $data, int $userId): Project
    {
        return Project::create([
            'business_id' => $business->id,
            'name'        => $data['name'],
            'description' => filled($data['description'] ?? '') ? $data['description'] : null,
            'status'      => $data['status'] ?? Project::STATUS_ACTIVE,
            'priority'    => $data['priority'] ?? Project::PRIORITY_NORMAL,
            'color'       => filled($data['color'] ?? '') ? $data['color'] : null,
            'start_date'  => filled($data['start_date'] ?? '') ? $data['start_date'] : null,
            'due_date'    => filled($data['due_date'] ?? '') ? $data['due_date'] : null,
            'budget'      => filled($data['budget'] ?? '') ? $data['budget'] : null,
            'client_name' => filled($data['client_name'] ?? '') ? $data['client_name'] : null,
            'created_by'  => $userId,
        ]);
    }

    public function update(Project $project, array $data): Project
    {
        $project->update([
            'name'        => $data['name'],
            'description' => filled($data['description'] ?? '') ? $data['description'] : null,
            'status'      => $data['status'] ?? $project->status,
            'priority'    => $data['priority'] ?? $project->priority,
            'color'       => filled($data['color'] ?? '') ? $data['color'] : null,
            'start_date'  => filled($data['start_date'] ?? '') ? $data['start_date'] : null,
            'due_date'    => filled($data['due_date'] ?? '') ? $data['due_date'] : null,
            'budget'      => filled($data['budget'] ?? '') ? $data['budget'] : null,
            'client_name' => filled($data['client_name'] ?? '') ? $data['client_name'] : null,
        ]);

        return $project->fresh();
    }

    public function updateStatus(Project $project, string $status): Project
    {
        $project->update(['status' => $status]);

        return $project;
    }

    public function delete(Project $project): void
    {
        if ($project->tasks()->exists()) {
            throw ValidationException::withMessages(['project' => 'Move or remove tasks in this project before deleting it.']);
        }

        $project->delete();
    }

    public function projectForBusiness(Business $business, Project $project): ?Project
    {
        return (int) $project->business_id === (int) $business->id ? $project : null;
    }
}
