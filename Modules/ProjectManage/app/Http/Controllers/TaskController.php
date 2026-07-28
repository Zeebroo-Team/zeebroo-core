<?php

namespace Modules\ProjectManage\Http\Controllers;

use App\Http\Controllers\Controller;
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

        $statusFilter = $request->query('status', '');
        $filters      = filled($statusFilter) ? ['status' => $statusFilter] : [];
        $tasks        = $this->taskService->listForProject($project, $filters);
        $milestones   = $this->milestoneService->listForProject($project);

        $statusTabs = [
            ''                      => 'All',
            Task::STATUS_TODO        => 'To Do',
            Task::STATUS_IN_PROGRESS => 'In Progress',
            Task::STATUS_REVIEW      => 'Review',
            Task::STATUS_DONE        => 'Done',
        ];

        return view('projectmanage::tasks.index', [
            'business'        => $business,
            'project'         => $project,
            'tasks'           => $tasks,
            'milestones'      => $milestones,
            'statusFilter'    => $statusFilter,
            'statusTabs'      => $statusTabs,
            'assignableUsers' => $this->assignableUsers($business),
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

        $task->load(['project', 'milestone', 'assignedTo', 'comments.user', 'timeLogs.user']);
        $milestones = $this->milestoneService->listForProject($task->project);

        return view('projectmanage::tasks.show', [
            'business'        => $business,
            'task'            => $task,
            'milestones'      => $milestones,
            'assignableUsers' => $this->assignableUsers($business),
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

        return redirect()->route('pm.projects.tasks.index', $project)->with('status', 'Task added.');
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

    public function status(Request $request, Task $task): RedirectResponse
    {
        $business = $this->requireTask($request, $task);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'status' => ['required', Rule::in([Task::STATUS_TODO, Task::STATUS_IN_PROGRESS, Task::STATUS_REVIEW, Task::STATUS_DONE])],
        ]);

        $this->taskService->moveStatus($task, $data['status']);

        return redirect()->back()->with('status', 'Task status updated.');
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

        return redirect()->route('pm.projects.tasks.index', $project)->with('status', 'Task deleted.');
    }

    private function validatedTaskData(Request $request, Project $project): array
    {
        return $request->validate([
            'title'           => ['required', 'string', 'max:200'],
            'description'     => ['nullable', 'string', 'max:10000'],
            'status'          => ['nullable', Rule::in([Task::STATUS_TODO, Task::STATUS_IN_PROGRESS, Task::STATUS_REVIEW, Task::STATUS_DONE])],
            'priority'        => ['nullable', Rule::in([Task::PRIORITY_LOW, Task::PRIORITY_NORMAL, Task::PRIORITY_HIGH])],
            'assigned_to'     => ['nullable', 'integer', 'exists:users,id'],
            'due_date'        => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'milestone_id'    => ['nullable', 'integer', Rule::exists('pm_milestones', 'id')->where(fn ($q) => $q->where('project_id', $project->id))],
        ]);
    }
}
