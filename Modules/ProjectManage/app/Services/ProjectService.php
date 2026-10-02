<?php

namespace Modules\ProjectManage\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Property;
use Modules\Account\Models\Rental;
use Modules\Business\Models\Branch;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessMember;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Models\Employee;
use Modules\Modification\Models\Modification;
use Modules\ProjectManage\Models\Project;
use Modules\ProjectManage\Models\Task;

class ProjectService
{
    /**
     * @param array{status?: string, project_type?: string, search?: string} $filters
     */
    public function listForBusiness(Business $business, array $filters = []): Collection
    {
        $query = Project::query()
            ->where('business_id', $business->id)
            ->withCount(['tasks', 'members'])
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

    /**
     * Users who can be added to a project team: the business owner plus active members.
     *
     * @return Collection<int, array{id:int,name:string,email:?string,role:string}>
     */
    public function businessUsers(Business $business): Collection
    {
        $users = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->with('user')
            ->get()
            ->filter(fn (BusinessMember $m) => $m->user)
            ->map(fn (BusinessMember $m) => [
                'id'    => (int) $m->user->id,
                'name'  => $m->user->name,
                'email' => $m->user->email,
                'role'  => (string) $m->role,
            ]);

        if ($business->user) {
            $users->prepend([
                'id'    => (int) $business->user->id,
                'name'  => $business->user->name,
                'email' => $business->user->email,
                'role'  => 'owner',
            ]);
        }

        return $users->unique('id')->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /**
     * The project team, each with their task counts in this project.
     *
     * @return Collection<int, array{id:int,name:string,email:?string,role:string,open_tasks:int,total_tasks:int,added_at:?string}>
     */
    public function membersForProject(Project $project): Collection
    {
        $roles = $this->businessUsers($project->business)->pluck('role', 'id');

        $counts = DB::table('pm_task_assignees as a')
            ->join('pm_tasks as t', 't.id', '=', 'a.task_id')
            ->where('t.project_id', $project->id)
            ->selectRaw('a.user_id, count(*) as total, sum(case when t.status = ? then 0 else 1 end) as open', [Task::STATUS_DONE])
            ->groupBy('a.user_id')
            ->get()
            ->keyBy('user_id');

        return $project->members()
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id'          => (int) $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'role'        => $roles[$u->id] ?? 'former',
                'open_tasks'  => (int) ($counts[$u->id]->open ?? 0),
                'total_tasks' => (int) ($counts[$u->id]->total ?? 0),
                'added_at'    => $u->pivot->created_at?->toDateTimeString(),
            ]);
    }

    /**
     * Adds business users to the project team; ids outside the business are rejected.
     *
     * @param int[] $userIds
     */
    public function addMembers(Project $project, array $userIds, ?int $addedBy): void
    {
        $allowed = $this->businessUsers($project->business)->pluck('id')->all();
        $invalid = array_diff(array_map('intval', $userIds), $allowed);

        if ($invalid) {
            throw ValidationException::withMessages(['user_ids' => 'Only users of this business can be added to the project.']);
        }

        $project->members()->syncWithoutDetaching(
            collect($userIds)->mapWithKeys(fn ($id) => [(int) $id => ['added_by' => $addedBy]])->all()
        );
    }

    /** Removes a user from the team and from every task of theirs in this project. Returns how many tasks they were removed from. */
    public function removeMember(Project $project, int $userId): int
    {
        return DB::transaction(function () use ($project, $userId) {
            $tasks = Task::query()
                ->where('project_id', $project->id)
                ->whereHas('assignees', fn ($q) => $q->where('users.id', $userId))
                ->with('assignees')
                ->get();

            foreach ($tasks as $task) {
                $task->syncAssignees($task->assignees->pluck('id')->reject(fn ($id) => (int) $id === $userId)->all());
            }

            $project->members()->detach($userId);

            return $tasks->count();
        });
    }

    public function businessHasProjects(Business $business): bool
    {
        return Project::query()->where('business_id', $business->id)->exists();
    }

    public function create(Business $business, array $data, int $userId): Project
    {
        $project = Project::create(array_merge([
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

        // The creator starts on the project team.
        if ($userId > 0) {
            $project->members()->attach($userId, ['added_by' => $userId]);
        }

        return $project;
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
