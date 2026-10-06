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

        return redirect()->route('pm.projects.tasks.board', $project)->with('status', 'Status added.');
    }

    public function update(Request $request, TaskStatus $taskStatus): RedirectResponse
    {
        $business = $this->requireProject($request, $taskStatus->project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->taskService->updateStatus($taskStatus, $request->validate(TaskService::statusRules(partial: true)));

        return redirect()->route('pm.projects.tasks.board', $taskStatus->project_id)->with('status', 'Status updated.');
    }

    public function destroy(Request $request, TaskStatus $taskStatus): RedirectResponse
    {
        $business = $this->requireProject($request, $taskStatus->project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $projectId = $taskStatus->project_id;
        $this->taskService->deleteStatus($taskStatus);

        return redirect()->route('pm.projects.tasks.board', $projectId)->with('status', 'Status deleted. Its tasks were moved to To Do.');
    }
}
