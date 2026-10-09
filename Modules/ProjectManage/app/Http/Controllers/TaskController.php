<?php

namespace Modules\ProjectManage\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\ProjectManage\Http\Controllers\Concerns\ResolvesProjectManageBusiness;
use Modules\ProjectManage\Models\Project;
use Modules\ProjectManage\Models\Task;
use Modules\ProjectManage\Services\MilestoneService;
use Modules\ProjectManage\Services\TaskService;

class TaskController extends Controller
{
    use ResolvesProjectManageBusiness;

    public function __construct(
        private readonly TaskService $taskService,
        private readonly MilestoneService $milestoneService,
    ) {}

    public function index(Request $request, Project $project): View|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $statuses   = collect($this->taskService->statusesForProject($project));
        $statusTabs = ['' => 'All'] + $statuses->pluck('label', 'status')->all();

        // All tasks are loaded so milestone progress counts every task; the status
        // filter only hides rows on the page.
        $statusFilter = (string) $request->query('status', '');
        if (!array_key_exists($statusFilter, $statusTabs)) {
            $statusFilter = '';
        }
        $activeView = $request->query('view') === 'timeline' ? 'timeline' : 'milestones';

        $tasks      = $this->taskService->listForProject($project);
        $milestones = $this->milestoneService->listForProject($project);

        return view('projectmanage::tasks.index', [
            'business'        => $business,
            'project'         => $project,
            'tasks'           => $tasks,
            'milestones'      => $milestones,
            'groups'          => $this->milestoneService->groupTasks($milestones, $tasks),
            'activeView'      => $activeView,
            'statusFilter'    => $statusFilter,
            'statusTabs'      => $statusTabs,
            'statusColors'    => $statuses->pluck('color', 'status')->filter()->all(),
            'assignableUsers' => $project->members()->orderBy('name')->get(),
        ]);
    }

    public function mine(Request $request, Project $project): View|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $filter = (string) $request->query('filter', 'open');
        $tasks  = $this->taskService->listForProject($project, ['assigned_to' => (int) $request->user()?->id]);

        $tasks = $filter === 'overdue'
            ? $tasks->filter(fn (Task $t) => $t->isOverdue())->values()
            : $tasks->filter(fn (Task $t) => $t->isOpen())->values();

        return view('projectmanage::tasks.mine', [
            'business' => $business,
            'project'  => $project,
            'tasks'    => $tasks,
            'filter'   => $filter,
        ]);
    }

    public function board(Request $request, Project $project): View|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $columns    = $this->taskService->boardForProject($project);
        $milestones = $this->milestoneService->listForProject($project);

        return view('projectmanage::tasks.board', [
            'business'   => $business,
            'project'    => $project,
            'columns'    => $columns,
            'milestones' => $milestones,
        ]);
    }

    public function show(Request $request, Task $task): View|RedirectResponse
    {
        $business = $this->requireTask($request, $task);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $task->load(['project', 'milestone', 'assignees', 'comments.user', 'timeLogs.user']);
        $milestones = $this->milestoneService->listForProject($task->project);

        return view('projectmanage::tasks.show', [
            'business'        => $business,
            'task'            => $task,
            'milestones'      => $milestones,
            'statuses'        => $this->taskService->statusesForProject($task->project),
            'assignableUsers' => $task->project->members()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $this->validatedTaskData($request, $project);
        $this->taskService->create($project, $data);

        if ($request->input('_from') === 'board') {
            return redirect()->route('pm.projects.tasks.board', $project)->with('status', 'Task added.');
        }

        // Back to the Tasks page as it was (view, filter, open timeline modal).
        return redirect()->back(fallback: route('pm.projects.tasks.index', $project))->with('status', 'Task added.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $business = $this->requireTask($request, $task);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $this->validatedTaskData($request, $task->project);
        $this->taskService->update($task, $data);

        return redirect()->route('pm.tasks.show', $task)->with('status', 'Task updated.');
    }

    public function status(Request $request, Task $task): RedirectResponse|JsonResponse
    {
        $business = $this->requireTask($request, $task);
        if ($business instanceof RedirectResponse) {
            return $request->expectsJson()
                ? response()->json(['message' => 'You cannot update this task.'], 403)
                : $business;
        }

        $data = $request->validate([
            'status' => ['required', Rule::in($this->taskService->statusKeysForProject($task->project))],
        ]);

        $task = $this->taskService->moveStatus($task, $data['status']);

        // Board drag-and-drop posts via fetch and expects JSON; the Move menu is a plain form.
        if ($request->expectsJson()) {
            return response()->json(['data' => ['id' => $task->id, 'status' => $task->status]]);
        }

        return redirect()->back()->with('status', 'Task status updated.');
    }

    /** Moves a task to another milestone of its project (empty = no milestone). Used by the Tasks page drag-and-drop / select. */
    public function milestone(Request $request, Task $task): RedirectResponse|JsonResponse
    {
        $business = $this->requireTask($request, $task);
        if ($business instanceof RedirectResponse) {
            return $request->expectsJson()
                ? response()->json(['message' => 'You cannot update this task.'], 403)
                : $business;
        }

        $milestoneId = $request->validate([
            'milestone_id' => ['nullable', 'integer', Rule::exists('pm_milestones', 'id')->where(fn ($q) => $q->where('project_id', $task->project_id))],
        ])['milestone_id'] ?? null;

        $task = $this->taskService->moveMilestone($task, $milestoneId !== null ? (int) $milestoneId : null);

        if ($request->expectsJson()) {
            return response()->json(['data' => [
                'id'             => $task->id,
                'milestone_id'   => $task->milestone_id,
                'milestone_name' => $task->milestone?->name,
            ]]);
        }

        return redirect()->back()->with('status', 'Task milestone updated.');
    }

    /** Replaces the task's assignees with assignee_ids (project members only; [] = unassigned). Used by the Tasks page assign dialog. */
    public function assignees(Request $request, Task $task): RedirectResponse|JsonResponse
    {
        $business = $this->requireTask($request, $task);
        if ($business instanceof RedirectResponse) {
            return $request->expectsJson()
                ? response()->json(['message' => 'You cannot update this task.'], 403)
                : $business;
        }

        $validated = $request->validate(TaskService::assigneeRules($task->project), TaskService::assigneeMessages());
        $task      = $this->taskService->assign($task, TaskService::assigneeIdsFrom($validated) ?? []);

        if ($request->expectsJson()) {
            return response()->json(['data' => [
                'id'        => $task->id,
                'assignees' => $task->assignees->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])->values(),
            ]]);
        }

        return redirect()->back()->with('status', 'Task assignees updated.');
    }

    public function complete(Request $request, Task $task): RedirectResponse
    {
        $business = $this->requireTask($request, $task);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->taskService->complete($task);

        return redirect()->back()->with('status', 'Task marked as done.');
    }

    public function reopen(Request $request, Task $task): RedirectResponse
    {
        $business = $this->requireTask($request, $task);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->taskService->reopen($task);

        return redirect()->back()->with('status', 'Task reopened.');
    }

    public function comment(Request $request, Task $task): RedirectResponse
    {
        $business = $this->requireTask($request, $task);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $this->taskService->addComment($task, (int) $request->user()?->id, $data['body']);

        return redirect()->route('pm.tasks.show', $task)->with('status', 'Comment added.');
    }

    public function logTime(Request $request, Task $task): RedirectResponse
    {
        $business = $this->requireTask($request, $task);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'minutes'   => ['required', 'integer', 'min:1', 'max:32767'],
            'logged_at' => ['required', 'date'],
            'note'      => ['nullable', 'string', 'max:255'],
        ]);

        $this->taskService->logTime($task, (int) $request->user()?->id, $data);

        return redirect()->route('pm.tasks.show', $task)->with('status', 'Time logged.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $business = $this->requireTask($request, $task);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $project = $task->project;
        $this->taskService->delete($task);

        // From the Tasks page go back to it as it was; from the task page itself (now gone) go to the list.
        if ($request->boolean('_back')) {
            return redirect()->back(fallback: route('pm.projects.tasks.index', $project))->with('status', 'Task deleted.');
        }

        return redirect()->route('pm.projects.tasks.index', $project)->with('status', 'Task deleted.');
    }

    private function validatedTaskData(Request $request, Project $project): array
    {
        return $request->validate(array_merge([
            'title'           => ['required', 'string', 'max:200'],
            'description'     => ['nullable', 'string', 'max:10000'],
            'status'          => ['nullable', Rule::in($this->taskService->statusKeysForProject($project))],
            'completion_status' => ['nullable', Rule::in(array_keys(Task::COMPLETION_STATUSES))],
            'priority'        => ['nullable', Rule::in([Task::PRIORITY_LOW, Task::PRIORITY_NORMAL, Task::PRIORITY_HIGH])],
            'due_date'        => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'milestone_id'    => ['nullable', 'integer', Rule::exists('pm_milestones', 'id')->where(fn ($q) => $q->where('project_id', $project->id))],
        ], TaskService::assigneeRules($project)), TaskService::assigneeMessages());
    }
}
