<?php

namespace Modules\ProjectManage\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ProjectManage\Models\Milestone;
use Modules\ProjectManage\Models\Project;
use Modules\ProjectManage\Models\Task;
use Modules\ProjectManage\Services\MilestoneService;
use Modules\ProjectManage\Services\ProjectService;
use Modules\ProjectManage\Services\TaskService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class ProjectManageApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly ProjectService   $projects,
        private readonly TaskService      $tasks,
        private readonly MilestoneService $milestones,
    ) {}

    // ── Projects ─────────────────────────────────────────────────────────────

    public function projectIndex(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $filter   = $request->query('filter', 'all');
        $list     = $this->projects->listForBusiness($business, $filter);

        return response()->json(['data' => $list->map(fn ($p) => $this->fmtProject($p))]);
    }

    public function projectStore(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $validated = $request->validate([
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'client_name' => 'nullable|string|max:120',
            'priority'    => 'nullable|in:low,normal,high',
            'color'       => 'nullable|string|max:20',
            'start_date'  => 'nullable|date',
            'due_date'    => 'nullable|date',
            'budget'      => 'nullable|numeric|min:0',
        ]);

        $project = $this->projects->create($business, $validated, $request->user()?->id ?? 0);

        return response()->json(['data' => $this->fmtProject($project)], 201);
    }

    public function projectUpdate(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:150',
            'description' => 'nullable|string|max:2000',
            'client_name' => 'nullable|string|max:120',
            'priority'    => 'nullable|in:low,normal,high',
            'status'      => 'nullable|in:active,on_hold,completed,archived',
            'color'       => 'nullable|string|max:20',
            'start_date'  => 'nullable|date',
            'due_date'    => 'nullable|date',
            'budget'      => 'nullable|numeric|min:0',
        ]);

        $project = $this->projects->update($project, $validated);

        return response()->json(['data' => $this->fmtProject($project->fresh())]);
    }

    public function projectDestroy(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $id)->firstOrFail();

        try {
            $this->projects->delete($project);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json(['message' => 'Project deleted.']);
    }

    // ── Board ────────────────────────────────────────────────────────────────

    public function board(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $id)->firstOrFail();

        $board = $this->tasks->boardForProject($project);

        $labels = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'review' => 'Review', 'done' => 'Done'];
        $columns = collect($board)->map(function ($tasks, $status) use ($labels) {
            return [
                'status' => $status,
                'label'  => $labels[$status] ?? $status,
                'tasks'  => $tasks->map(fn ($t) => $this->fmtTask($t))->values(),
            ];
        })->values();

        return response()->json([
            'project' => $this->fmtProject($project),
            'columns' => $columns,
        ]);
    }

    // ── Tasks ────────────────────────────────────────────────────────────────

    public function taskIndex(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $projectId)->firstOrFail();

        $filters = $request->only(['status', 'milestone_id', 'assigned_to', 'priority']);
        $tasks   = $this->tasks->listForProject($project, $filters);

        return response()->json(['data' => $tasks->map(fn ($t) => $this->fmtTask($t))]);
    }

    public function taskStore(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $projectId)->firstOrFail();

        $validated = $request->validate([
            'title'            => 'required|string|max:200',
            'description'      => 'nullable|string|max:5000',
            'status'           => 'nullable|in:todo,in_progress,review,done',
            'priority'         => 'nullable|in:low,normal,high',
            'milestone_id'     => 'nullable|integer',
            'assigned_to'      => 'nullable|integer|exists:users,id',
            'due_date'         => 'nullable|date',
            'estimated_hours'  => 'nullable|numeric|min:0',
        ]);

        $task = $this->tasks->create($project, $validated);

        return response()->json(['data' => $this->fmtTask($task)], 201);
    }

    public function taskStatus(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $task     = $this->resolveTask($business, $id);

        $status = $request->validate(['status' => 'required|in:todo,in_progress,review,done'])['status'];
        $task   = $this->tasks->moveStatus($task, $status);

        return response()->json(['data' => $this->fmtTask($task)]);
    }

    public function taskComplete(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $task     = $this->resolveTask($business, $id);
        $task     = $this->tasks->complete($task);

        return response()->json(['data' => $this->fmtTask($task)]);
    }

    public function taskReopen(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $task     = $this->resolveTask($business, $id);
        $task     = $this->tasks->reopen($task);

        return response()->json(['data' => $this->fmtTask($task)]);
    }

    public function taskComment(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $task     = $this->resolveTask($business, $id);

        $body    = $request->validate(['body' => 'required|string|max:5000'])['body'];
        $comment = $this->tasks->addComment($task, $request->user()?->id ?? 0, $body);

        return response()->json([
            'data' => [
                'id'         => $comment->id,
                'user'       => $comment->user?->name ?? 'System',
                'body'       => $comment->body,
                'created_at' => $comment->created_at?->toDateTimeString(),
            ],
        ], 201);
    }

    public function taskTime(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
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
        $business = $this->businessOrAbort($request);
        $task     = $this->resolveTask($business, $id);
        $this->tasks->delete($task);

        return response()->json(['message' => 'Task deleted.']);
    }

    public function myTasks(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $filter = (string) $request->query('filter', 'open');

        $tasks = $this->tasks->listForBusiness($business, $filter);

        return response()->json(['data' => $tasks->map(fn ($t) => $this->fmtTask($t))]);
    }

    // ── Milestones ───────────────────────────────────────────────────────────

    public function milestoneIndex(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $projectId)->firstOrFail();

        $list = $this->milestones->listForProject($project);

        return response()->json(['data' => $list->map(fn ($m) => $this->fmtMilestone($m))]);
    }

    public function milestoneStore(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = Project::where('business_id', $business->id)->where('id', $projectId)->firstOrFail();

        $validated = $request->validate([
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'due_date'    => 'nullable|date',
        ]);

        $milestone = $this->milestones->create($project, $validated);

        return response()->json(['data' => $this->fmtMilestone($milestone)], 201);
    }

    public function milestoneComplete(Request $request, int $id): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $milestone = $this->resolveMilestone($business, $id);
        $milestone = $this->milestones->complete($milestone);

        return response()->json(['data' => $this->fmtMilestone($milestone)]);
    }

    public function milestoneDestroy(Request $request, int $id): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $milestone = $this->resolveMilestone($business, $id);
        $this->milestones->delete($milestone);

        return response()->json(['message' => 'Milestone deleted.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function resolveTask(\Modules\Business\Models\Business $business, int $id): Task
    {
        $task = Task::with('project')->findOrFail($id);
        abort_unless((int) $task->project->business_id === (int) $business->id, 404);
        return $task;
    }

    private function resolveMilestone(\Modules\Business\Models\Business $business, int $id): Milestone
    {
        $milestone = Milestone::with('project')->findOrFail($id);
        abort_unless((int) $milestone->project->business_id === (int) $business->id, 404);
        return $milestone;
    }

    private function fmtProject(Project $p): array
    {
        $stats = $p->taskStats();
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
            'created_at'  => $p->created_at?->toDateTimeString(),
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
            'assigned_to'      => $t->assigned_to,
            'assigned_name'    => $t->assignedTo?->name,
            'due_date'         => $t->due_date?->toDateString(),
            'estimated_hours'  => $t->estimated_hours ? (float) $t->estimated_hours : null,
            'logged_minutes'   => $t->totalLoggedMinutes(),
            'is_overdue'       => $t->isOverdue(),
            'completed_at'     => $t->completed_at?->toDateTimeString(),
            'created_at'       => $t->created_at?->toDateTimeString(),
        ];
    }

    private function fmtMilestone(Milestone $m): array
    {
        return [
            'id'           => $m->id,
            'project_id'   => $m->project_id,
            'name'         => $m->name,
            'description'  => $m->description,
            'due_date'     => $m->due_date?->toDateString(),
            'status'       => $m->status,
            'completed_at' => $m->completed_at?->toDateTimeString(),
            'tasks_count'  => $m->tasks()->count(),
        ];
    }
}
