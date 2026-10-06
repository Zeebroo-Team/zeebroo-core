<?php

namespace Modules\ProjectManage\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\ProjectManage\Http\Controllers\Concerns\ResolvesProjectManageBusiness;
use Modules\ProjectManage\Models\Project;
use Modules\ProjectManage\Models\Task;
use Modules\ProjectManage\Services\TaskService;

/**
 * Projects → My Projects (web): the signed-in user's assigned tasks across every project,
 * as an overview, a task list and a kanban board. Mirrors the desktop /api/pm/my-work flow —
 * the user can only add tasks for themselves and move tasks assigned to them.
 */
class MyProjectsController extends Controller
{
    use ResolvesProjectManageBusiness;

    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $view = (string) $request->query('view', 'overview');

        return view('projectmanage::my-projects.index', [
            'business' => $business,
            'view'     => in_array($view, ['overview', 'tasks', 'board'], true) ? $view : 'overview',
            'work'     => $this->workPayload($business, (int) $request->user()->id),
        ]);
    }

    /** JSON refresh of the page data (same shape as the initial payload). */
    public function data(Request $request): JsonResponse
    {
        $business = $this->requireJsonBusiness($request);

        return response()->json(['data' => $this->workPayload($business, (int) $request->user()->id)]);
    }

    /** Creates a task assigned to me, in a (non-archived) project whose team I am on. */
    public function storeTask(Request $request): JsonResponse
    {
        $business = $this->requireJsonBusiness($request);
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
            'status'          => ['nullable', Rule::in($this->taskService->statusKeysForProject($project))],
            'priority'        => 'nullable|in:low,normal,high',
            'due_date'        => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
        ], ['status.in' => 'This project does not have that status.']);

        $task = $this->taskService->createForSelf($project, $validated, $userId);

        return response()->json(['data' => $this->fmtTask($task->fresh(['milestone', 'project']))], 201);
    }

    /** Status change (kanban drag & drop, status select, complete / reopen) for a task assigned to me. */
    public function status(Request $request, Task $task): JsonResponse
    {
        $business = $this->requireJsonBusiness($request);
        $task->loadMissing('project');
        abort_unless((int) $task->project->business_id === (int) $business->id, 404);
        abort_unless($this->taskService->isAssignee($task, (int) $request->user()->id), 403, 'You can only update tasks assigned to you.');

        $status = $request->validate([
            'status' => ['required', Rule::in($this->taskService->statusKeysForProject($task->project))],
        ], ['status.in' => 'This project does not have that status.'])['status'];

        $task = $this->taskService->moveStatus($task, $status);

        return response()->json(['data' => $this->fmtTask($task->fresh(['milestone', 'project']))]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function requireJsonBusiness(Request $request): Business
    {
        $business = $this->requireBusiness($request);
        abort_if($business instanceof RedirectResponse, 422, 'Select or create a business first.');

        return $business;
    }

    /** @return array{tasks: array, projects: array} */
    private function workPayload(Business $business, int $userId): array
    {
        $work = $this->taskService->assignedWorkForUser($business, $userId);

        return [
            'tasks'    => $work['tasks']->map(fn (Task $t) => $this->fmtTask($t))->values()->all(),
            'projects' => $work['projects']->map(fn (Project $p) => [
                'id'        => $p->id,
                'name'      => $p->name,
                'status'    => $p->status,
                'priority'  => $p->priority,
                'color'     => $p->color,
                'due_date'  => $p->due_date?->toDateString(),
                'url'       => route('pm.projects.show', $p),
                'statuses'  => $this->taskService->statusesForProject($p),
                // Only team members may add tasks for themselves (see storeTask).
                'is_member' => $p->members()->where('users.id', $userId)->exists(),
            ])->values()->all(),
        ];
    }

    private function fmtTask(Task $t): array
    {
        return [
            'id'             => $t->id,
            'project_id'     => $t->project_id,
            'project_name'   => $t->project?->name,
            'milestone_name' => $t->milestone?->name,
            'title'          => $t->title,
            'status'         => $t->status,
            'priority'       => $t->priority,
            'due_date'       => $t->due_date?->toDateString(),
            'url'            => route('pm.tasks.show', $t),
        ];
    }
}
