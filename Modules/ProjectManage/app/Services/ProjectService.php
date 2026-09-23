<?php

namespace Modules\ProjectManage\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Property;
use Modules\Account\Models\Rental;
use Modules\Business\Models\Branch;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Models\Employee;
use Modules\Modification\Models\Modification;
use Modules\ProjectManage\Models\Project;

class ProjectService
{
    /**
     * @param array{status?: string, project_type?: string, search?: string} $filters
     */
    public function listForBusiness(Business $business, array $filters = []): Collection
    {
        $query = Project::query()
            ->where('business_id', $business->id)
            ->withCount('tasks')
            ->with(['customer', 'branch', 'department', 'property', 'employee', 'modification', 'rental', 'imageFile']);

        if (filled($filters['status'] ?? '')) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['project_type'] ?? '')) {
            $query->where('project_type', $filters['project_type']);
        }

        if (filled($filters['search'] ?? '')) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('client_name', 'like', "%{$term}%");
            });
        }

        return $query->orderBy('status')->orderBy('name')->get();
    }

    /**
     * Lists of assignable targets (branch/department/property/employee/modification/rental)
     * for the "Assign To" fields, mirroring PosExpenseBillAssignmentApiController's targets.
     *
     * @return array<string, Collection>
     */
    public function assignableTargets(Business $business): array
    {
        $safe = function (callable $fn): Collection {
            try {
                return $fn() ?? collect();
            } catch (\Throwable) {
                return collect();
            }
        };

        return [
            'branches' => $safe(fn () => Branch::query()
                ->where('business_id', $business->id)
                ->orderBy('name')
                ->get()
                ->map(fn ($b) => (object) ['id' => $b->id, 'name' => $b->name])),

            'departments' => $safe(function () use ($business) {
                if (!Schema::hasTable('hr_departments')) {
                    return collect();
                }
                return Department::query()->where('business_id', $business->id)->orderBy('name')->get()
                    ->map(fn ($d) => (object) ['id' => $d->id, 'name' => $d->name]);
            }),

            'properties' => $safe(function () use ($business) {
                if (!Schema::hasTable('properties')) {
                    return collect();
                }
                return Property::query()->where('business_id', $business->id)->orderBy('property_name')->get()
                    ->map(fn ($p) => (object) ['id' => $p->id, 'name' => $p->property_name.' · '.$p->property_type]);
            }),

            'employees' => $safe(function () use ($business) {
                if (!Schema::hasTable('hr_employees')) {
                    return collect();
                }
                return Employee::query()->where('business_id', $business->id)->orderBy('full_name')->get()
                    ->map(fn ($e) => (object) ['id' => $e->id, 'name' => $e->full_name.($e->employee_id ? '  #'.$e->employee_id : '')]);
            }),

            'modifications' => $safe(function () use ($business) {
                if (!Schema::hasTable('modifications')) {
                    return collect();
                }
                return Modification::query()->where('business_id', $business->id)->orderBy('name')->get()
                    ->map(fn ($m) => (object) ['id' => $m->id, 'name' => $m->name]);
            }),

            'rentals' => $safe(function () use ($business) {
                if (!Schema::hasTable('rentals')) {
                    return collect();
                }
                return Rental::query()->where('business_id', $business->id)->orderBy('property_type')->get()
                    ->map(fn ($r) => (object) ['id' => $r->id, 'name' => $r->property_type.($r->purpose ? '  ·  '.$r->purpose : '')]);
            }),
        ];
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
