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
use Modules\Pos\Services\PosNotificationService;
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
                'id'         => (int) $m->user->id,
                'name'       => $m->user->name,
                'email'      => $m->user->email,
                'avatar_url' => $m->user->avatarUrl(),
                'role'       => (string) $m->role,
            ]);

        if ($business->user) {
            $users->prepend([
                'id'         => (int) $business->user->id,
                'name'       => $business->user->name,
                'email'      => $business->user->email,
                'avatar_url' => $business->user->avatarUrl(),
                'role'       => 'owner',
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
                'avatar_url'  => $u->avatarUrl(),
                'role'        => $roles[$u->id] ?? 'former',
                'open_tasks'  => (int) ($counts[$u->id]->open ?? 0),
                'total_tasks' => (int) ($counts[$u->id]->total ?? 0),
                'added_at'    => $u->pivot->created_at?->toDateTimeString(),
            ]);
    }

    /**
     * A non-archived project of the business that the user is on the team of or has a task in
     * (the My Projects scope), loaded for the project-detail view; null when out of scope.
     */
    public function findForAssignee(Business $business, int $projectId, int $userId): ?Project
    {
        return Project::query()
            ->where('business_id', $business->id)
            ->where('id', $projectId)
            ->where('status', '!=', Project::STATUS_ARCHIVED)
            ->where(fn ($q) => $q->whereHas('members', fn ($m) => $m->where('users.id', $userId))
                                 ->orWhereHas('tasks.assignees', fn ($a) => $a->where('users.id', $userId)))
            ->withCount('members')
            ->with(['customer', 'branch', 'department', 'property', 'employee', 'modification', 'rental', 'imageFile', 'createdBy'])
            ->first();
    }

    /**
     * The user's own task counts in a project.
     *
     * @return array{total:int,open:int,overdue:int,done:int}
     */
    public function userTaskStats(Project $project, int $userId): array
    {
        $done = Task::STATUS_DONE;
        $row  = DB::table('pm_task_assignees as a')
            ->join('pm_tasks as t', 't.id', '=', 'a.task_id')
            ->where('a.user_id', $userId)
            ->where('t.project_id', $project->id)
            ->selectRaw(
                'count(*) as total,
                 sum(case when t.status = ? then 0 else 1 end) as open,
                 sum(case when t.status <> ? and t.due_date is not null and t.due_date < ? then 1 else 0 end) as overdue',
                [$done, $done, now()->toDateString()]
            )
            ->first();

        $total = (int) ($row->total ?? 0);
        $open  = (int) ($row->open ?? 0);

        return ['total' => $total, 'open' => $open, 'overdue' => (int) ($row->overdue ?? 0), 'done' => $total - $open];
    }

    /**
     * Team-member profile card: who they are (business role, HR job title / department / photo)
     * and the projects they work on with their task counts there.
     *
     * Managers ($canManage) see any business user and all their projects; everyone else sees only
     * teammates who share a (non-archived) project with them, limited to those shared projects.
     * Returns null when the viewer may not see this user.
     */
    public function memberProfile(Business $business, int $userId, int $viewerId, bool $canManage): ?array
    {
        $user = User::find($userId);
        if (! $user) {
            return null;
        }

        // Projects of this business where the user is on the team or has a task.
        $involving = fn (int $uid) => Project::query()
            ->where('business_id', $business->id)
            ->where(fn ($q) => $q->whereHas('members', fn ($m) => $m->where('users.id', $uid))
                                 ->orWhereHas('tasks.assignees', fn ($a) => $a->where('users.id', $uid)));

        $query = $involving($userId);
        if (! $canManage) {
            $query->where('status', '!=', Project::STATUS_ARCHIVED)
                  ->whereIn('id', $involving($viewerId)->where('status', '!=', Project::STATUS_ARCHIVED)->select('id'));
        }
        $projects = $query->orderBy('name')->get();

        $role = $this->businessUsers($business)->firstWhere('id', $userId)['role'] ?? null;
        $isMe = $userId === $viewerId;

        if (! $isMe && ($canManage ? ($role === null && $projects->isEmpty()) : $projects->isEmpty())) {
            return null;
        }

        $done   = Task::STATUS_DONE;
        $counts = DB::table('pm_task_assignees as a')
            ->join('pm_tasks as t', 't.id', '=', 'a.task_id')
            ->where('a.user_id', $userId)
            ->whereIn('t.project_id', $projects->pluck('id'))
            ->selectRaw(
                't.project_id, count(*) as total,
                 sum(case when t.status = ? then 0 else 1 end) as open,
                 sum(case when t.status <> ? and t.due_date is not null and t.due_date < ? then 1 else 0 end) as overdue',
                [$done, $done, now()->toDateString()]
            )
            ->groupBy('t.project_id')
            ->get()
            ->keyBy('project_id');

        $memberOf = DB::table('pm_project_members')->where('user_id', $userId)->pluck('project_id')->map(fn ($id) => (int) $id)->all();

        $employee = Employee::query()
            ->where('business_id', $business->id)
            ->where('user_id', $userId)
            ->with(['jobTitle', 'department'])
            ->first();

        $name = (string) $user->name;

        return [
            'id'           => (int) $user->id,
            'name'         => $name,
            'initial'      => mb_strtoupper(mb_substr(trim($name), 0, 1)) ?: '?',
            'email'        => $user->email,
            'avatar_url'   => $user->avatarUrl(),
            'role'         => $role ?? 'former',
            'is_me'        => $isMe,
            'last_seen_at' => $user->last_seen_at?->toDateTimeString(),
            'employee'     => $employee ? [
                'employee_id'     => $employee->employee_id,
                'job_title'       => $employee->jobTitle?->name,
                'department'      => $employee->department?->name,
                'date_of_joining' => $employee->date_of_joining?->toDateString(),
                'photo_url'       => $employee->profilePhotoUrl(),
                // Contact number is shown to project managers only.
                'phone'           => $canManage ? $employee->phone_number : null,
            ] : null,
            'stats'        => [
                'projects' => $projects->count(),
                'total'    => (int) $counts->sum('total'),
                'open'     => (int) $counts->sum('open'),
                'overdue'  => (int) $counts->sum('overdue'),
                'done'     => (int) ($counts->sum('total') - $counts->sum('open')),
            ],
            'projects'     => $projects->map(fn (Project $p) => [
                'id'          => (int) $p->id,
                'name'        => $p->name,
                'color'       => $p->color,
                'status'      => $p->status,
                'is_member'   => in_array((int) $p->id, $memberOf, true),
                'open_tasks'  => (int) ($counts[$p->id]->open ?? 0),
                'total_tasks' => (int) ($counts[$p->id]->total ?? 0),
            ])->values(),
        ];
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

        $changes = $project->members()->syncWithoutDetaching(
            collect($userIds)->mapWithKeys(fn ($id) => [(int) $id => ['added_by' => $addedBy]])->all()
        );

        // Only people who were not already on the team; a failed notification must not undo the add.
        if ($added = $changes['attached'] ?? []) {
            try {
                app(PosNotificationService::class)->notifyProjectMembersAdded($project, $added, $addedBy);
            } catch (\Throwable $e) {
                report($e);
            }
        }
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
