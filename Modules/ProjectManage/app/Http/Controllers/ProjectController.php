<?php

namespace Modules\ProjectManage\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\FileManager\Services\FileManagerService;
use Modules\Pos\Models\Customer;
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
        private readonly FileManagerService $fileManagerService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $hasProjects = $this->projectService->businessHasProjects($business);

        $statusFilter = (string) $request->query('status', '');
        $typeFilter   = (string) $request->query('type', '');
        $search       = trim((string) $request->query('q', ''));

        $projects = $this->projectService->listForBusiness($business, [
            'status'       => $statusFilter,
            'project_type' => $typeFilter,
            'search'       => $search,
        ]);

        $modalOpen = $hasProjects && (($request->hasSession() && session('errors') !== null && $request->old()) || $request->boolean('new'));

        return view('projectmanage::projects.index', [
            'business'          => $business,
            'hasProjects'       => $hasProjects,
            'projects'          => $projects,
            'modalOpen'         => $modalOpen,
            'statusFilter'      => $statusFilter,
            'typeFilter'        => $typeFilter,
            'search'            => $search,
            'assignableTargets' => $this->projectService->assignableTargets($business),
            'customers'         => Customer::query()->where('business_id', $business->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $this->validatedProjectData($request);

        if ($request->hasFile('image')) {
            $file = $this->fileManagerService->storeFile($business, $request->file('image'), null, (int) $request->user()?->id, 'Project image');
            $data['file_manager_file_id'] = $file->id;
        }

        $project = $this->projectService->create($business, $data, (int) $request->user()?->id);

        return redirect()->route('pm.projects.show', $project)->with('status', 'Project "' . $project->name . '" created.');
    }

    public function show(Request $request, Project $project): View|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $project->loadMissing(['customer', 'branch', 'department', 'property', 'employee', 'modification', 'rental', 'imageFile']);

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
            'business'          => $business,
            'project'           => $project,
            'assignableTargets' => $this->projectService->assignableTargets($business),
            'customers'         => Customer::query()->where('business_id', $business->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $this->validatedProjectData($request);

        if ($request->hasFile('image')) {
            $file = $this->fileManagerService->storeFile($business, $request->file('image'), null, (int) $request->user()?->id, 'Project image');
            $data['file_manager_file_id'] = $file->id;
        } elseif ($request->boolean('remove_image')) {
            $data['file_manager_file_id'] = null;
        } else {
            $data['file_manager_file_id'] = $project->file_manager_file_id;
        }

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

    private function validatedProjectData(Request $request): array
    {
        return $request->validate([
            'name'                 => ['required', 'string', 'max:150'],
            'description'          => ['nullable', 'string', 'max:5000'],
            'client_name'          => ['nullable', 'string', 'max:120'],
            'priority'             => ['nullable', Rule::in([Project::PRIORITY_LOW, Project::PRIORITY_NORMAL, Project::PRIORITY_HIGH])],
            'status'               => ['nullable', Rule::in([Project::STATUS_ACTIVE, Project::STATUS_ON_HOLD, Project::STATUS_COMPLETED, Project::STATUS_ARCHIVED])],
            'start_date'           => ['nullable', 'date'],
            'due_date'             => ['nullable', 'date'],
            'budget'               => ['nullable', 'numeric', 'min:0'],
            'color'                => ['nullable', 'string', 'max:20'],
            'project_type'         => ['nullable', Rule::in([Project::TYPE_IN_HOUSE, Project::TYPE_CUSTOMER])],
            'customer_id'          => ['nullable', 'integer'],
            'assignment_type'      => ['nullable', Rule::in([
                Project::ASSIGNMENT_NONE, Project::ASSIGNMENT_BRANCH, Project::ASSIGNMENT_DEPARTMENT,
                Project::ASSIGNMENT_PROPERTY, Project::ASSIGNMENT_EMPLOYEE, Project::ASSIGNMENT_MODIFICATION,
                Project::ASSIGNMENT_RENTAL, Project::ASSIGNMENT_OTHER,
            ])],
            'branch_id'            => ['nullable', 'integer'],
            'department_id'        => ['nullable', 'integer'],
            'property_id'          => ['nullable', 'integer'],
            'employee_id'          => ['nullable', 'integer'],
            'modification_id'      => ['nullable', 'integer'],
            'rental_id'            => ['nullable', 'integer'],
            'assignment_reference' => ['nullable', 'string', 'max:255'],
            'image'                => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);
    }
}
