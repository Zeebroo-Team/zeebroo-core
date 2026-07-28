<?php

namespace Modules\ProjectManage\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\ProjectManage\Http\Controllers\Concerns\ResolvesProjectManageBusiness;
use Modules\ProjectManage\Models\Project;
use Modules\ProjectManage\Services\MilestoneService;
use Modules\ProjectManage\Services\ProjectService;
use Modules\ProjectManage\Services\TaskService;

class ProjectController extends Controller
{
    use ResolvesProjectManageBusiness;

    public function __construct(
        private readonly ProjectService $projectService,
        private readonly MilestoneService $milestoneService,
        private readonly TaskService $taskService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $hasProjects = $this->projectService->businessHasProjects($business);
        $projects    = $this->projectService->listForBusiness($business);
        $modalOpen   = $hasProjects && $request->hasSession() && session()->has('_old_input');

        return view('projectmanage::projects.index', [
            'business'   => $business,
            'hasProjects' => $hasProjects,
            'projects'   => $projects,
            'modalOpen'  => $hasProjects && $request->hasSession() && session('errors') !== null && $request->old(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'client_name' => ['nullable', 'string', 'max:120'],
            'priority'    => ['nullable', Rule::in([Project::PRIORITY_LOW, Project::PRIORITY_NORMAL, Project::PRIORITY_HIGH])],
            'status'      => ['nullable', Rule::in([Project::STATUS_ACTIVE, Project::STATUS_ON_HOLD, Project::STATUS_COMPLETED, Project::STATUS_ARCHIVED])],
            'start_date'  => ['nullable', 'date'],
            'due_date'    => ['nullable', 'date'],
            'budget'      => ['nullable', 'numeric', 'min:0'],
            'color'       => ['nullable', 'string', 'max:20'],
        ]);

        $project = $this->projectService->create($business, $data, (int) $request->user()?->id);

        return redirect()->route('pm.projects.show', $project)->with('status', 'Project "' . $project->name . '" created.');
    }

    public function show(Request $request, Project $project): View|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $stats      = $project->taskStats();
        $milestones = $this->milestoneService->listForProject($project);
        $recentTasks = $this->taskService->listForProject($project)
            ->sortByDesc('id')
            ->take(8);

        return view('projectmanage::projects.show', [
            'business'    => $business,
            'project'     => $project,
            'stats'       => $stats,
            'milestones'  => $milestones,
            'recentTasks' => $recentTasks,
            'hasTasks'    => $project->tasks()->exists(),
        ]);
    }

    public function edit(Request $request, Project $project): View|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('projectmanage::projects.edit', [
            'business' => $business,
            'project'  => $project,
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'client_name' => ['nullable', 'string', 'max:120'],
            'priority'    => ['nullable', Rule::in([Project::PRIORITY_LOW, Project::PRIORITY_NORMAL, Project::PRIORITY_HIGH])],
            'status'      => ['nullable', Rule::in([Project::STATUS_ACTIVE, Project::STATUS_ON_HOLD, Project::STATUS_COMPLETED, Project::STATUS_ARCHIVED])],
            'start_date'  => ['nullable', 'date'],
            'due_date'    => ['nullable', 'date'],
            'budget'      => ['nullable', 'numeric', 'min:0'],
            'color'       => ['nullable', 'string', 'max:20'],
        ]);

        $this->projectService->update($project, $data);

        return redirect()->route('pm.projects.index')->with('status', 'Project updated.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        try {
            $this->projectService->delete($project);
        } catch (ValidationException $e) {
            return redirect()->route('pm.projects.show', $project)->withErrors($e->errors());
        }

        return redirect()->route('pm.projects.index')->with('status', 'Project deleted.');
    }
}
