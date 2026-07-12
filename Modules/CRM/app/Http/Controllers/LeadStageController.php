<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\CRM\Http\Controllers\Concerns\ResolvesCrmBusiness;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\Project;
use Modules\CRM\Services\LeadStageService;

class LeadStageController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(
        private readonly LeadStageService $stages,
    ) {}

    public function index(Request $request, Project $project): View|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $stages = $this->stages->listForProject($project)->loadCount('leads');

        return view('crm::leads.stages.index', [
            'business' => $business,
            'project'  => $project,
            'stages'   => $stages,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $this->validated($request, $project);
        $this->stages->create($project, $data);

        return redirect()->route('crm.projects.stages.index', $project)->with('status', 'Stage added.');
    }

    public function update(Request $request, Project $project, LeadStage $stage): RedirectResponse
    {
        $business = $this->requireStage($request, $project, $stage);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $this->validated($request, $project, $stage);
        $this->stages->update($stage, $data);

        return redirect()->route('crm.projects.stages.index', $project)->with('status', 'Stage updated.');
    }

    public function destroy(Request $request, Project $project, LeadStage $stage): RedirectResponse
    {
        $business = $this->requireStage($request, $project, $stage);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        try {
            $this->stages->delete($stage);
        } catch (ValidationException $e) {
            return redirect()->route('crm.projects.stages.index', $project)->withErrors($e->errors());
        }

        return redirect()->route('crm.projects.stages.index', $project)->with('status', 'Stage deleted.');
    }

    public function reorder(Request $request, Project $project): JsonResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            abort(403);
        }

        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        $this->stages->reorder($project, $ids);

        return response()->json(['success' => true]);
    }

    private function requireStage(Request $request, Project $project, LeadStage $stage): Business|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->stages->stageForProject($project, $stage) instanceof LeadStage, 404);

        return $business;
    }

    private function validated(Request $request, Project $project, ?LeadStage $stage = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('crm_lead_stages', 'name')
                    ->where(fn ($q) => $q->where('project_id', $project->id))
                    ->ignore($stage?->id),
            ],
            'color'   => ['nullable', 'string', 'max:20'],
            'is_won'  => ['nullable', 'boolean'],
            'is_lost' => ['nullable', 'boolean'],
        ]);
    }
}
