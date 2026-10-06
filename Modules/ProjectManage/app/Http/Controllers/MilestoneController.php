<?php

namespace Modules\ProjectManage\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\ProjectManage\Http\Controllers\Concerns\ResolvesProjectManageBusiness;
use Modules\ProjectManage\Models\Milestone;
use Modules\ProjectManage\Models\Project;
use Modules\ProjectManage\Services\MilestoneService;

/**
 * Milestones are managed from the project dashboard and the Tasks page;
 * every action returns to the page it was submitted from.
 */
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

        $data = $request->validate(MilestoneService::rules());

        $this->milestoneService->create($project, $data);

        return $this->back($project, 'Milestone added.');
    }

    public function update(Request $request, Project $project, Milestone $milestone): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless((int) $milestone->project_id === (int) $project->id, 404);

        $data = $request->validate(MilestoneService::rules());

        $this->milestoneService->update($milestone, $data);

        return $this->back($project, 'Milestone updated.');
    }

    /** Drag-and-drop / arrow reorder on the Tasks page (fetch, JSON). */
    public function reorder(Request $request, Project $project): JsonResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return response()->json(['message' => 'You cannot update this project.'], 403);
        }

        $ids = $request->validate([
            'ids'   => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ])['ids'];

        $this->milestoneService->reorder($project, $ids);

        return response()->json([
            'data' => $this->milestoneService->listForProject($project)
                ->map(fn (Milestone $m) => ['id' => $m->id, 'sort_order' => (int) $m->sort_order])
                ->values(),
        ]);
    }

    public function complete(Request $request, Project $project, Milestone $milestone): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless((int) $milestone->project_id === (int) $project->id, 404);

        $this->milestoneService->complete($milestone);

        return $this->back($project, 'Milestone marked complete.');
    }

    public function reopen(Request $request, Project $project, Milestone $milestone): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless((int) $milestone->project_id === (int) $project->id, 404);

        $this->milestoneService->reopen($milestone);

        return $this->back($project, 'Milestone reopened.');
    }

    public function destroy(Request $request, Project $project, Milestone $milestone): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless((int) $milestone->project_id === (int) $project->id, 404);

        $this->milestoneService->delete($milestone);

        return $this->back($project, 'Milestone deleted. Its tasks were kept without a milestone.');
    }

    private function back(Project $project, string $status): RedirectResponse
    {
        return redirect()->back(fallback: route('pm.projects.show', $project))->with('status', $status);
    }
}
