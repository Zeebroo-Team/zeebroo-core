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
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadForm;
use Modules\CRM\Models\Project;
use Modules\CRM\Services\LeadCustomFieldService;
use Modules\CRM\Services\LeadFormService;
use Modules\CRM\Services\LeadService;
use Modules\CRM\Services\LeadStageService;

class LeadController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(
        private readonly LeadService $leadService,
        private readonly LeadStageService $stageService,
        private readonly LeadCustomFieldService $customFieldService,
        private readonly LeadFormService $formService,
    ) {}

    public function index(Request $request, Project $project): View|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $search       = trim((string) $request->query('q', ''));
        $stageFilter  = $request->query('stage');
        $stageId      = filled($stageFilter) && $stageFilter !== 'all' ? (int) $stageFilter : null;
        $statusFilter = (string) $request->query('status', 'open');
        $leadForm     = $this->formService->listForProject($project)->first();

        return view('crm::leads.index', [
            'business'        => $business,
            'project'         => $project,
            'hasLeads'        => $this->leadService->projectHasLeads($project),
            'hasForms'        => $leadForm !== null,
            'leadForm'        => $leadForm,
            'leads'           => $this->leadService->listForProject($project, $search, $stageId, $statusFilter),
            'pipeline'        => $this->leadService->pipelineSummary($project),
            'search'          => $search,
            'stageFilter'     => $stageFilter ?? 'all',
            'statusFilter'    => $statusFilter,
            'stageOptions'    => $this->stageService->listForProject($project),
            'customFields'    => $this->customFieldService->listKeyedById($project),
            'statusTabs'      => ['open' => 'Open', 'won' => 'Won', 'lost' => 'Lost', 'all' => 'All'],
            'assignableUsers' => $this->assignableUsers($business),
        ]);
    }

    public function board(Request $request, Project $project): View|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('crm::leads.board', [
            'business'     => $business,
            'project'      => $project,
            'stages'       => $this->stageService->listForProject($project),
            'leadsByStage' => $this->leadService->groupedByStage($project),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $leadForm = $this->formService->listForProject($project)->first();
        $data     = $this->validated($request, $project, $leadForm);
        $lead     = $this->leadService->create($project, $data, $request->user()?->id);

        return redirect()->route('crm.leads.show', $lead)->with('status', 'Lead "' . $lead->name . '" created.');
    }

    public function show(Request $request, Lead $lead): View|RedirectResponse
    {
        $business = $this->requireLead($request, $lead);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $lead->load(['assignedTo', 'customer', 'stage', 'project', 'activities.createdBy', 'tasks.assignedTo', 'customFieldValues.customField']);

        return view('crm::leads.show', [
            'business'        => $business,
            'lead'            => $lead,
            'assignableUsers' => $this->assignableUsers($business),
            'stageLogs'       => $this->leadService->stageLogsForLead($lead),
        ]);
    }

    public function edit(Request $request, Lead $lead): View|RedirectResponse
    {
        $business = $this->requireLead($request, $lead);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $project = $lead->project;
        $lead->load('customFieldValues');

        return view('crm::leads.edit', [
            'business'        => $business,
            'project'         => $project,
            'lead'            => $lead,
            'leadForm'        => $this->formService->listForProject($project)->first(),
            'stageOptions'    => $this->stageService->listForProject($project),
            'customFields'    => $this->customFieldService->listKeyedById($project),
            'assignableUsers' => $this->assignableUsers($business),
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $business = $this->requireLead($request, $lead);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $project  = $lead->project;
        $leadForm = $this->formService->listForProject($project)->first();
        $data     = $this->validated($request, $project, $leadForm, $lead);
        $this->leadService->update($lead, $data, $request->user()?->id);

        return redirect()->route('crm.leads.show', $lead)->with('status', 'Lead updated.');
    }

    public function convert(Request $request, Lead $lead): RedirectResponse
    {
        $business = $this->requireLead($request, $lead);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        try {
            $this->leadService->convertToCustomer($lead, $request->user()?->id);
        } catch (ValidationException $e) {
            return redirect()->route('crm.leads.show', $lead)->withErrors($e->errors());
        }

        return redirect()->route('crm.leads.show', $lead)->with('status', 'Lead converted to a customer.');
    }

    public function markLost(Request $request, Lead $lead): RedirectResponse
    {
        $business = $this->requireLead($request, $lead);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->leadService->markLost($lead, (string) $request->input('lost_reason', ''), $request->user()?->id);

        return redirect()->route('crm.leads.show', $lead)->with('status', 'Lead marked as lost.');
    }

    public function reopen(Request $request, Lead $lead): RedirectResponse
    {
        $business = $this->requireLead($request, $lead);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->leadService->reopen($lead, $request->user()?->id);

        return redirect()->route('crm.leads.show', $lead)->with('status', 'Lead reopened.');
    }

    public function moveStage(Request $request, Lead $lead): JsonResponse
    {
        $business = $this->requireLead($request, $lead);
        if ($business instanceof RedirectResponse) {
            abort(403);
        }

        $data = $request->validate([
            'stage_id' => ['required', 'integer', Rule::exists('crm_lead_stages', 'id')->where(fn ($q) => $q->where('project_id', $lead->project_id))],
        ]);

        $this->leadService->moveStage($lead, (int) $data['stage_id'], $request->user()?->id);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, Lead $lead): RedirectResponse
    {
        $business = $this->requireLead($request, $lead);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $project = $lead->project;
        $this->leadService->delete($lead);

        return redirect()->route('crm.projects.leads.index', $project)->with('status', 'Lead deleted.');
    }

    private function requireLead(Request $request, Lead $lead): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless((int) $lead->business_id === (int) $business->id, 404);

        return $business;
    }

    /**
     * The New Lead form is built from the project's lead form (same fields a public
     * visitor would fill out) plus a fixed set of staff-only pipeline fields the
     * public builder can't express (source, value, close date, assignee, notes).
     */
    private function validated(Request $request, Project $project, ?LeadForm $leadForm, ?Lead $lead = null): array
    {
        $rules = [
            'name'                => ['required', 'string', 'max:150'],
            'source'              => ['nullable', 'string', 'max:60'],
            'stage_id'            => ['nullable', 'integer', Rule::exists('crm_lead_stages', 'id')->where(fn ($q) => $q->where('project_id', $project->id))],
            'estimated_value'     => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'expected_close_date' => ['nullable', 'date'],
            'notes'               => ['nullable', 'string', 'max:5000'],
            'assigned_to'         => ['nullable', 'integer', 'exists:users,id'],
        ];

        // Use array union (+), not array_merge(): field-block paths are numeric-string
        // keys ("2", "3", …), which PHP auto-casts to int keys — array_merge() would
        // renumber those instead of preserving them, silently corrupting the rules.
        $validated = $request->validate($rules + $this->fieldBlockRules($leadForm));

        if ($leadForm) {
            $mapped       = $leadForm->mapPathedInputsToLeadData($validated);
            $company      = $mapped['core']['company'];
            $email        = $mapped['core']['email'];
            $phone        = $mapped['core']['phone'];
            $customFields = $mapped['custom_fields'];
        } else {
            // No lead form currently exists for this project (e.g. it was deleted
            // after the lead was created) — preserve whatever the lead already had
            // rather than wiping it out.
            $company      = $lead?->company;
            $email        = $lead?->email;
            $phone        = $lead?->phone;
            $customFields = [];
        }

        return array_merge($validated, [
            'company'       => $company,
            'email'         => $email,
            'phone'         => $phone,
            'custom_fields' => $customFields,
        ]);
    }

    /**
     * Validation rules for the lead form's own field blocks, keyed by the same flat
     * hyphen-path scheme used on the public form (see LeadForm::fieldBlocksWithPaths()).
     */
    private function fieldBlockRules(?LeadForm $leadForm): array
    {
        if (!$leadForm) {
            return [];
        }

        $rules = [];
        foreach ($leadForm->fieldBlocksWithPaths() as $path => $block) {
            if (($block['field'] ?? '') === 'name') {
                continue; // Name has its own fixed input/rule above.
            }

            $required     = (bool) ($block['required'] ?? false);
            $rules[$path] = array_merge(
                [$required ? 'required' : 'nullable'],
                ($block['field'] ?? '') === 'email' ? ['email'] : ['string', 'max:2000'],
            );
        }

        return $rules;
    }
}
