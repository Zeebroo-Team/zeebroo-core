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
            ->with(['customer', 'branch', 'department', 'property', 'employee', 'modification', 'rental', 'imageFile'])
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
        return Project::create(array_merge([
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
        ], $this->assignmentAttributes($data)));
    }

    public function update(Project $project, array $data): Project
    {
        $project->update(array_merge([
            'name'        => $data['name'],
            'description' => filled($data['description'] ?? '') ? $data['description'] : null,
            'status'      => $data['status'] ?? $project->status,
            'priority'    => $data['priority'] ?? $project->priority,
            'color'       => filled($data['color'] ?? '') ? $data['color'] : null,
            'start_date'  => filled($data['start_date'] ?? '') ? $data['start_date'] : null,
            'due_date'    => filled($data['due_date'] ?? '') ? $data['due_date'] : null,
            'budget'      => filled($data['budget'] ?? '') ? $data['budget'] : null,
            'client_name' => filled($data['client_name'] ?? '') ? $data['client_name'] : null,
        ], $this->assignmentAttributes($data)));

        return $project->fresh();
    }

    /**
     * Normalizes project-type / customer / in-house-assignment / image fields so
     * only the columns matching the chosen project_type and assignment_type are kept.
     */
    private function assignmentAttributes(array $data): array
    {
        $projectType = in_array($data['project_type'] ?? null, [Project::TYPE_IN_HOUSE, Project::TYPE_CUSTOMER], true)
            ? $data['project_type']
            : Project::TYPE_IN_HOUSE;

        $isCustomer = $projectType === Project::TYPE_CUSTOMER;

        $assignmentType = $isCustomer
            ? Project::ASSIGNMENT_NONE
            : (in_array($data['assignment_type'] ?? null, [
                Project::ASSIGNMENT_NONE, Project::ASSIGNMENT_BRANCH, Project::ASSIGNMENT_DEPARTMENT,
                Project::ASSIGNMENT_PROPERTY, Project::ASSIGNMENT_EMPLOYEE, Project::ASSIGNMENT_MODIFICATION,
                Project::ASSIGNMENT_RENTAL, Project::ASSIGNMENT_OTHER,
            ], true) ? $data['assignment_type'] : Project::ASSIGNMENT_NONE);

        return [
            'project_type'          => $projectType,
            'customer_id'           => $isCustomer ? ($data['customer_id'] ?? null) : null,
            'assignment_type'       => $assignmentType,
            'branch_id'             => $assignmentType === Project::ASSIGNMENT_BRANCH       ? ($data['branch_id'] ?? null)       : null,
            'department_id'         => $assignmentType === Project::ASSIGNMENT_DEPARTMENT   ? ($data['department_id'] ?? null)   : null,
            'property_id'           => $assignmentType === Project::ASSIGNMENT_PROPERTY     ? ($data['property_id'] ?? null)     : null,
            'employee_id'           => $assignmentType === Project::ASSIGNMENT_EMPLOYEE     ? ($data['employee_id'] ?? null)     : null,
            'modification_id'       => $assignmentType === Project::ASSIGNMENT_MODIFICATION ? ($data['modification_id'] ?? null) : null,
            'rental_id'             => $assignmentType === Project::ASSIGNMENT_RENTAL       ? ($data['rental_id'] ?? null)       : null,
            'assignment_reference'  => $assignmentType === Project::ASSIGNMENT_OTHER ? (filled($data['assignment_reference'] ?? '') ? $data['assignment_reference'] : null) : null,
            'file_manager_file_id'  => $data['file_manager_file_id'] ?? null,
        ];
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
