<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\Division;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadAuditLog;
use Modules\CRM\Models\LeadCustomField;
use Modules\CRM\Models\LeadForm;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\LeadStageMailTemplate;
use Modules\CRM\Models\Project;
use Modules\CRM\Models\Task;
use Modules\CRM\Policies\LeadPolicy;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CrmOrgService;
use Modules\CRM\Services\CrmPermissionService;
use Modules\CRM\Services\CustomerProfileService;
use Modules\CRM\Services\IntakeResult;
use Modules\CRM\Services\LeadAssignmentService;
use Modules\CRM\Services\LeadAuditService;
use Modules\CRM\Services\LeadIntakeService;
use Modules\CRM\Services\LeadCustomFieldService;
use Modules\CRM\Services\LeadFormService;
use Modules\CRM\Services\LeadService;
use Modules\CRM\Services\LeadSourceService;
use Modules\CRM\Services\LeadStageMailTemplateService;
use Modules\CRM\Services\LeadStageService;
use Modules\CRM\Services\LostReasonService;
use Modules\CRM\Services\ProjectService;
use Modules\CRM\Services\TaskService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Customer;

class PosCrmApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly ProjectService              $projects,
        private readonly LeadService                 $leads,
        private readonly LeadStageService            $stages,
        private readonly TaskService                 $tasks,
        private readonly LeadFormService             $forms,
        private readonly LeadCustomFieldService      $customFields,
        private readonly LeadStageMailTemplateService $stageMailTemplates,
        private readonly CrmPermissionService        $crmPermissions,
        private readonly LeadAuditService            $audit,
        private readonly LeadPolicy                  $policy,
        private readonly CrmOrgService               $org,
        private readonly LostReasonService           $lostReasons,
        private readonly LeadSourceService           $leadSources,
        private readonly LeadIntakeService           $intake,
        private readonly LeadAssignmentService       $assignments,
        private readonly CustomerProfileService      $customerProfiles,
    ) {}

    private ?CrmAccess $crmAccess = null;

    // ── Projects ─────────────────────────────────────────────────────────

    public function projects(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->abortUnlessCrm($request, $business, 'relations_view');

        $rows = $this->policy->visibleProjects($access, Project::query()
            ->where('business_id', $business->id)
            ->where('status', Project::STATUS_ACTIVE)
            ->withCount('leads')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'status', 'department_id']));

        foreach ($rows as $project) {
            // Restricted users only count the leads in stages they can see
            $stageIds = $access->visibleStageIds($project->id);
            if ($stageIds !== null) {
                $project->leads_count = Lead::where('project_id', $project->id)->whereIn('stage_id', $stageIds)->count();
            }
            $project->can = [
                'manage'       => $this->policy->canManageProject($access, $project),
                'create_leads' => $this->policy->canCreateLeadIn($access, $project),
                'work_all'     => $this->policy->worksWholeProject($access, $project),
            ];
        }

        return response()->json(['data' => $rows]);
    }

    public function createProject(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $this->abortUnlessCrm($request, $business, 'relations_create');

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $project = $this->projects->create($business, $validated);

        return response()->json(['data' => $project], 201);
    }

    public function deleteProject(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $this->abortUnlessCrm($request, $business, 'relations_delete');
        $project  = $this->visibleProject($request, $business, $projectId);

        try {
            $this->projects->delete($project);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->errors()['project'][0] ?? 'Cannot delete relation.'], 422);
        }

        return response()->json(['message' => 'Relation deleted.']);
    }

    // ── Pipeline ─────────────────────────────────────────────────────────

    public function pipeline(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->abortUnlessCrm($request, $business, 'leads_view');

        $project = $this->visibleProject($request, $business, $projectId);

        $allStages   = $access->filterStages($project->id, $this->stages->listForProject($project));
        $defaultForm = $this->forms->defaultForProject($project);

        $leads = Lead::query()
            ->where('project_id', $project->id)
            ->whereIn('stage_id', $allStages->pluck('id'))
            ->visibleWith($access)
            ->with(['customFieldValues:id,lead_id,custom_field_id,value', 'createdBy:id,name', 'assignedTo:id,name', 'products:id,name', 'assignees.user:id,name'])
            ->orderByDesc('id')
            ->get(['id', 'project_id', 'department_id', 'division_id', 'stage_id', 'form_id', 'name', 'company', 'email', 'phone', 'source', 'source_id',
                'estimated_value', 'score', 'expected_close_date', 'notes', 'product_interest_note', 'lost_reason_id', 'lost_reason',
                'on_hold_from_stage_id', 'closed_at', 'assigned_to', 'created_by', 'created_at', 'updated_at',
                'needs_review', 'review_reason', 'verified_at', 'forwarded_at', 'customer_id', 'converted_at']);

        $editable = $this->policy->editableLeadIds($access, $leads);

        // Mark leads that already exist as customers (matched by email or phone)
        $emails = $leads->pluck('email')->filter()->unique()->values()->all();
        $phones = $leads->pluck('phone')->filter()->unique()->values()->all();

        $customerEmails = $emails
            ? Customer::where('business_id', $business->id)->whereIn('email', $emails)->pluck('email')->map(fn($e) => strtolower($e))->flip()
            : collect();
        $customerPhones = $phones
            ? Customer::where('business_id', $business->id)->whereIn('phone', $phones)->pluck('phone')->flip()
            : collect();

        $leads = $leads->map(function ($lead) use ($customerEmails, $customerPhones, $access, $editable) {
            $byEmail = $lead->email && $customerEmails->has(strtolower($lead->email));
            $byPhone = $lead->phone && $customerPhones->has($lead->phone);
            $lead->is_customer    = $lead->customer_id !== null || $byEmail || $byPhone;
            $lead->custom_fields  = $lead->customFieldValues->pluck('value', 'custom_field_id');
            $lead->created_by_name = $lead->createdBy?->name;
            $lead->assigned_to_name = $lead->assignedTo?->name;
            // Everyone who sees the lead sees who is working it (primary first)
            $lead->assignee_list  = $this->assignments->present($lead->assignees);
            $lead->product_items  = $this->presentLeadProducts($lead);
            $lead->can = [
                'edit'       => isset($editable[$lead->id]),
                'move'       => isset($editable[$lead->id]) && $access->can('leads_move'),
                'delete'     => $this->policy->canDelete($access, $lead),
                'assign'     => $this->policy->canAssign($access, $lead),
                'approve'    => $this->policy->canApprove($access, $lead),
                'forward'    => $this->policy->canForward($access, $lead),
                'contribute' => $this->policy->canContribute($access, $lead),
                'convert'    => isset($editable[$lead->id]) && $access->can('leads_edit'),
            ];
            unset($lead->customFieldValues);
            $lead->unsetRelation('createdBy');
            $lead->unsetRelation('assignedTo');
            $lead->unsetRelation('products');
            $lead->unsetRelation('assignees');
            return $lead;
        });

        $groupedLeads = $leads->groupBy('stage_id');

        $columns = $allStages->map(function (LeadStage $stage) use ($groupedLeads) {
            $stageLeads = $groupedLeads->get($stage->id, collect());
            return $this->presentStage($stage) + [
                'leads_count'  => $stageLeads->count(),
                'value_total'  => (float) $stageLeads->sum('estimated_value'),
                'leads'        => $stageLeads->values(),
            ];
        });

        return response()->json([
            'data' => [
                'project'            => ['id' => $project->id, 'name' => $project->name, 'department_id' => $project->department_id],
                'can'                => [
                    'manage'       => $this->policy->canManageProject($access, $project),
                    'create_leads' => $this->policy->canCreateLeadIn($access, $project),
                    'work_all'     => $this->policy->worksWholeProject($access, $project),
                ],
                'columns'            => $columns,
                'stages'             => $allStages->map(fn ($s) => $this->presentStage($s))->values(),
                'default_form'       => $defaultForm ? [
                    'id'               => $defaultForm->id,
                    'name'             => $defaultForm->name,
                    'default_stage_id' => $defaultForm->default_stage_id,
                ] : null,
                // Only those who may assign get the list of people to assign to
                'assignable_users'   => $this->policy->canAssignIn($access, $project)
                    ? $this->org->assignableUsers($business, $project->department_id)->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()
                    : [],
                'custom_data_count'  => $access->can('custom_data_view') ? $this->leads->customDataLeadCount($project) : 0,
                'sources'            => Lead::sourceOptions(),
                'statuses'           => LeadStage::statusOptions(),
                'scores'             => collect(Lead::SCORES)->map(fn ($s, $key) => ['key' => $key, 'label' => $s[0], 'color' => $s[1]])->values(),
                'lost_reasons'       => $this->lostReasons->present($this->lostReasons->listForBusiness($business)),
                // Divisions of the pipeline's department, for those who place leads in one
                'divisions'          => $project->department_id && $this->policy->canAssignIn($access, $project)
                    ? Division::query()->where('business_id', $business->id)->where('department_id', $project->department_id)
                        ->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                    : [],
            ],
        ]);
    }

    /** The standard statuses (stages carry one of these keys). */
    public function statuses(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrm($request, $business, 'leads_view');

        return response()->json(['data' => LeadStage::statusOptions()]);
    }

    /** Active reasons a lead can be lost for — picked when moving a lead to a lost stage. */
    public function lostReasons(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrm($request, $business, 'leads_view');

        return response()->json(['data' => $this->lostReasons->present($this->lostReasons->listForBusiness($business))]);
    }

    /** The business's lead sources (manual, web form, CSV, Google Form, Facebook, WhatsApp, …). */
    public function leadSources(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrm($request, $business, 'leads_view');

        return response()->json(['data' => $this->leadSources->present($this->leadSources->listForBusiness($business))]);
    }

    /**
     * Leads submitted through a "Custom Data Entry Form" (LeadForm::TYPE_CUSTOM_DATA) for this
     * project, grouped by the form they came from — each form has its own set of fields, so the
     * "Custom Data" tab renders one spreadsheet-style table per form (columns = that form's
     * fields, one row per submission). These leads still also appear on the Pipeline.
     */
    public function customDataLeads(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->abortUnlessCrm($request, $business, 'custom_data_view');
        $project  = $this->visibleProject($request, $business, $projectId);

        $groups = $this->leads->customDataLeadsForProject($project)
            ->filter(fn (Lead $lead) => $this->policy->canView($access, $lead))
            ->groupBy('form_id')
            ->map(function ($leadsForForm) {
                $form = $leadsForForm->first()->form;

                return [
                    'form' => [
                        'id'     => $form?->id,
                        'name'   => $form?->name ?? 'Deleted form',
                        'blocks' => $form?->blocks ?? [],
                    ],
                    'leads' => $leadsForForm->map(fn (Lead $lead) => [
                        'id'                  => $lead->id,
                        'stage_id'            => $lead->stage_id,
                        'stage_name'          => $lead->stage?->name,
                        'form_id'             => $lead->form_id,
                        'name'                => $lead->name,
                        'company'             => $lead->company,
                        'email'               => $lead->email,
                        'phone'               => $lead->phone,
                        'source'              => $lead->source,
                        'estimated_value'     => $lead->estimated_value,
                        'expected_close_date' => $lead->expected_close_date,
                        'notes'               => $lead->notes,
                        'custom_fields'       => $lead->customFieldValues->pluck('value', 'custom_field_id'),
                        'created_at'          => $lead->created_at?->toIso8601String(),
                    ])->values(),
                ];
            })
            ->values();

        return response()->json(['data' => $groups]);
    }

    /**
     * Contacts derived from this relation's own leads — one row per lead, since a lead already
     * carries its own name/company/email/phone and doesn't have to be linked to a POS Customer
     * to count as a contact here. Backs the relation detail "Contacts" tab (search + stage filter).
     */
    public function projectContacts(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->abortUnlessCrm($request, $business, 'contacts_view');
        $project  = $this->visibleProject($request, $business, $projectId);

        $search  = trim((string) $request->query('q', ''));
        $stageId = $request->query('stage_id');
        $source  = (string) $request->query('source', '');
        $visibleStageIds = $access->visibleStageIds($project->id);

        $leads = Lead::query()
            ->where('project_id', $project->id)
            ->visibleWith($access)
            ->when($visibleStageIds !== null, fn ($q) => $q->whereIn('stage_id', $visibleStageIds))
            ->when(filled($search), fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->when(filled($stageId), fn ($q) => $q->where('stage_id', $stageId))
            ->when(filled($source), fn ($q) => $q->where('source', $source))
            ->with('stage:id,name,color')
            ->orderByDesc('id')
            ->get(['id', 'stage_id', 'customer_id', 'name', 'company', 'email', 'phone', 'source']);

        $leadIds = $leads->pluck('id');

        $openTaskCounts = Task::query()
            ->where('business_id', $business->id)
            ->where('subject_type', Lead::class)
            ->whereIn('subject_id', $leadIds)
            ->where('status', Task::STATUS_PENDING)
            ->selectRaw('subject_id, count(*) as open_tasks')
            ->groupBy('subject_id')
            ->pluck('open_tasks', 'subject_id');

        $lastActivityAt = Activity::query()
            ->where('business_id', $business->id)
            ->where('subject_type', Lead::class)
            ->whereIn('subject_id', $leadIds)
            ->selectRaw('subject_id, max(occurred_at) as last_at')
            ->groupBy('subject_id')
            ->pluck('last_at', 'subject_id');

        $data = $leads->map(fn (Lead $lead) => [
            'id'            => $lead->id,
            'name'          => $lead->name,
            'company'       => $lead->company,
            'email'         => $lead->email,
            'phone'         => $lead->phone,
            'source'        => $lead->source,
            'is_customer'   => (bool) $lead->customer_id,
            'stage_id'      => $lead->stage_id,
            'stage_name'    => $lead->stage?->name,
            'stage_color'   => $lead->stage?->color,
            'open_tasks'    => (int) ($openTaskCounts[$lead->id] ?? 0),
            'last_activity' => $lastActivityAt[$lead->id] ?? null,
        ]);

        return response()->json(['data' => $data]);
    }

    // ── Leads ─────────────────────────────────────────────────────────────

    public function createLead(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $this->abortUnlessCrm($request, $business, 'leads_create');

        $project = $this->visibleProject($request, $business, $projectId);
        $this->abortUnlessAllowed($this->policy->canCreateLeadIn($this->crmAccess($request, $business), $project));

        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'company'             => ['nullable', 'string', 'max:255'],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:50'],
            'source'              => ['nullable', Rule::in(array_keys(Lead::SOURCES))],
            'stage_id'            => ['nullable', 'integer'],
            'estimated_value'     => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'notes'               => ['nullable', 'string', 'max:2000'],
        ] + $this->leadStatusFieldRules());
        $validated['source'] = ($validated['source'] ?? null) ?: Lead::SOURCE_MANUAL;
        // Only those who may assign place the lead in a division
        if (! $this->policy->canAssignIn($this->crmAccess($request, $business), $project)) {
            unset($validated['division_id']);
        }

        $this->abortUnlessStageVisible($request, $business, $project->id, $validated['stage_id'] ?? null);

        $result = $this->intake->capture($validated, $validated['source'], $project, $request->user()?->id);

        return $this->intakeResponse($request, $business, $result);
    }

    public function updateLead(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access = $this->abortUnlessCrm($request, $business, 'leads_edit');

        $lead = $this->visibleLead($request, $business, $leadId);
        $this->abortUnlessAllowed($this->policy->canEdit($access, $lead));

        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'company'             => ['nullable', 'string', 'max:255'],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:50'],
            'source'              => ['sometimes', 'nullable', Rule::in(array_keys(Lead::SOURCES))],
            'stage_id'            => ['nullable', 'integer'],
            'estimated_value'     => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'notes'               => ['nullable', 'string', 'max:2000'],
            'custom_fields'       => ['nullable', 'array'],
            'custom_fields.*'     => ['nullable', 'string', 'max:2000'],
            'assigned_to'         => ['sometimes', 'nullable', 'integer'],
        ] + $this->leadStatusFieldRules());

        if (array_key_exists('division_id', $validated) && (int) $validated['division_id'] !== (int) $lead->division_id) {
            $this->abortUnlessAllowed($this->policy->canAssign($access, $lead));
        }

        if (isset($validated['stage_id']) && (int) $validated['stage_id'] !== (int) $lead->stage_id) {
            $this->abortUnlessAllowed($this->policy->canMove($access, $lead));
            $this->abortUnlessStageVisible($request, $business, $lead->project_id, (int) $validated['stage_id']);
        }

        // The single "Assigned to" field sets the primary assignee (the others stay on the lead):
        // department head / admin only, and only someone of the lead's department
        $assignee       = isset($validated['assigned_to']) ? (int) $validated['assigned_to'] : null;
        $changeAssignee = array_key_exists('assigned_to', $validated)
            && $assignee !== ($lead->assigned_to === null ? null : (int) $lead->assigned_to);
        unset($validated['assigned_to']);
        if ($changeAssignee) {
            $this->abortUnlessAllowed($this->policy->canAssign($access, $lead));
            if ($assignee !== null) {
                try {
                    $this->assignments->assertAssignable($lead, [$assignee]);
                } catch (ValidationException) {
                    throw ValidationException::withMessages(['assigned_to' => 'This person is not a member of the lead\'s department.']);
                }
            }
        }

        $lead = $this->leads->update($lead, $validated, $request->user()?->id);
        if ($changeAssignee) {
            $lead = $this->assignments->setPrimaryAssignee($lead, $assignee, $request->user()?->id);
        }

        return response()->json(['data' => $lead->load('stage')->toArray() + ['assignee_list' => $this->assignments->present($this->assignments->assignees($lead))]]);
    }

    public function moveLead(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access = $this->abortUnlessCrm($request, $business, 'leads_move');

        $lead = $this->visibleLead($request, $business, $leadId);
        $this->abortUnlessAllowed($this->policy->canMove($access, $lead));

        $validated = $request->validate([
            'stage_id'       => ['required', 'integer', 'exists:crm_lead_stages,id'],
            'lost_reason_id' => ['nullable', 'integer'],
            'lost_reason'    => ['nullable', 'string', 'max:255'],
            'reason'         => ['nullable', 'string', 'max:500'],
        ]);

        // Ensure stage belongs to the same project
        $stage = LeadStage::where('project_id', $lead->project_id)->findOrFail($validated['stage_id']);
        $this->abortUnlessStageVisible($request, $business, $lead->project_id, $stage->id);

        $lead = $this->leads->moveStage($lead, $stage->id, $request->user()?->id, $validated);

        return response()->json(['data' => $lead->load('stage')]);
    }

    /** Put the lead in the pipeline's "On Hold" stage, remembering where it was. */
    public function holdLead(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access = $this->abortUnlessCrm($request, $business, 'leads_move');

        $lead = $this->visibleLead($request, $business, $leadId);
        $this->abortUnlessAllowed($this->policy->canMove($access, $lead));

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $lead = $this->leads->putOnHold($lead, $validated['reason'] ?? null, $request->user()?->id);

        return response()->json(['data' => $lead->load('stage')]);
    }

    /** Return an on-hold lead to the stage it was in before it was put on hold. */
    public function resumeLead(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access = $this->abortUnlessCrm($request, $business, 'leads_move');

        $lead = $this->visibleLead($request, $business, $leadId);
        $this->abortUnlessAllowed($this->policy->canMove($access, $lead));

        $lead = $this->leads->resume($lead, $request->user()?->id);

        return response()->json(['data' => $lead->load('stage')]);
    }

    /**
     * Convert an open lead: it is marked won and linked to its customer — the one it is already
     * linked to, else one with the same phone / email, else a new one (same as the web action).
     */
    public function convertLead(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access = $this->abortUnlessCrm($request, $business, 'leads_edit');

        $lead = $this->visibleLead($request, $business, $leadId);
        $this->abortUnlessAllowed($this->policy->canEdit($access, $lead));

        $lead     = $this->leads->convertToCustomer($lead, $request->user()?->id);
        $customer = $lead->customer;

        return response()->json([
            'message' => $customer->wasRecentlyCreated
                ? 'Lead converted to a new customer.'
                : "Lead converted and linked to the existing customer \"{$customer->name}\".",
            'data'    => [
                'lead'     => $lead->load('stage'),
                'customer' => ['id' => $customer->id, 'name' => $customer->name, 'phone' => $customer->phone, 'email' => $customer->email],
                'created'  => $customer->wasRecentlyCreated,
            ],
        ]);
    }

    public function deleteLead(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access = $this->abortUnlessCrm($request, $business, 'leads_delete');

        $lead = $this->visibleLead($request, $business, $leadId);
        $this->abortUnlessAllowed($this->policy->canDelete($access, $lead));

        $this->leads->delete($lead, $request->user()?->id);

        return response()->json(['message' => 'Lead deleted.']);
    }

    /** Full audit trail for one lead — every create/edit/stage move with who, when and from where. */
    public function leadHistory(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrm($request, $business, 'leads_view');

        $lead = $this->visibleLead($request, $business, $leadId);

        return response()->json(['data' => $this->audit->present($this->audit->forLead($lead))]);
    }

    /** Relation-wide audit feed (paginated, filterable by event / user / date range / lead name). */
    public function projectHistory(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->abortUnlessCrm($request, $business, 'leads_view');
        $project  = $this->visibleProject($request, $business, $projectId);

        $filters = $request->validate([
            'event'    => ['nullable', 'string', Rule::in(array_keys(LeadAuditLog::events()))],
            'user_id'  => ['nullable', 'integer'],
            'from'     => ['nullable', 'date'],
            'to'       => ['nullable', 'date'],
            'q'        => ['nullable', 'string', 'max:255'],
            'lead_source' => ['nullable', Rule::in(array_keys(Lead::SOURCES))],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
        ]);

        $page = $this->audit->forProject($project, $filters, $access->visibleStageIds($project->id), (int) ($filters['per_page'] ?? 50));

        return response()->json([
            'data' => $this->audit->present(collect($page->items())),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
                'events'       => LeadAuditLog::events(),
                'users'        => $this->audit->actorsForProject($project),
            ],
        ]);
    }

    /** Stage-movement graph + per-lead journeys for the History "View more" flow window. */
    public function projectHistoryFlow(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->abortUnlessCrm($request, $business, 'leads_view');
        $project  = $this->visibleProject($request, $business, $projectId);

        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'from'    => ['nullable', 'date'],
            'to'      => ['nullable', 'date'],
            'q'       => ['nullable', 'string', 'max:255'],
            'lead_source' => ['nullable', Rule::in(array_keys(Lead::SOURCES))],
        ]);

        return response()->json(['data' => $this->audit->flowForProject($project, $filters, $access->visibleStageIds($project->id))]);
    }

    // ── Contacts ──────────────────────────────────────────────────────────

    public function contacts(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrm($request, $business, 'contacts_view');

        $search = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->where('business_id', $business->id)
            ->when(filled($search), fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email']);

        $customerIds = $customers->pluck('id');

        $openTaskCounts = Task::query()
            ->where('business_id', $business->id)
            ->where('subject_type', Customer::class)
            ->whereIn('subject_id', $customerIds)
            ->where('status', Task::STATUS_PENDING)
            ->selectRaw('subject_id, count(*) as open_tasks')
            ->groupBy('subject_id')
            ->pluck('open_tasks', 'subject_id');

        $lastActivityAt = Activity::query()
            ->where('business_id', $business->id)
            ->where('subject_type', Customer::class)
            ->whereIn('subject_id', $customerIds)
            ->selectRaw('subject_id, max(occurred_at) as last_at')
            ->groupBy('subject_id')
            ->pluck('last_at', 'subject_id');

        $data = $customers->map(fn ($c) => [
            'id'            => $c->id,
            'name'          => $c->name,
            'phone'         => $c->phone,
            'email'         => $c->email,
            'open_tasks'    => (int) ($openTaskCounts[$c->id] ?? 0),
            'last_activity' => $lastActivityAt[$c->id] ?? null,
        ]);

        return response()->json(['data' => $data]);
    }

    /** The 360° customer page: leads, quotations, files, follow-ups, invoices / orders and the timeline. */
    public function customer(Request $request, int $customerId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->abortUnlessCrm($request, $business, 'contacts_view');

        $customer = Customer::where('business_id', $business->id)->findOrFail($customerId);

        return response()->json(['data' => $this->customerProfiles->build($customer, $access)]);
    }

    // ── Tasks ─────────────────────────────────────────────────────────────

    public function tasksList(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access = $this->abortUnlessCrm($request, $business, 'tasks_view');

        $filter = in_array($request->query('filter'), ['open', 'overdue', 'completed'], true)
            ? $request->query('filter')
            : 'open';

        $taskRows = $this->tasks->listForBusiness($business, $filter, $access);

        $data = $taskRows->map(fn (Task $t) => [
            'id'          => $t->id,
            'title'       => $t->title,
            'description' => $t->description,
            'due_at'      => $t->due_at?->toIso8601String(),
            'status'      => $t->status,
            'is_overdue'  => $t->isOverdue(),
            'subject_type' => $t->subject_type,
            'subject_id'   => $t->subject_id,
            'subject_label' => $this->taskSubjectLabel($t),
        ]);

        return response()->json(['data' => $data]);
    }

    public function createTask(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_tasks');
        $this->abortUnlessCrm($request, $business, 'tasks_create');

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_at'      => ['nullable', 'date'],
        ]);

        $task = $this->tasks->create($business, $validated, null, $request->user()?->id);

        return response()->json(['data' => $task], 201);
    }

    public function completeTask(Request $request, int $taskId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_tasks');
        $this->abortUnlessCrm($request, $business, 'tasks_complete');

        $task = Task::where('business_id', $business->id)->findOrFail($taskId);
        $this->abortUnlessTaskWorkable($request, $business, $task);

        $this->tasks->complete($task);

        return response()->json(['message' => 'Task completed.']);
    }

    public function reopenTask(Request $request, int $taskId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_tasks');
        $this->abortUnlessCrm($request, $business, 'tasks_complete');

        $task = Task::where('business_id', $business->id)->findOrFail($taskId);
        $this->abortUnlessTaskWorkable($request, $business, $task);

        $this->tasks->reopen($task);

        return response()->json(['message' => 'Task reopened.']);
    }

    public function deleteTask(Request $request, int $taskId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_tasks');
        $this->abortUnlessCrm($request, $business, 'tasks_delete');

        $task = Task::where('business_id', $business->id)->findOrFail($taskId);
        $this->abortUnlessTaskWorkable($request, $business, $task);

        $this->tasks->delete($task);

        return response()->json(['message' => 'Task deleted.']);
    }

    // ── Stages ───────────────────────────────────────────────────────────

    public function stages(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = $this->visibleProject($request, $business, $projectId);

        $stages = $this->crmAccess($request, $business)
            ->filterStages($project->id, $this->stages->listForProject($project)->loadCount('leads'));

        $stageIdsWithTemplate = LeadStageMailTemplate::where('project_id', $project->id)
            ->pluck('stage_id')
            ->all();

        return response()->json(['data' => $stages->map(fn ($s) => $this->presentStage($s) + [
            'sort_order'         => $s->sort_order,
            'leads_count'        => (int) $s->leads_count,
            'has_mail_template'  => in_array($s->id, $stageIdsWithTemplate, true),
        ])->values()]);
    }

    public function createStage(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $this->abortUnlessCrm($request, $business, 'stages_manage');
        $project  = $this->manageableProject($request, $business, $projectId);

        $validated = $request->validate($this->stageRules());

        $stage = $this->stages->create($project, $validated);

        return response()->json(['data' => $stage], 201);
    }

    public function updateStage(Request $request, int $projectId, int $stageId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $this->abortUnlessCrm($request, $business, 'stages_manage');
        $project  = $this->manageableProject($request, $business, $projectId);
        $stage    = LeadStage::where('project_id', $project->id)->findOrFail($stageId);
        $this->abortUnlessStageVisible($request, $business, $project->id, $stage->id);

        $validated = $request->validate($this->stageRules());

        $stage = $this->stages->update($stage, $validated);

        return response()->json(['data' => $stage]);
    }

    public function deleteStage(Request $request, int $projectId, int $stageId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $this->abortUnlessCrm($request, $business, 'stages_manage');
        $project  = $this->manageableProject($request, $business, $projectId);
        $stage    = LeadStage::where('project_id', $project->id)->findOrFail($stageId);
        $this->abortUnlessStageVisible($request, $business, $project->id, $stage->id);

        try {
            $this->stages->delete($stage);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->errors()['stage'][0] ?? 'Cannot delete stage.'], 422);
        }

        return response()->json(['message' => 'Stage deleted.']);
    }

    public function reorderStages(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access   = $this->abortUnlessCrm($request, $business, 'stages_manage');
        $project  = $this->manageableProject($request, $business, $projectId);

        // Reordering a partial list would collide with the sort order of the hidden stages
        if ($access->visibleStageIds($project->id) !== null) {
            return response()->json(['message' => 'You need access to every stage of this relation to reorder them.'], 403);
        }

        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        $this->stages->reorder($project, $ids);

        return response()->json(['message' => 'Reordered.']);
    }

    // ── Stage mail templates (manual send) ──────────────────────────────────

    public function stageMailTemplate(Request $request, int $projectId, int $stageId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = $this->visibleProject($request, $business, $projectId);
        $stage    = LeadStage::where('project_id', $project->id)->findOrFail($stageId);
        $this->abortUnlessStageVisible($request, $business, $project->id, $stage->id);

        $template = $this->stageMailTemplates->forStage($stage);

        return response()->json([
            'data' => [
                'template'        => $template,
                'recipient_types' => LeadStageMailTemplate::recipientTypes(),
            ],
        ]);
    }

    public function saveStageMailTemplate(Request $request, int $projectId, int $stageId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $this->abortUnlessCrm($request, $business, 'stage_mail');
        $project  = $this->manageableProject($request, $business, $projectId);
        $stage    = LeadStage::where('project_id', $project->id)->findOrFail($stageId);
        $this->abortUnlessStageVisible($request, $business, $project->id, $stage->id);

        $validated = $request->validate([
            'recipient_type'  => ['required', 'string', Rule::in(array_keys(LeadStageMailTemplate::recipientTypes()))],
            'recipient_email' => ['nullable', 'email', 'max:190'],
            'subject'         => ['required', 'string', 'max:200'],
            'body'            => ['required', 'string', 'max:5000'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

        if ($validated['recipient_type'] === LeadStageMailTemplate::RECIPIENT_CUSTOM && !filled($validated['recipient_email'] ?? '')) {
            return response()->json(['message' => 'Enter an email address for a custom recipient.'], 422);
        }

        $template = $this->stageMailTemplates->save($project, $stage, $validated);

        return response()->json(['data' => $template]);
    }

    public function deleteStageMailTemplate(Request $request, int $projectId, int $stageId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $this->abortUnlessCrm($request, $business, 'stage_mail');
        $project  = $this->manageableProject($request, $business, $projectId);
        $stage    = LeadStage::where('project_id', $project->id)->findOrFail($stageId);
        $this->abortUnlessStageVisible($request, $business, $project->id, $stage->id);

        $template = $this->stageMailTemplates->forStage($stage);
        if ($template) {
            $this->stageMailTemplates->delete($template);
        }

        return response()->json(['message' => 'Mail template removed.']);
    }

    public function sendStageMail(Request $request, int $projectId, int $stageId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $this->abortUnlessCrm($request, $business, 'stage_mail');
        $project  = $this->manageableProject($request, $business, $projectId);
        $stage    = LeadStage::where('project_id', $project->id)->findOrFail($stageId);
        $this->abortUnlessStageVisible($request, $business, $project->id, $stage->id);

        $template = $this->stageMailTemplates->forStage($stage);
        if (!$template) {
            return response()->json(['message' => 'Set up a mail template for this stage first.'], 422);
        }

        $result = $this->stageMailTemplates->sendNow($stage, $template);

        return response()->json(['data' => $result]);
    }

    // ── Forms ─────────────────────────────────────────────────────────────

    public function forms(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrm($request, $business, 'forms_view');
        $project  = $this->visibleProject($request, $business, $projectId);

        $rows = $this->forms->listForProject($project);

        return response()->json(['data' => $rows->map(fn (LeadForm $f) => [
            'id'                 => $f->id,
            'name'               => $f->name,
            'type'               => $f->type,
            'is_published'       => $f->is_published,
            'is_default'         => (bool) $f->is_default,
            'public_url'         => $f->publicUrl(),
            'submit_button_text' => $f->submit_button_text,
            'success_message'    => $f->success_message,
            'default_stage_id'   => $f->default_stage_id,
            'default_stage_name' => $f->defaultStage?->name,
            'blocks_count'       => count($f->blocks ?? []),
        ])]);
    }

    public function createForm(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_forms');
        $this->abortUnlessCrm($request, $business, 'forms_manage');
        $project  = $this->manageableProject($request, $business, $projectId);

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'type'              => ['nullable', 'string', Rule::in([LeadForm::TYPE_CUSTOMER, LeadForm::TYPE_SUPPLIER, LeadForm::TYPE_CUSTOM_DATA, LeadForm::TYPE_EMPLOYEE])],
            'template'          => ['nullable', 'string', Rule::in(LeadFormService::templateKeys())],
            'is_default'        => ['nullable', 'boolean'],
            'success_message'   => ['nullable', 'string', 'max:500'],
            'default_stage_id'  => ['nullable', 'integer', Rule::exists('crm_lead_stages', 'id')->where(fn ($q) => $q->where('project_id', $project->id))],
        ]);

        $form = $this->forms->create($project, $validated);

        if ($validated['is_default'] ?? false) {
            $form = $this->forms->toggleDefault($form);
        }

        return response()->json(['data' => [
            'id'                 => $form->id,
            'name'               => $form->name,
            'type'               => $form->type,
            'is_published'       => $form->is_published,
            'is_default'         => (bool) $form->is_default,
            'public_url'         => $form->publicUrl(),
            'blocks'             => $form->blocks,
            'style'              => $form->styleSettings(),
            'submit_button_text' => $form->submit_button_text,
            'success_message'    => $form->success_message,
            'default_stage_id'   => $form->default_stage_id,
        ]], 201);
    }

    public function getForm(Request $request, int $projectId, int $formId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = $this->visibleProject($request, $business, $projectId);
        $form     = LeadForm::where('project_id', $project->id)->findOrFail($formId);

        $customFields = $this->customFields->listForProject($project);
        $stages       = $this->crmAccess($request, $business)->filterStages(
            $project->id,
            $this->stages->listForProject($project)->where('is_won', false)->where('is_lost', false)->values(),
        );

        return response()->json(['data' => [
            'id'                 => $form->id,
            'name'               => $form->name,
            'type'               => $form->type,
            'is_published'       => $form->is_published,
            'is_default'         => (bool) $form->is_default,
            'public_url'         => $form->publicUrl(),
            'blocks'             => $form->blocks ?? [],
            'style'              => $form->styleSettings(),
            'submit_button_text' => $form->submit_button_text,
            'success_message'    => $form->success_message,
            'default_stage_id'   => $form->default_stage_id,
            'stages'             => $stages->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'color' => $s->color]),
            'custom_fields'      => $customFields->map(fn ($cf) => [
                'id'      => $cf->id,
                'name'    => $cf->name,
                'type'    => $cf->type,
                'options' => $cf->optionList(),
            ]),
            'templates'          => $this->forms->templateChoices(),
        ]]);
    }

    public function updateForm(Request $request, int $projectId, int $formId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_forms');
        $this->abortUnlessCrm($request, $business, 'forms_manage');
        $project  = $this->manageableProject($request, $business, $projectId);
        $form     = LeadForm::where('project_id', $project->id)->findOrFail($formId);

        $validated = $request->validate([
            'name'               => ['nullable', 'string', 'max:255'],
            'blocks'             => ['nullable', 'array'],
            'style'              => ['nullable', 'array'],
            'submit_button_text' => ['nullable', 'string', 'max:60'],
            'success_message'    => ['nullable', 'string', 'max:500'],
            'default_stage_id'   => ['nullable', 'integer', Rule::exists('crm_lead_stages', 'id')->where(fn ($q) => $q->where('project_id', $project->id))],
        ]);

        $form = $this->forms->update($form, $validated);

        return response()->json(['data' => [
            'id'                 => $form->id,
            'name'               => $form->name,
            'is_published'       => $form->is_published,
            'is_default'         => (bool) $form->is_default,
            'public_url'         => $form->publicUrl(),
            'blocks'             => $form->blocks ?? [],
            'style'              => $form->styleSettings(),
            'submit_button_text' => $form->submit_button_text,
            'success_message'    => $form->success_message,
            'default_stage_id'   => $form->default_stage_id,
        ]]);
    }

    /**
     * Create a lead from the desktop "New Lead" flow, which fills out the project's default
     * form. Reuses the same block-path validation and mapping as the public web form, so both
     * entry points always agree on required fields and which stage a submission lands on.
     */
    public function submitFormLead(Request $request, int $projectId, int $formId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access   = $this->abortUnlessCrm($request, $business, 'leads_create');
        $project  = $this->visibleProject($request, $business, $projectId);
        $this->abortUnlessAllowed($this->policy->canCreateLeadIn($access, $project));
        $form     = LeadForm::where('project_id', $project->id)->findOrFail($formId);

        $rules = [];
        foreach ($form->fieldBlocksWithPaths() as $path => $block) {
            $required     = (bool) ($block['required'] ?? false);
            $rules[$path] = [$required ? 'required' : 'nullable', 'string', 'max:2000'];

            if (($block['field'] ?? '') === 'email') {
                $rules[$path][] = 'email';
            }
        }

        // Block paths are numeric ("0", "1.2", …), so a 'source' key can't collide with one.
        $rules['source'] = ['nullable', Rule::in(array_keys(Lead::SOURCES))];

        $data   = $request->validate($rules);
        $source = ($data['source'] ?? null) ?: Lead::SOURCE_MANUAL;
        unset($data['source']);

        $result = $this->forms->submit($form, $data, $source, $request->user()?->id);

        return $this->intakeResponse($request, $business, $result);
    }

    public function setDefaultForm(Request $request, int $projectId, int $formId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_forms');
        $this->abortUnlessCrm($request, $business, 'forms_manage');
        $project  = $this->manageableProject($request, $business, $projectId);
        $form     = LeadForm::where('project_id', $project->id)->findOrFail($formId);

        $form = $this->forms->toggleDefault($form);

        return response()->json(['data' => ['id' => $form->id, 'is_default' => (bool) $form->is_default]]);
    }

    public function publishForm(Request $request, int $projectId, int $formId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_forms');
        $this->abortUnlessCrm($request, $business, 'forms_manage');
        $project  = $this->manageableProject($request, $business, $projectId);
        $form     = LeadForm::where('project_id', $project->id)->findOrFail($formId);

        $this->forms->publish($form);

        return response()->json(['message' => 'Form published.', 'is_published' => true]);
    }

    public function unpublishForm(Request $request, int $projectId, int $formId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_forms');
        $this->abortUnlessCrm($request, $business, 'forms_manage');
        $project  = $this->manageableProject($request, $business, $projectId);
        $form     = LeadForm::where('project_id', $project->id)->findOrFail($formId);

        $this->forms->unpublish($form);

        return response()->json(['message' => 'Form unpublished.', 'is_published' => false]);
    }

    public function deleteForm(Request $request, int $projectId, int $formId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_forms');
        $this->abortUnlessCrm($request, $business, 'forms_manage');
        $project  = $this->manageableProject($request, $business, $projectId);
        $form     = LeadForm::where('project_id', $project->id)->findOrFail($formId);

        $this->forms->delete($form);

        return response()->json(['message' => 'Form deleted.']);
    }

    public function formTemplates(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->forms->templateChoices()]);
    }

    // ── Custom Fields ─────────────────────────────────────────────────────

    public function customFieldsList(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $project  = $this->visibleProject($request, $business, $projectId);

        $fields = $this->customFields->listForProject($project);

        return response()->json(['data' => $fields->map(fn ($cf) => [
            'id'         => $cf->id,
            'name'       => $cf->name,
            'type'       => $cf->type,
            'options'    => $cf->optionList(),
            'sort_order' => $cf->sort_order,
        ])]);
    }

    public function createCustomField(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_forms');
        $this->abortUnlessCrm($request, $business, 'custom_fields_manage');
        $project  = $this->manageableProject($request, $business, $projectId);

        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:80'],
            'type'    => ['required', 'string', Rule::in(array_keys(LeadCustomField::types()))],
            'options' => ['nullable', 'string', 'max:2000'],
        ]);

        if (in_array($validated['type'], LeadCustomField::OPTION_TYPES, true) && !filled($validated['options'] ?? '')) {
            throw ValidationException::withMessages(['options' => 'Add at least one option, one per line.']);
        }

        $field = $this->customFields->create($project, [
            'label'   => $validated['name'],
            'type'    => $validated['type'],
            'options' => $validated['options'] ?? '',
        ]);

        return response()->json(['data' => [
            'id'      => $field->id,
            'name'    => $field->name,
            'type'    => $field->type,
            'options' => $field->optionList(),
        ]], 201);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * A stage with its standard status. Older desktop builds only send is_won / is_lost, so
     * system_key stays optional here and the service derives it from those flags.
     */
    private function stageRules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:60'],
            'color'           => ['nullable', 'string', 'max:20'],
            'is_won'          => ['nullable', 'boolean'],
            'is_lost'         => ['nullable', 'boolean'],
            'system_key'      => ['sometimes', 'nullable', Rule::in(array_keys(LeadStage::STATUSES))],
            'requires_reason' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    private function presentStage(LeadStage $stage): array
    {
        return [
            'id'              => $stage->id,
            'name'            => $stage->name,
            'color'           => $stage->color,
            'is_won'          => (bool) $stage->is_won,
            'is_lost'         => (bool) $stage->is_lost,
            'system_key'      => $stage->system_key,
            'status_label'    => LeadStage::statusLabel($stage->system_key),
            'requires_reason' => (bool) $stage->requires_reason,
            'is_reserved'     => $stage->isReserved(),
            'is_on_hold'      => $stage->isOnHold(),
        ];
    }

    /** Lead fields added with the standard statuses; every key is optional for older desktop builds. */
    private function leadStatusFieldRules(): array
    {
        return [
            'source_id'             => ['sometimes', 'nullable', 'integer'],
            'score'                 => ['sometimes', 'nullable', Rule::in(array_keys(Lead::SCORES))],
            'division_id'           => ['sometimes', 'nullable', 'integer'],
            'product_interest_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'products'              => ['sometimes', 'array', 'max:50'],
            'products.*.product_id' => ['required', 'integer'],
            'products.*.quantity'   => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lost_reason_id'        => ['sometimes', 'nullable', 'integer'],
            'lost_reason'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'reason'                => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * 201 for every accepted lead, so older desktop builds keep showing success. `data` is the
     * lead when the user may still see it — it may have gone to the marketing inbox, or be
     * another department's existing lead the enquiry was added to — and null otherwise;
     * `intake` says what happened.
     */
    private function intakeResponse(Request $request, Business $business, IntakeResult $result): JsonResponse
    {
        $lead    = $result->lead->fresh(['stage']);
        $visible = $this->policy->canView($this->crmAccess($request, $business), $lead);

        return response()->json([
            'data'    => $visible ? $lead : null,
            'intake'  => $result->toArray(),
            'message' => $result->message(),
        ], 201);
    }

    /** @return list<array{id:int,name:string,quantity:?float}> */
    private function presentLeadProducts(Lead $lead): array
    {
        return $lead->products->map(fn ($p) => [
            'id'       => $p->id,
            'name'     => $p->name,
            'quantity' => $p->pivot->quantity !== null ? (float) $p->pivot->quantity : null,
        ])->values()->all();
    }

    /** Per-user CRM access (function toggles + stage visibility), resolved once per request. */
    protected function crmAccess(Request $request, Business $business): CrmAccess
    {
        return $this->crmAccess ??= $this->crmPermissions->accessFor($business, $request->user());
    }

    /** 403 unless the user may use this CRM function. */
    private function abortUnlessCrm(Request $request, Business $business, string $key): CrmAccess
    {
        $access = $this->crmAccess($request, $business);
        if (! $access->can($key)) {
            throw new HttpResponseException(
                response()->json(['message' => 'You do not have permission to perform this action.'], 403)
            );
        }

        return $access;
    }

    /** Load a relation the user may see, 404-ing on relations hidden from them. */
    private function visibleProject(Request $request, Business $business, int $projectId): Project
    {
        $project = Project::where('business_id', $business->id)->findOrFail($projectId);
        if (! $this->policy->canViewProject($this->crmAccess($request, $business), $project)) {
            abort(404);
        }

        return $project;
    }

    /** Load a relation whose stages, forms and fields the user may change (its department head, …). */
    private function manageableProject(Request $request, Business $business, int $projectId): Project
    {
        $project = $this->visibleProject($request, $business, $projectId);
        $this->abortUnlessAllowed($this->policy->canManageProject($this->crmAccess($request, $business), $project));

        return $project;
    }

    /** Load a lead the user may see, 404-ing on leads hidden from them. */
    private function visibleLead(Request $request, Business $business, int $leadId): Lead
    {
        $lead = Lead::where('business_id', $business->id)->findOrFail($leadId);
        if (! $this->policy->canView($this->crmAccess($request, $business), $lead)) {
            abort(404);
        }

        return $lead;
    }

    /** A task on a lead is worked by whoever may edit that lead; other tasks by any CRM user who can change things. */
    private function abortUnlessTaskWorkable(Request $request, Business $business, Task $task): void
    {
        $access = $this->crmAccess($request, $business);
        $lead   = $task->subject_type === (new Lead)->getMorphClass() ? $task->subject : null;

        $this->abortUnlessAllowed($lead instanceof Lead ? $this->policy->canEdit($access, $lead) : ! $access->isReadOnly());
    }

    private function abortUnlessAllowed(bool $allowed): void
    {
        if (! $allowed) {
            throw new HttpResponseException(
                response()->json(['message' => 'You do not have permission to perform this action.'], 403)
            );
        }
    }

    /** 403 unless the stage is visible to the user. Null stage ids pass (service picks the default). */
    private function abortUnlessStageVisible(Request $request, Business $business, int $projectId, ?int $stageId): void
    {
        if ($stageId === null) {
            return;
        }
        if (! $this->crmAccess($request, $business)->canSeeStage($projectId, $stageId)) {
            throw new HttpResponseException(
                response()->json(['message' => 'You do not have access to this stage.'], 403)
            );
        }
    }

    private function taskSubjectLabel(Task $task): ?string
    {
        if ($task->subject_type === null) {
            return null;
        }

        if ($task->subject_type === Customer::class || str_ends_with($task->subject_type, 'Customer')) {
            return $task->subject instanceof Customer ? $task->subject->name : null;
        }

        if ($task->subject_type === Lead::class || str_ends_with($task->subject_type, 'Lead')) {
            return $task->subject instanceof Lead ? $task->subject->name : null;
        }

        return null;
    }
}
