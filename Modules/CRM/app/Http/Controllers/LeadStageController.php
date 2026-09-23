<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\AutomationEditor\Models\AutomationFlow;
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

        $automationEnabled = Route::has('automations.index') && $this->businessFeatureOn($business, 'automation_editor');
        $pipelineFlow = $automationEnabled
            ? AutomationFlow::query()
                ->where('business_id', $business->id)
                ->where('trigger_type', 'crm.lead.stage_changed')
                ->get()
                ->first(fn (AutomationFlow $f) => (int) ($f->trigger_config['relation_id'] ?? 0) === $project->id)
            : null;

        return view('crm::leads.stages.index', [
            'business'           => $business,
            'project'            => $project,
            'stages'             => $stages,
            'automationEnabled'  => $automationEnabled,
            'pipelineFlow'       => $pipelineFlow,
            'pipelineActive'     => (bool) $pipelineFlow?->is_active,
        ]);
    }

    /**
     * Create (or hand off to) a Pipeline Automation flow for this relation — an
     * Automation Editor flow on the "Lead Stage Changed (in Relation)" trigger,
     * scoped to this project via trigger_config.relation_id. Mirrors the desktop
     * app's "Pipeline Automation" toggle on its Stages screen. Starts inactive so
     * the user reviews/builds the flow before it takes over stage-entry side
     * effects from the per-stage mail templates (see AutomationFlow::pipelineAutomationActive()).
     */
    public function createAutomation(Request $request, Project $project): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless(Route::has('automations.index') && $this->businessFeatureOn($business, 'automation_editor'), 404);

        $flow = AutomationFlow::create([
            'business_id'    => $business->id,
            'name'           => $project->name . ' — Stage automation',
            'trigger_type'   => 'crm.lead.stage_changed',
            'trigger_config' => ['relation_id' => $project->id],
            'flow_data'      => $this->seedAutomationTriggerFlowData('crm.lead.stage_changed'),
            'is_active'      => false,
        ]);

        return redirect()->route('automations.edit', $flow)->with('status', 'Pipeline automation created — build your flow below.');
    }

    /**
     * Mirrors AutomationController::seedTriggerFlowData() — same Drawflow node
     * shape the editor itself saves, so the flow is immediately openable/runnable.
     */
    private function seedAutomationTriggerFlowData(string $triggerType): array
    {
        return [
            'drawflow' => [
                'drawflow' => [
                    'Home' => [
                        'data' => [
                            '1' => [
                                'id'       => 1,
                                'name'     => 'trigger',
                                'data'     => [
                                    'type'   => 'trigger',
                                    'config' => ['trigger' => $triggerType],
                                    'preset' => null,
                                ],
                                'class'    => 'ae-node ae-node--trigger',
                                'html'     => '',
                                'typenode' => false,
                                'inputs'   => (object) [],
                                'outputs'  => ['output_1' => ['connections' => []]],
                                'pos_x'    => 100,
                                'pos_y'    => 100,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Mirrors the `$featureOn` check in the main app layout (Modules/Theme) — a
     * business-level feature toggle stored under the "business.features" setting,
     * defaulting to on when the business has never customized it.
     */
    private function businessFeatureOn(Business $business, string $key): bool
    {
        $saved = (array) ($business->getSetting('business.features', []) ?: []);

        return array_key_exists($key, $saved) ? (bool) $saved[$key] : true;
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
