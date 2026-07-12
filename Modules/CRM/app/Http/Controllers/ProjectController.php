<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\CRM\Http\Controllers\Concerns\ResolvesCrmBusiness;
use Modules\CRM\Models\Project;
use Modules\CRM\Services\ActivityService;
use Modules\CRM\Services\LeadCustomFieldService;
use Modules\CRM\Services\LeadService;
use Modules\CRM\Services\LeadStageService;
use Modules\CRM\Services\ProjectService;

class ProjectController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(
        private readonly ProjectService $projectService,
        private readonly LeadService $leadService,
        private readonly LeadStageService $stageService,
        private readonly LeadCustomFieldService $customFieldService,
        private readonly ActivityService $activityService,
    ) {}

    public function show(Request $request, Project $project): View|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('crm::projects.show', [
            'business'         => $business,
            'project'          => $project,
            'pipeline'         => $this->leadService->pipelineSummary($project),
            'stageCount'       => $this->stageService->listForProject($project)->count(),
            'fieldCount'       => $this->customFieldService->listForProject($project)->count(),
            'recentLeads'      => $this->leadService->listForProject($project)->take(5),
            'stageChart'       => $this->leadService->stageTrend($project),
            'stageLogs'        => $this->leadService->stageLogsForProject($project),
            'recentActivities' => $this->activityService->recentForProject($project),
        ]);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('crm::projects.index', [
            'business'    => $business,
            'hasProjects' => $this->projectService->businessHasProjects($business),
            'projects'    => $this->projectService->listForBusiness($business),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $project = $this->projectService->create($business, $this->validated($request, $business));

        return redirect()->route('crm.projects.show', $project)->with('status', 'Project "' . $project->name . '" created.');
    }

    public function edit(Request $request, Project $project): View|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('crm::projects.edit', [
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

        $this->projectService->update($project, $this->validated($request, $business, $project));

        return redirect()->route('crm.projects.index')->with('status', 'Project updated.');
    }

    public function archive(Request $request, Project $project): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->projectService->archive($project);

        return redirect()->route('crm.projects.index')->with('status', 'Project archived.');
    }

    public function reactivate(Request $request, Project $project): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->projectService->reactivate($project);

        return redirect()->route('crm.projects.index')->with('status', 'Project reactivated.');
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
            return redirect()->route('crm.projects.index')->withErrors($e->errors());
        }

        return redirect()->route('crm.projects.index')->with('status', 'Project deleted.');
    }

    private function validated(Request $request, \Modules\Business\Models\Business $business, ?Project $project = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('crm_projects', 'name')
                    ->where(fn ($q) => $q->where('business_id', $business->id))
                    ->ignore($project?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
