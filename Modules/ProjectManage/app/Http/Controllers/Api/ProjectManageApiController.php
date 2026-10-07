<?php

namespace Modules\ProjectManage\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\ProjectManage\Models\Milestone;
use Modules\ProjectManage\Models\Project;
use Modules\ProjectManage\Models\Task;
use Modules\ProjectManage\Models\TaskStatus;
use Modules\ProjectManage\Services\MilestoneService;
use Modules\ProjectManage\Models\TaskAttachment;
use Modules\ProjectManage\Models\TaskComment;
use Modules\ProjectManage\Models\TaskDeleteRequest;
use Modules\ProjectManage\Services\ProjectService;
use Modules\ProjectManage\Services\TaskAttachmentService;
use Modules\ProjectManage\Services\TaskDeleteRequestService;
use Modules\ProjectManage\Services\TaskService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class ProjectManageApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly ProjectService   $projects,
        private readonly TaskService      $tasks,
        private readonly MilestoneService $milestones,
        private readonly TaskAttachmentService $attachments,
        private readonly TaskDeleteRequestService $deleteRequests,
    ) {}

    // ── Projects ─────────────────────────────────────────────────────────────

    public function projectIndex(Request $request): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);

        // Legacy desktop clients send ?filter=<status|all>; map it onto the service's filters array.
        $status = (string) $request->query('status', $request->query('filter', 'all'));
        $list   = $this->projects->listForBusiness($business, [
            'status'       => $status === 'all' ? '' : $status,
            'project_type' => (string) $request->query('project_type', ''),
            'search'       => (string) $request->query('search', ''),
        ]);

        return response()->json(['data' => $list->map(fn ($p) => $this->fmtProject($p))]);
    }

    public function projectStore(Request $request): JsonResponse
    {
        $business  = $this->manageBusinessOrAbort($request);
        $validated = $request->validate(array_merge([
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'client_name' => 'nullable|string|max:120',
            'priority'    => 'nullable|in:low,normal,high',
            'color'       => 'nullable|string|max:20',
            'start_date'  => 'nullable|date',
            'due_date'    => 'nullable|date',
            'budget'      => 'nullable|numeric|min:0',
        ], $this->assignmentValidationRules($business)));

        $project = $this->projects->create($business, $validated, $request->user()?->id ?? 0);

        return response()->json(['data' => $this->fmtProject($project)], 201);
    }

    public function projectUpdate(Request $request, int $id): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate(array_merge([
            'name'        => 'sometimes|string|max:150',
            'description' => 'nullable|string|max:2000',
            'client_name' => 'nullable|string|max:120',
            'priority'    => 'nullable|in:low,normal,high',
            'status'      => 'nullable|in:active,on_hold,completed,archived',
            'color'       => 'nullable|string|max:20',
            'start_date'  => 'nullable|date',
            'due_date'    => 'nullable|date',
            'budget'      => 'nullable|numeric|min:0',
        ], $this->assignmentValidationRules($business)));

        $project = $this->projects->update($project, $validated);

        return response()->json(['data' => $this->fmtProject($project->fresh())]);
    }

    /**
     * Validation rules for the project-type / customer / in-house-assignment / image fields,
     * shared by projectStore() and projectUpdate(). Mirrors the assignment pattern used by
     * PosExpenseBillApiController (branch/department/property/employee/modification/rental)
     * plus a "customer" project type and an "other" free-text reference.
     */
    private function assignmentValidationRules($business): array
    {
        $deptRule = ['nullable', 'integer'];
        if (Schema::hasTable('hr_departments')) {
            $deptRule[] = Rule::exists('hr_departments', 'id')->where(fn ($q) => $q->where('business_id', $business->id));
        }

        return [
            'project_type'          => ['nullable', Rule::in(['in_house', 'customer'])],
            'customer_id'           => ['nullable', 'integer', Rule::exists('pos_customers', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'assignment_type'       => ['nullable', Rule::in(['none', 'branch', 'department', 'property', 'employee', 'modification', 'rental', 'other'])],
            'branch_id'             => ['nullable', 'integer', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'department_id'         => $deptRule,
            'property_id'           => ['nullable', 'integer', Rule::exists('properties', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'employee_id'           => ['nullable', 'integer', Rule::exists('hr_employees', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'modification_id'       => ['nullable', 'integer', Rule::exists('modifications', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'rental_id'             => ['nullable', 'integer', Rule::exists('rentals', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'assignment_reference'  => ['nullable', 'string', 'max:255'],
            'file_manager_file_id'  => ['nullable', 'integer', Rule::exists('file_manager_files', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
        ];
    }

    public function projectDestroy(Request $request, int $id): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $id)->firstOrFail();

        try {
            $this->projects->delete($project);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json(['message' => 'Project deleted.']);
    }

    // ── Project members (team) ───────────────────────────────────────────────

    /** The project team plus the business users that can still be added. */
    public function memberIndex(Request $request, int $projectId): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $projectId)->firstOrFail();

        return response()->json($this->membersPayload($project));
    }

    /**
     * Team-member profile, for both the Projects tab (Manage All Projects — any business user)
     * and My Projects (Assigned Project Access — only teammates sharing a project with the caller).
     */
    public function memberProfile(Request $request, int $userId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $member   = $this->resolveMember($request, $business);
        $canManage = $member === null || $member->hasPermission('projects_access');
        if (! $canManage) {
            $this->abortUnlessPerm($request, $business, 'projects_assigned');
        }

        $profile = $this->projects->memberProfile($business, $userId, (int) $request->user()->id, $canManage);
        abort_unless($profile, 404, 'This person is not on any of your project teams.');

        return response()->json(['data' => $profile]);
    }

    public function memberStore(Request $request, int $projectId): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $projectId)->firstOrFail();

        $userIds = $request->validate([
            'user_ids'   => 'required|array|min:1|max:200',
            'user_ids.*' => 'integer',
        ])['user_ids'];

        try {
            $this->projects->addMembers($project, $userIds, $request->user()?->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json($this->membersPayload($project), 201);
    }

    public function memberDestroy(Request $request, int $projectId, int $userId): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $projectId)->firstOrFail();

        $unassigned = $this->projects->removeMember($project, $userId);

        return response()->json(['unassigned_tasks' => $unassigned] + $this->membersPayload($project));
    }

    // ── Board ────────────────────────────────────────────────────────────────

    public function board(Request $request, int $id): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $id)->firstOrFail();

        $columns = array_map(
            fn (array $col) => ['tasks' => $col['tasks']->map(fn ($t) => $this->fmtTask($t))->values()] + $col,
            $this->tasks->boardForProject($project),
        );

        return response()->json([
            'project' => $this->fmtProject($project),
            'columns' => $columns,
        ]);
    }

    // ── Task statuses (board columns) ────────────────────────────────────────

    public function statusIndex(Request $request, int $projectId): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $projectId)->firstOrFail();

        return response()->json(['data' => $this->tasks->statusesForProject($project)]);
    }

    public function statusStore(Request $request, int $projectId): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $projectId)->firstOrFail();

        $validated = $request->validate(TaskService::statusRules());
        $status    = $this->tasks->createStatus($project, $validated);

        return response()->json(['data' => $this->tasks->fmtStatus($status)], 201);
    }

    public function statusUpdate(Request $request, int $id): JsonResponse
    {
        $business  = $this->manageBusinessOrAbort($request);
        $status    = $this->resolveStatus($business, $id);
        $validated = $request->validate(TaskService::statusRules(partial: true));

        $status = $this->tasks->updateStatus($status, $validated);

        return response()->json(['data' => $this->tasks->fmtStatus($status)]);
    }

    public function statusDestroy(Request $request, int $id): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $status   = $this->resolveStatus($business, $id);

        $this->tasks->deleteStatus($status);

        return response()->json(['message' => 'Status deleted. Its tasks were moved to To Do.']);
    }

    // ── Tasks ────────────────────────────────────────────────────────────────

    public function taskIndex(Request $request, int $projectId): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $projectId)->firstOrFail();

        $filters = $request->only(['status', 'milestone_id', 'assigned_to', 'priority']);
        $tasks   = $this->tasks->listForProject($project, $filters);

        return response()->json(['data' => $tasks->map(fn ($t) => $this->fmtTask($t))]);
    }

    public function taskStore(Request $request, int $projectId): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $projectId)->firstOrFail();

        $validated = $request->validate(array_merge([
            'title'            => 'required|string|max:200',
            'description'      => 'nullable|string|max:5000',
            'status'           => ['nullable', Rule::in($this->tasks->statusKeysForProject($project))],
            'priority'         => 'nullable|in:low,normal,high',
            'milestone_id'     => $this->milestoneIdRule($project),
            'due_date'         => 'nullable|date',
            'estimated_hours'  => 'nullable|numeric|min:0',
        ], TaskService::assigneeRules($project)), TaskService::assigneeMessages());

        $task = $this->tasks->create($project, $validated);

        return response()->json(['data' => $this->fmtTask($task)], 201);
    }

    /** Replaces the task's assignees with assignee_ids (all must be project members; [] = unassigned). */
    public function taskAssign(Request $request, int $id): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $task     = $this->resolveTask($business, $id);

        $validated = $request->validate(
            TaskService::assigneeRules($task->project),
            TaskService::assigneeMessages(),
        );

        $task = $this->tasks->assign($task, TaskService::assigneeIdsFrom($validated) ?? []);

        return response()->json(['data' => $this->fmtTask($task)]);
    }

    public function taskStatus(Request $request, int $id): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $task     = $this->resolveTask($business, $id);

        $status = $request->validate([
            'status' => ['required', Rule::in($this->tasks->statusKeysForProject($task->project))],
        ])['status'];
        $task   = $this->tasks->moveStatus($task, $status);

        return response()->json(['data' => $this->fmtTask($task)]);
    }

    public function taskMilestone(Request $request, int $id): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $task     = $this->resolveTask($business, $id);

        $milestoneId = $request->validate([
            'milestone_id' => $this->milestoneIdRule($task->project),
        ])['milestone_id'] ?? null;

        $task = $this->tasks->moveMilestone($task, $milestoneId !== null ? (int) $milestoneId : null);

        return response()->json(['data' => $this->fmtTask($task)]);
    }

    public function taskComplete(Request $request, int $id): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $task     = $this->resolveTask($business, $id);
        $task     = $this->tasks->complete($task);

        return response()->json(['data' => $this->fmtTask($task)]);
    }

    public function taskReopen(Request $request, int $id): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $task     = $this->resolveTask($business, $id);
        $task     = $this->tasks->reopen($task);

        return response()->json(['data' => $this->fmtTask($task)]);
    }

    public function taskComment(Request $request, int $id): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);

        return $this->storeComment($request, $this->resolveTask($business, $id));
    }

    public function taskTime(Request $request, int $id): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $task     = $this->resolveTask($business, $id);

        $validated = $request->validate([
            'minutes'    => 'required|integer|min:1|max:9999',
            'logged_at'  => 'nullable|date',
            'note'       => 'nullable|string|max:255',
        ]);

        $log = $this->tasks->logTime($task, $request->user()?->id ?? 0, $validated);

        return response()->json(['data' => [
            'id'        => $log->id,
            'minutes'   => $log->minutes,
            'logged_at' => $log->logged_at?->toDateString(),
            'note'      => $log->note,
        ]], 201);
    }

    public function taskDestroy(Request $request, int $id): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $task     = $this->resolveTask($business, $id);
        $this->tasks->delete($task);

        return response()->json(['message' => 'Task deleted.']);
    }

    // ── Task attachments (Projects tab — any task of the business) ───────────

    public function taskAttachmentIndex(Request $request, int $id): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $task     = $this->resolveTask($business, $id);

        return response()->json(['data' => $this->attachments->listForTask($task)->map(fn ($a) => $this->attachments->fmt($a))]);
    }

    public function taskAttachmentStore(Request $request, int $id): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $task     = $this->resolveTask($business, $id);

        return $this->storeAttachments($request, $task);
    }

    public function taskAttachmentDownload(Request $request, int $id): StreamedResponse
    {
        $business = $this->manageBusinessOrAbort($request);

        return $this->attachments->download($this->resolveAttachment($business, $id));
    }

    public function taskAttachmentDestroy(Request $request, int $id): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $this->attachments->delete($this->resolveAttachment($business, $id));

        return response()->json(['message' => 'Attachment deleted.']);
    }

    public function myTasks(Request $request): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $filter = (string) $request->query('filter', 'open');

        $tasks = $this->tasks->listForBusiness($business, $filter);

        return response()->json(['data' => $tasks->map(fn ($t) => $this->fmtTask($t))]);
    }

    // ── My Projects (assigned access) ────────────────────────────────────────
    // Gated by projects_assigned instead of projects_access: the user only sees and
    // moves tasks assigned to them, never the rest of the business's projects.

    /** Tasks assigned to me (all statuses) + the projects they belong to, each with its board statuses. */
    public function myWork(Request $request): JsonResponse
    {
        $business = $this->assignedBusinessOrAbort($request);
        $userId   = (int) $request->user()->id;
        $work     = $this->tasks->assignedWorkForUser($business, $userId);

        return response()->json(['data' => [
            'tasks'    => $work['tasks']->map(fn ($t) => $this->fmtTask($t))->values(),
            'projects' => $work['projects']->map(fn ($p) => $this->fmtProject($p) + [
                'statuses'  => $this->tasks->statusesForProject($p),
                // Only team members may add tasks for themselves (see myWorkTaskStore).
                'is_member' => $p->members()->where('users.id', $userId)->exists(),
            ])->values(),
            // Teammates across these projects (Overview "Team" panel and project-card avatars).
            'team'     => $this->projects->teammatesForProjects($business, $work['projects'], $userId),
        ]]);
    }

    /** Creates a task assigned to me, in a (non-archived) project whose team I am on. */
    public function myWorkTaskStore(Request $request): JsonResponse
    {
        $business = $this->assignedBusinessOrAbort($request);
        $userId   = (int) $request->user()->id;

        $projectId = (int) $request->validate(['project_id' => 'required|integer'])['project_id'];
        $project   = Project::where('business_id', $business->id)
            ->where('id', $projectId)
            ->where('status', '!=', Project::STATUS_ARCHIVED)
            ->firstOrFail();
        abort_unless($project->members()->where('users.id', $userId)->exists(), 403, 'You can only add tasks to projects you are a member of.');

        $validated = $request->validate([
            'title'           => 'required|string|max:200',
            'description'     => 'nullable|string|max:5000',
            'status'          => ['nullable', Rule::in($this->tasks->statusKeysForProject($project))],
            'priority'        => 'nullable|in:low,normal,high',
            'due_date'        => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
        ], ['status.in' => 'This project does not have that status.']);

        $task = $this->tasks->createForSelf($project, $validated, $userId);

        return response()->json(['data' => $this->fmtTask($task)], 201);
    }

    /**
     * Full detail of a project I work on (team member or task assignee): the project fields,
     * its team with their task counts, milestones and my own task counts there.
     * The budget is shown to project managers only.
     */
    public function myWorkProjectShow(Request $request, int $id): JsonResponse
    {
        $business = $this->assignedBusinessOrAbort($request);
        $userId   = (int) $request->user()->id;
        $project  = $this->projects->findForAssignee($business, $id, $userId);
        abort_unless($project, 404, 'You are not working on this project.');

        $member    = $this->resolveMember($request, $business);
        $canManage = $member === null || $member->hasPermission('projects_access');

        $data = $this->fmtProject($project);
        if (! $canManage) {
            $data['budget'] = null;
        }

        return response()->json(['data' => $data + [
            'statuses'        => $this->tasks->statusesForProject($project),
            'is_member'       => $project->hasMember($userId),
            'created_by_name' => $project->createdBy?->name,
            'members'         => $this->projects->membersForProject($project)->values(),
            'milestones'      => $this->milestones->listForProject($project)->map(fn ($m) => $this->fmtMilestone($m))->values(),
            'my_stats'        => $this->projects->userTaskStats($project, $userId),
        ]]);
    }

    /** Full detail of a task assigned to me: the task plus its comments and time logs. */
    public function myWorkTaskShow(Request $request, int $id): JsonResponse
    {
        $business = $this->assignedBusinessOrAbort($request);
        $task     = $this->resolveTask($business, $id);
        abort_unless($this->tasks->isAssignee($task, (int) $request->user()->id), 403, 'You can only view tasks assigned to you.');

        $task->loadMissing(['assignees', 'milestone', 'pendingDeleteRequest']);

        return response()->json(['data' => $this->fmtTask($task) + $this->tasks->activityForTask($task)]);
    }

    /** Status change (kanban drag & drop, complete / reopen) for a task assigned to me. */
    public function myWorkTaskStatus(Request $request, int $id): JsonResponse
    {
        $business = $this->assignedBusinessOrAbort($request);
        $task     = $this->resolveTask($business, $id);
        abort_unless($this->tasks->isAssignee($task, (int) $request->user()->id), 403, 'You can only update tasks assigned to you.');

        $status = $request->validate([
            'status' => ['required', Rule::in($this->tasks->statusKeysForProject($task->project))],
        ], ['status.in' => 'This project does not have that status.'])['status'];

        $task = $this->tasks->moveStatus($task, $status);

        return response()->json(['data' => $this->fmtTask($task->fresh(['assignees', 'milestone', 'project', 'pendingDeleteRequest']))]);
    }

    /** Comment (or reply, with parent_id) on a task assigned to me. */
    public function myWorkTaskComment(Request $request, int $id): JsonResponse
    {
        $business = $this->assignedBusinessOrAbort($request);
        $task     = $this->resolveTask($business, $id);
        abort_unless($this->tasks->isAssignee($task, (int) $request->user()->id), 403, 'You can only comment on tasks assigned to you.');

        return $this->storeComment($request, $task);
    }

    /** The task owner deletes a task outright; anyone else sends a delete request instead. */
    public function myWorkTaskDestroy(Request $request, int $id): JsonResponse
    {
        $business = $this->assignedBusinessOrAbort($request);
        $task     = $this->resolveTask($business, $id);
        abort_unless($task->isOwnedBy((int) $request->user()->id), 403, 'Only the task owner can delete this task — send a delete request instead.');

        $this->tasks->delete($task);

        return response()->json(['message' => 'Task deleted.']);
    }

    /** Asks the owner of a task assigned to me to delete it (they get a notification). */
    public function myWorkTaskDeleteRequest(Request $request, int $id): JsonResponse
    {
        $task   = $this->resolveMyTask($request, $id);
        $reason = $request->validate(['reason' => 'nullable|string|max:500'])['reason'] ?? null;

        $deleteRequest = $this->deleteRequests->request($task, (int) $request->user()->id, $reason);

        return response()->json([
            'message' => 'Delete request sent to the task owner.',
            'data'    => $this->deleteRequests->fmt($deleteRequest->load(['task.project', 'task.milestone', 'requester'])),
        ], 201);
    }

    /** Delete requests waiting for my decision (tasks I own). */
    public function myWorkDeleteRequestIndex(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $list     = $this->deleteRequests->pendingForOwner($business, (int) $request->user()->id);

        return response()->json(['data' => $list->map(fn ($r) => $this->deleteRequests->fmt($r))->values()]);
    }

    /** One request addressed to me — any status, so a stale notification still shows what happened. */
    public function myWorkDeleteRequestShow(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->deleteRequests->fmt($this->resolveDeleteRequest($request, $id))]);
    }

    public function myWorkDeleteRequestApprove(Request $request, int $id): JsonResponse
    {
        $this->deleteRequests->approve($this->resolveDeleteRequest($request, $id));

        return response()->json(['message' => 'Request approved — the task was deleted.']);
    }

    public function myWorkDeleteRequestReject(Request $request, int $id): JsonResponse
    {
        $deleteRequest = $this->deleteRequests->reject($this->resolveDeleteRequest($request, $id));

        return response()->json(['message' => 'Request rejected.', 'data' => $this->deleteRequests->fmt($deleteRequest)]);
    }

    /** Files of a task assigned to me (the detail endpoint also returns them). */
    public function myWorkAttachmentIndex(Request $request, int $id): JsonResponse
    {
        $task = $this->resolveMyTask($request, $id);

        return response()->json(['data' => $this->attachments->listForTask($task)->map(fn ($a) => $this->attachments->fmt($a))]);
    }

    public function myWorkAttachmentStore(Request $request, int $id): JsonResponse
    {
        return $this->storeAttachments($request, $this->resolveMyTask($request, $id));
    }

    /** Files (images, PDFs…) for a comment I just posted; sent right after the comment itself. */
    public function myWorkCommentAttachmentStore(Request $request, int $id): JsonResponse
    {
        $comment = TaskComment::findOrFail($id);
        $task    = $this->resolveMyTask($request, (int) $comment->task_id);
        abort_unless((int) $comment->user_id === (int) $request->user()->id, 403, 'You can only attach files to your own comments.');

        return $this->storeAttachments($request, $task, $comment);
    }

    public function myWorkAttachmentDownload(Request $request, int $id): StreamedResponse
    {
        $business   = $this->assignedBusinessOrAbort($request);
        $attachment = $this->resolveAttachment($business, $id);
        abort_unless($this->tasks->isAssignee($attachment->task, (int) $request->user()->id), 403, 'You can only download files of tasks assigned to you.');

        return $this->attachments->download($attachment);
    }

    /** Assignees may only remove files they uploaded themselves. */
    public function myWorkAttachmentDestroy(Request $request, int $id): JsonResponse
    {
        $business   = $this->assignedBusinessOrAbort($request);
        $attachment = $this->resolveAttachment($business, $id);
        $userId     = (int) $request->user()->id;
        abort_unless($this->tasks->isAssignee($attachment->task, $userId), 403, 'You can only manage files of tasks assigned to you.');
        abort_unless((int) $attachment->user_id === $userId, 403, 'You can only delete files you uploaded.');

        $this->attachments->delete($attachment);

        return response()->json(['message' => 'Attachment deleted.']);
    }

    // ── Milestones ───────────────────────────────────────────────────────────

    public function milestoneIndex(Request $request, int $projectId): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $projectId)->firstOrFail();

        $list = $this->milestones->listForProject($project);

        return response()->json(['data' => $list->map(fn ($m) => $this->fmtMilestone($m))]);
    }

    public function milestoneStore(Request $request, int $projectId): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $projectId)->firstOrFail();

        $validated = $request->validate(MilestoneService::rules());

        $milestone = $this->milestones->create($project, $validated);

        return response()->json(['data' => $this->fmtMilestone($milestone)], 201);
    }

    public function milestoneUpdate(Request $request, int $id): JsonResponse
    {
        $business  = $this->manageBusinessOrAbort($request);
        $milestone = $this->resolveMilestone($business, $id);

        $validated = $request->validate(MilestoneService::rules(partial: true));
        $milestone = $this->milestones->update($milestone, $validated);

        return response()->json(['data' => $this->fmtMilestone($milestone)]);
    }

    public function milestoneReorder(Request $request, int $projectId): JsonResponse
    {
        $business = $this->manageBusinessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $projectId)->firstOrFail();

        $ids = $request->validate([
            'ids'   => 'required|array|max:500',
            'ids.*' => 'integer',
        ])['ids'];

        $this->milestones->reorder($project, $ids);

        return response()->json(['data' => $this->milestones->listForProject($project)->map(fn ($m) => $this->fmtMilestone($m))]);
    }

    public function milestoneComplete(Request $request, int $id): JsonResponse
    {
        $business  = $this->manageBusinessOrAbort($request);
        $milestone = $this->resolveMilestone($business, $id);
        $milestone = $this->milestones->complete($milestone);

        return response()->json(['data' => $this->fmtMilestone($milestone)]);
    }

    public function milestoneReopen(Request $request, int $id): JsonResponse
    {
        $business  = $this->manageBusinessOrAbort($request);
        $milestone = $this->resolveMilestone($business, $id);
        $milestone = $this->milestones->reopen($milestone);

        return response()->json(['data' => $this->fmtMilestone($milestone)]);
    }

    public function milestoneDestroy(Request $request, int $id): JsonResponse
    {
        $business  = $this->manageBusinessOrAbort($request);
        $milestone = $this->resolveMilestone($business, $id);
        $this->milestones->delete($milestone);

        return response()->json(['message' => 'Milestone deleted.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Business for the Projects-tab endpoints — requires "Manage All Projects" (owners/admins always pass). */
    private function manageBusinessOrAbort(Request $request): \Modules\Business\Models\Business
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'projects_access');

        return $business;
    }

    /** Business for the My Projects endpoints — requires "Assigned Project Access". */
    private function assignedBusinessOrAbort(Request $request): \Modules\Business\Models\Business
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'projects_assigned');

        return $business;
    }

    private function resolveTask(\Modules\Business\Models\Business $business, int $id): Task
    {
        $task = Task::with('project')->findOrFail($id);
        abort_unless((int) $task->project->business_id === (int) $business->id, 404);
        return $task;
    }

    /** A task assigned to the caller (My Projects endpoints). */
    private function resolveMyTask(Request $request, int $id): Task
    {
        $business = $this->assignedBusinessOrAbort($request);
        $task     = $this->resolveTask($business, $id);
        abort_unless($this->tasks->isAssignee($task, (int) $request->user()->id), 403, 'You can only manage files of tasks assigned to you.');

        return $task;
    }

    /**
     * A delete request addressed to the caller. Only business access is required — the
     * owner may manage projects without "Assigned Project Access" and still gets asked.
     */
    private function resolveDeleteRequest(Request $request, int $id): TaskDeleteRequest
    {
        $business      = $this->businessOrAbort($request);
        $deleteRequest = $this->deleteRequests->findForOwner($business, $id, (int) $request->user()->id);
        abort_unless($deleteRequest, 404, 'This delete request no longer exists — it may already have been approved.');

        return $deleteRequest;
    }

    private function resolveAttachment(\Modules\Business\Models\Business $business, int $id): TaskAttachment
    {
        $attachment = TaskAttachment::with('task.project')->findOrFail($id);
        abort_unless((int) $attachment->task->project->business_id === (int) $business->id, 404);
        return $attachment;
    }

    private function storeComment(Request $request, Task $task): JsonResponse
    {
        // has_files: the client uploads files to the comment right after, so the text may be empty.
        $data = $request->validate([
            'body'      => 'nullable|string|max:5000|required_unless:has_files,true,1',
            'parent_id' => 'nullable|integer',
            'has_files' => 'nullable|boolean',
        ], ['body.required_unless' => 'Write a comment or attach a file.']);

        $comment = $this->tasks->addComment($task, (int) ($request->user()?->id ?? 0), (string) ($data['body'] ?? ''), $data['parent_id'] ?? null);

        return response()->json(['data' => $this->tasks->fmtComment($comment)], 201);
    }

    private function storeAttachments(Request $request, Task $task, ?TaskComment $comment = null): JsonResponse
    {
        $request->validate(TaskAttachmentService::uploadRules(), TaskAttachmentService::uploadMessages());

        $stored = $this->attachments->store($task, $request->file('files', []), $request->user()?->id, $comment);

        return response()->json(['data' => $stored->map(fn ($a) => $this->attachments->fmt($a))->values()], 201);
    }

    /** milestone_id must be null or a milestone of the given project. */
    private function milestoneIdRule(Project $project): array
    {
        return ['nullable', 'integer', Rule::exists('pm_milestones', 'id')->where(fn ($q) => $q->where('project_id', $project->id))];
    }

    private function resolveStatus(\Modules\Business\Models\Business $business, int $id): TaskStatus
    {
        $status = TaskStatus::with('project')->findOrFail($id);
        abort_unless((int) $status->project->business_id === (int) $business->id, 404);
        return $status;
    }

    private function resolveMilestone(\Modules\Business\Models\Business $business, int $id): Milestone
    {
        $milestone = Milestone::with('project')->findOrFail($id);
        abort_unless((int) $milestone->project->business_id === (int) $business->id, 404);
        return $milestone;
    }

    /** @return array{data: \Illuminate\Support\Collection, available: \Illuminate\Support\Collection} */
    private function membersPayload(Project $project): array
    {
        $members   = $this->projects->membersForProject($project);
        $memberIds = $members->pluck('id')->all();

        return [
            'data'      => $members,
            'available' => $this->projects->businessUsers($project->business)
                ->reject(fn (array $u) => in_array($u['id'], $memberIds, true))
                ->values(),
        ];
    }

    private function fmtProject(Project $p): array
    {
        $stats = $p->taskStats();

        $assignmentName = match ($p->assignment_type) {
            Project::ASSIGNMENT_BRANCH       => $p->branch?->name,
            Project::ASSIGNMENT_DEPARTMENT   => $p->department?->name,
            Project::ASSIGNMENT_PROPERTY     => $p->property?->property_name,
            Project::ASSIGNMENT_EMPLOYEE     => $p->employee?->full_name,
            Project::ASSIGNMENT_MODIFICATION => $p->modification?->name,
            Project::ASSIGNMENT_RENTAL       => $p->rental?->property_type,
            Project::ASSIGNMENT_OTHER        => $p->assignment_reference,
            default                          => null,
        };

        return [
            'id'          => $p->id,
            'name'        => $p->name,
            'description' => $p->description,
            'client_name' => $p->client_name,
            'status'      => $p->status,
            'priority'    => $p->priority,
            'color'       => $p->color,
            'start_date'  => $p->start_date?->toDateString(),
            'due_date'    => $p->due_date?->toDateString(),
            'budget'      => $p->budget ? (float) $p->budget : null,
            'task_stats'  => $stats,
            'tasks_count' => $stats['total'],
            'members_count' => (int) ($p->members_count ?? $p->members()->count()),
            'created_at'  => $p->created_at?->toDateTimeString(),

            'project_type'          => $p->project_type,
            'customer_id'           => $p->customer_id,
            'customer_name'         => $p->customer?->name,
            'assignment_type'       => $p->assignment_type,
            'assignment_target_id'  => $p->branch_id ?? $p->department_id ?? $p->property_id ?? $p->employee_id ?? $p->modification_id ?? $p->rental_id,
            'assignment_reference'  => $p->assignment_reference,
            'assignment_name'       => $assignmentName,
            'file_manager_file_id'  => $p->file_manager_file_id,
            'image_url'             => $p->imageFile?->publicUrl(),
        ];
    }

    private function fmtTask(Task $t): array
    {
        return [
            'id'               => $t->id,
            'project_id'       => $t->project_id,
            'project_name'     => $t->project?->name,
            'milestone_id'     => $t->milestone_id,
            'milestone_name'   => $t->milestone?->name,
            'title'            => $t->title,
            'description'      => $t->description,
            'status'           => $t->status,
            'priority'         => $t->priority,
            'assignees'        => $t->assignees->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name, 'avatar_url' => $u->avatarUrl()])->values(),
            'assignee_ids'     => $t->assignees->pluck('id')->map(fn ($id) => (int) $id)->values(),
            // Legacy single-assignee fields (older desktop builds): first assignee id, all names.
            'assigned_to'      => $t->assigned_to,
            'assigned_name'    => $t->assignees->pluck('name')->implode(', ') ?: null,
            'due_date'         => $t->due_date?->toDateString(),
            'estimated_hours'  => $t->estimated_hours ? (float) $t->estimated_hours : null,
            'logged_minutes'   => $t->totalLoggedMinutes(),
            'attachments_count'=> (int) ($t->attachments_count ?? $t->attachments()->count()),
            'is_overdue'       => $t->isOverdue(),
            'completed_at'     => $t->completed_at?->toDateTimeString(),
            'created_at'       => $t->created_at?->toDateTimeString(),
            // Deletion (My Projects): the owner deletes directly, others send a delete request.
            'created_by'       => $t->created_by !== null ? (int) $t->created_by : null,
            'owner_id'         => $t->ownerId(),
            'is_owner'         => auth()->id() !== null && $t->isOwnedBy((int) auth()->id()),
            'delete_request'   => $t->relationLoaded('pendingDeleteRequest') && $t->pendingDeleteRequest ? [
                'id'           => $t->pendingDeleteRequest->id,
                'requested_by' => (int) $t->pendingDeleteRequest->requested_by,
                'is_mine'      => (int) $t->pendingDeleteRequest->requested_by === (int) auth()->id(),
                'created_at'   => $t->pendingDeleteRequest->created_at?->toDateTimeString(),
            ] : null,
        ];
    }

    private function fmtMilestone(Milestone $m): array
    {
        return [
            'id'           => $m->id,
            'project_id'   => $m->project_id,
            'name'         => $m->name,
            'description'  => $m->description,
            'start_date'   => $m->start_date?->toDateString(),
            'due_date'     => $m->due_date?->toDateString(),
            'sort_order'   => (int) $m->sort_order,
            'status'       => $m->status,
            'completed_at' => $m->completed_at?->toDateTimeString(),
            'tasks_count'  => $m->tasks_count ?? $m->tasks()->count(),
            'done_count'   => $m->done_tasks_count ?? $m->tasks()->where('status', Task::STATUS_DONE)->count(),
        ];
    }
}
