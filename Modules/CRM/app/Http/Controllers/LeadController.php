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
use Modules\CRM\Models\Division;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadForm;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\Project;
use Modules\CRM\Services\LeadCustomFieldService;
use Modules\CRM\Services\LeadFormService;
use Modules\CRM\Services\LeadIntakeService;
use Modules\CRM\Services\LeadService;
use Modules\CRM\Services\LeadStageService;
use Modules\CRM\Services\LostReasonService;

class LeadController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(
        private readonly LeadService $leadService,
        private readonly LeadStageService $stageService,
        private readonly LeadCustomFieldService $customFieldService,
        private readonly LeadFormService $formService,
        private readonly LostReasonService $lostReasons,
        private readonly LeadIntakeService $intake,
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
        $sourceFilter = array_key_exists((string) $request->query('source'), Lead::SOURCES) ? (string) $request->query('source') : null;
        $leadForm     = $this->formService->defaultOrFirstForProject($project);

        return view('crm::leads.index', [
            'business'        => $business,
            'project'         => $project,
            'hasLeads'        => $this->leadService->projectHasLeads($project),
            'hasForms'        => $leadForm !== null,
            'leadForm'        => $leadForm,
            'leads'           => $this->leadService->listForProject($project, $search, $stageId, $statusFilter, $sourceFilter),
            'pipeline'        => $this->leadService->pipelineSummary($project),
            'search'          => $search,
            'stageFilter'     => $stageFilter ?? 'all',
            'statusFilter'    => $statusFilter,
            'sourceFilter'    => $sourceFilter,
            'stageOptions'    => $this->stageService->listForProject($project),
            'customFields'    => $this->customFieldService->listKeyedById($project),
            'lostReasons'     => $this->lostReasons->listForBusiness($business),
            'statusTabs'      => ['open' => 'Open', 'won' => 'Won', 'lost' => 'Lost', 'all' => 'All'],
            'assignableUsers' => $this->assignableUsers($business),
            'canCreateLead'   => $this->leadPolicy()->canCreateLeadIn($this->crmAccess($request, $business), $project),
            'canAssign'       => $this->leadPolicy()->canAssignIn($this->crmAccess($request, $business), $project),
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
            'lostReasons'  => $this->lostReasons->listForBusiness($business),
        ]);
    }

    public function customData(Request $request, Project $project): View|RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $forms = $this->formService->listForProject($project)
            ->filter(fn (LeadForm $f) => $f->type === LeadForm::TYPE_CUSTOM_DATA);

        return view('crm::leads.custom-data.index', [
            'business'      => $business,
            'project'       => $project,
            'forms'         => $forms,
            'leadsByForm'   => $this->leadService->groupedByForm($project, $forms),
            'customFields'  => $this->customFieldService->listKeyedById($project),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $business = $this->requireProject($request, $project);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $access = $this->crmAccess($request, $business);
        abort_unless($this->leadPolicy()->canCreateLeadIn($access, $project), 403);

        $leadForm = $this->formService->defaultOrFirstForProject($project);
        $data     = $this->validated($request, $project, $leadForm) + ['form_id' => $leadForm?->id];
        // Only those who may assign pick an assignee
        if (! $this->leadPolicy()->canAssignIn($access, $project)) {
            $data['assigned_to'] = null;
        }
        $result = $this->intake->capture($data, $data['source'] ?? Lead::SOURCE_MANUAL, $project, $request->user()?->id);

        // The lead may have gone to the marketing inbox, or be another department's existing lead
        if (! $this->leadPolicy()->canView($access, $result->lead->fresh(['stage']))) {
            return redirect()->route('crm.projects.leads.index', $project)->with('status', $result->message());
        }

        return redirect()->route('crm.leads.show', $result->lead)->with('status', $result->message());
    }

    public function show(Request $request, Lead $lead): View|RedirectResponse
    {
        $business = $this->requireLead($request, $lead);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $lead->load(['assignedTo', 'customer', 'stage', 'project', 'activities.createdBy', 'tasks.assignedTo', 'customFieldValues.customField',
            'products:id,name,sku', 'lostReason', 'division', 'department', 'leadSource', 'onHoldFromStage',
            'verifiedBy:id,name', 'forwardedBy:id,name', 'requestedDepartment:id,name']);

        return view('crm::leads.show', [
            'business'        => $business,
            'lead'            => $lead,
            'assignableUsers' => $this->assignableUsers($business),
            'stageLogs'       => $this->leadService->stageLogsForLead($lead),
            'can'             => $this->leadPolicy()->abilities($this->crmAccess($request, $business), $lead),
            'lostReasons'     => $this->lostReasons->listForBusiness($business),
            'hasOnHoldStage'  => $this->stageService->stageForStatus($lead->project, LeadStage::STATUS_ON_HOLD) !== null,
        ]);
    }

    public function edit(Request $request, Lead $lead): View|RedirectResponse
    {
        $business = $this->requireLead($request, $lead, 'canEdit');
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $project = $lead->project;
        $lead->load(['customFieldValues', 'products:id,name,sku']);

        return view('crm::leads.edit', [
            'business'        => $business,
            'project'         => $project,
            'lead'            => $lead,
            'leadForm'        => $lead->form ?? $this->formService->defaultOrFirstForProject($project),
            'stageOptions'    => $this->stageService->listForProject($project),
            'customFields'    => $this->customFieldService->listKeyedById($project),
            'assignableUsers' => $this->assignableUsers($business),
            'canAssign'       => $this->leadPolicy()->canAssign($this->crmAccess($request, $business), $lead),
            'lostReasons'     => $this->lostReasons->listForBusiness($business),
            'divisions'       => $lead->department_id
                ? Division::query()->where('business_id', $business->id)->where('department_id', $lead->department_id)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $business = $this->requireLead($request, $lead, 'canEdit');
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $access   = $this->crmAccess($request, $business);
        $project  = $lead->project;
        $leadForm = $lead->form ?? $this->formService->defaultOrFirstForProject($project);
        $data     = $this->validated($request, $project, $leadForm, $lead);

        if ((int) ($data['assigned_to'] ?? 0) !== (int) $lead->assigned_to && ! $this->leadPolicy()->canAssign($access, $lead)) {
            $data['assigned_to'] = $lead->assigned_to;
        }
        // Like the assignee, only those who may assign place the lead in a division
        if (! $this->leadPolicy()->canAssign($access, $lead)) {
            unset($data['division_id']);
        }
        if (isset($data['stage_id']) && (int) $data['stage_id'] !== (int) $lead->stage_id) {
            abort_unless($this->leadPolicy()->canMove($access, $lead), 403);
        }

        $this->leadService->update($lead, $data, $request->user()?->id);

        return redirect()->route('crm.leads.show', $lead)->with('status', 'Lead updated.');
    }

    public function convert(Request $request, Lead $lead): RedirectResponse
    {
        $business = $this->requireLead($request, $lead, 'canEdit');
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
        $business = $this->requireLead($request, $lead, 'canMove');
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'lost_reason_id' => ['required', 'integer'],
            'lost_reason'    => ['nullable', 'string', 'max:255'],
        ]);

        $this->leadService->markLost($lead, $data['lost_reason_id'], $data['lost_reason'] ?? null, $request->user()?->id);

        return redirect()->route('crm.leads.show', $lead)->with('status', 'Lead marked as lost.');
    }

    public function reopen(Request $request, Lead $lead): RedirectResponse
    {
        $business = $this->requireLead($request, $lead, 'canMove');
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->leadService->reopen($lead, $request->user()?->id);

        return redirect()->route('crm.leads.show', $lead)->with('status', 'Lead reopened.');
    }

    public function hold(Request $request, Lead $lead): RedirectResponse
    {
        $business = $this->requireLead($request, $lead, 'canMove');
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->leadService->putOnHold($lead, $data['reason'] ?? null, $request->user()?->id);

        return redirect()->route('crm.leads.show', $lead)->with('status', 'Lead put on hold.');
    }

    public function resume(Request $request, Lead $lead): RedirectResponse
    {
        $business = $this->requireLead($request, $lead, 'canMove');
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->leadService->resume($lead, $request->user()?->id);

        return redirect()->route('crm.leads.show', $lead)->with('status', 'Lead resumed.');
    }

    public function moveStage(Request $request, Lead $lead): JsonResponse
    {
        $business = $this->requireLead($request, $lead, 'canMove');
        if ($business instanceof RedirectResponse) {
            abort(403);
        }

        $data = $request->validate([
            'stage_id'       => ['required', 'integer', Rule::exists('crm_lead_stages', 'id')->where(fn ($q) => $q->where('project_id', $lead->project_id))],
            'lost_reason_id' => ['nullable', 'integer'],
            'lost_reason'    => ['nullable', 'string', 'max:255'],
            'reason'         => ['nullable', 'string', 'max:500'],
        ]);

        $this->leadService->moveStage($lead, (int) $data['stage_id'], $request->user()?->id, $data);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, Lead $lead): RedirectResponse
    {
        $business = $this->requireLead($request, $lead, 'canDelete');
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $project = $lead->project;
        $this->leadService->delete($lead, $request->user()?->id);

        return redirect()->route('crm.projects.leads.index', $project)->with('status', 'Lead deleted.');
    }

    /** A lead of the current business the user may see (404 otherwise) and, given an ability, act on (403). */
    private function requireLead(Request $request, Lead $lead, ?string $ability = null): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless((int) $lead->business_id === (int) $business->id, 404);

        $access = $this->crmAccess($request, $business);
        abort_unless($this->leadPolicy()->canView($access, $lead), 404);
        if ($ability !== null) {
            abort_unless($this->leadPolicy()->{$ability}($access, $lead), 403);
        }

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
            'name'                  => ['required', 'string', 'max:150'],
            'source'                => ['nullable', Rule::in(array_keys(Lead::SOURCES))],
            'stage_id'              => ['nullable', 'integer', Rule::exists('crm_lead_stages', 'id')->where(fn ($q) => $q->where('project_id', $project->id))],
            'estimated_value'       => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'expected_close_date'   => ['nullable', 'date'],
            'notes'                 => ['nullable', 'string', 'max:5000'],
            'assigned_to'           => ['nullable', 'integer', 'exists:users,id'],
            'score'              => ['nullable', Rule::in(array_keys(Lead::SCORES))],
            'division_id'           => ['nullable', 'integer'],
            'product_interest_note' => ['nullable', 'string', 'max:2000'],
            'products'              => ['nullable', 'array', 'max:50'],
            'products.*.product_id' => ['required', 'integer'],
            'products.*.quantity'   => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lost_reason_id'        => ['nullable', 'integer'],
            'lost_reason'           => ['nullable', 'string', 'max:255'],
            'reason'                => ['nullable', 'string', 'max:500'],
        ];

        // Use array union (+), not array_merge(): field-block paths are numeric-string
        // keys ("2", "3", …), which PHP auto-casts to int keys — array_merge() would
        // renumber those instead of preserving them, silently corrupting the rules.
        $validated = $request->validate($rules + $this->fieldBlockRules($leadForm, $project));

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

        // The product picker posts a marker so removing every product clears the list
        if ($request->boolean('products_present')) {
            $validated['products'] = $validated['products'] ?? [];
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
    private function fieldBlockRules(?LeadForm $leadForm, Project $project): array
    {
        if (!$leadForm) {
            return [];
        }

        $customFields = $this->customFieldService->listKeyedById($project);

        $rules = [];
        foreach ($leadForm->fieldBlocksWithPaths() as $path => $block) {
            $fieldKey = (string) ($block['field'] ?? '');
            if ($fieldKey === 'name') {
                continue; // Name has its own fixed input/rule above.
            }

            $required    = (bool) ($block['required'] ?? false);
            $customField = str_starts_with($fieldKey, 'custom:') ? ($customFields[(int) substr($fieldKey, 7)] ?? null) : null;

            if ($customField && $customField->isMultiValue()) {
                $rules[$path]       = [$required ? 'required' : 'nullable', 'array'];
                $rules["{$path}.*"] = ['string', 'max:200'];
                continue;
            }

            $rules[$path] = array_merge(
                [$required ? 'required' : 'nullable'],
                $fieldKey === 'email' ? ['email'] : ['string', 'max:2000'],
            );
        }

        return $rules;
    }
}
