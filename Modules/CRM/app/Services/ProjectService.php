<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Project;

class ProjectService
{
    public function listForBusiness(Business $business): Collection
    {
        return Project::query()
            ->where('business_id', $business->id)
            ->withCount('leads')
            ->orderBy('name')
            ->get();
    }

    public function businessHasProjects(Business $business): bool
    {
        return Project::query()->where('business_id', $business->id)->exists();
    }

    public function create(Business $business, array $data): Project
    {
        return Project::create([
            'business_id' => $business->id,
            'name'        => $data['name'],
            'description' => filled($data['description'] ?? '') ? $data['description'] : null,
            'status'      => Project::STATUS_ACTIVE,
        ]);
    }

    public function update(Project $project, array $data): Project
    {
        $project->update([
            'name'        => $data['name'],
            'description' => filled($data['description'] ?? '') ? $data['description'] : null,
        ]);

        return $project->fresh();
    }

    public function archive(Project $project): Project
    {
        $project->update(['status' => Project::STATUS_ARCHIVED]);

        return $project;
    }

    public function reactivate(Project $project): Project
    {
        $project->update(['status' => Project::STATUS_ACTIVE]);

        return $project;
    }

    public function delete(Project $project): void
    {
        if ($project->leads()->exists()) {
            throw ValidationException::withMessages(['project' => 'Move or remove leads in this project before deleting it.']);
        }

        $project->delete();
    }

    public function projectForBusiness(Business $business, Project $project): ?Project
    {
        return $project->business_id === $business->id ? $project : null;
    }
}
