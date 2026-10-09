<?php

namespace Modules\ProjectManage\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\ProjectManage\Http\Controllers\Concerns\ResolvesProjectManageBusiness;
use Modules\ProjectManage\Models\Project;
use Modules\ProjectManage\Models\TaskStatus;
use Modules\ProjectManage\Services\TaskService;

/** Custom board columns (task statuses) for a project — web side of the pm/statuses API. */
class TaskStatusController extends Controller
{
    use ResolvesProjectManageBusiness;

    public function __construct(private readonly TaskService $taskService) {}

    public function store(Request $request, Project $project): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->taskService->createStatus($project, $request->validate(TaskService::statusRules()));

        return redirect()->route('pm.projects.tasks.board', $project)->with('status', 'Stage added.');
    }

    public function update(Request $request, TaskStatus $taskStatus): RedirectResponse
    {
        $business = $this->requireProject($request, $taskStatus->project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->taskService->updateStatus($taskStatus, $request->validate(TaskService::statusRules(partial: true)));

        return redirect()->route('pm.projects.tasks.board', $taskStatus->project_id)->with('status', 'Stage updated.');
    }

    public function destroy(Request $request, TaskStatus $taskStatus): RedirectResponse
    {
        $business = $this->requireProject($request, $taskStatus->project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $projectId = $taskStatus->project_id;
        $this->taskService->deleteStatus($taskStatus);

        return redirect()->route('pm.projects.tasks.board', $projectId)->with('status', 'Stage deleted. Its tasks were moved to Not Defined.');
    }

    /** Edit any status (built-in or custom) by its key. */
    public function updateByKey(Request $request, Project $project, string $statusKey): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->taskService->updateStatus(
            $this->taskService->statusForKey($project, $statusKey),
            $request->validate(TaskService::statusRules(partial: true)),
        );

        return redirect()->route('pm.projects.tasks.board', $project)->with('status', 'Stage updated.');
    }

    /** Delete any status (built-in or custom) by its key; its tasks move to Not Defined. */
    public function destroyByKey(Request $request, Project $project, string $statusKey): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->taskService->deleteStatus($this->taskService->statusForKey($project, $statusKey));

        return redirect()->route('pm.projects.tasks.board', $project)->with('status', 'Stage deleted. Its tasks were moved to Not Defined.');
    }
}
