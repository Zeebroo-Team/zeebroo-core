<?php

namespace Modules\ProjectManage\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\ProjectManage\Http\Controllers\Concerns\ResolvesProjectManageBusiness;
use Modules\ProjectManage\Models\Milestone;
use Modules\ProjectManage\Models\Project;
use Modules\ProjectManage\Services\MilestoneService;

class MilestoneController extends Controller
{
    use ResolvesProjectManageBusiness;

    public function __construct(
        private readonly MilestoneService $milestoneService,
    ) {}

    public function store(Request $request, Project $project): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date'    => ['nullable', 'date'],
        ]);

        $this->milestoneService->create($project, $data);

        return redirect()->route('pm.projects.show', $project)->with('status', 'Milestone added.');
    }

    public function update(Request $request, Project $project, Milestone $milestone): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless((int) $milestone->project_id === (int) $project->id, 404);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date'    => ['nullable', 'date'],
        ]);

        $this->milestoneService->update($milestone, $data);

        return redirect()->route('pm.projects.show', $project)->with('status', 'Milestone updated.');
    }

    public function complete(Request $request, Project $project, Milestone $milestone): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless((int) $milestone->project_id === (int) $project->id, 404);

        $this->milestoneService->complete($milestone);

        return redirect()->route('pm.projects.show', $project)->with('status', 'Milestone marked complete.');
    }

    public function reopen(Request $request, Project $project, Milestone $milestone): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless((int) $milestone->project_id === (int) $project->id, 404);

        $this->milestoneService->reopen($milestone);

        return redirect()->route('pm.projects.show', $project)->with('status', 'Milestone reopened.');
    }

    public function destroy(Request $request, Project $project, Milestone $milestone): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless((int) $milestone->project_id === (int) $project->id, 404);

        $this->milestoneService->delete($milestone);

        return redirect()->route('pm.projects.show', $project)->with('status', 'Milestone deleted.');
    }
}
