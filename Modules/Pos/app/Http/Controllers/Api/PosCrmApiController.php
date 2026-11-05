<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
use Modules\CRM\Services\CrmNotifier;
use Modules\CRM\Services\CrmOrgService;
use Modules\CRM\Services\CrmPermissionService;
use Modules\CRM\Services\CustomerProfileService;
use Modules\CRM\Services\CustomStatusService;
use Modules\CRM\Services\FollowUpService;
use Modules\CRM\Services\LeadScoreService;
use Modules\CRM\Services\IntakeResult;
use Modules\CRM\Services\LeadAssignmentService;
use Modules\CRM\Services\LeadAuditService;
use Modules\CRM\Services\LeadIntakeService;
use Modules\CRM\Services\LeadCustomFieldService;
use Modules\CRM\Services\LeadFormService;
use Modules\CRM\Services\LeadQuotationService;
use Modules\CRM\Services\LeadService;
use Modules\CRM\Services\LeadSourceService;
use Modules\CRM\Services\LeadStageMailTemplateService;
use Modules\CRM\Services\LeadStageService;
use Modules\CRM\Services\LostReasonService;
use Modules\CRM\Services\ProjectService;
use Modules\CRM\Services\StageEntryActionService;
use Modules\CRM\Services\StageMoveRequestService;
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
        private readonly FollowUpService             $followUps,
        private readonly LeadScoreService            $scores,
        private readonly CustomStatusService         $customStatuses,
        private readonly StageMoveRequestService     $moveRequests,
        private readonly CrmNotifier                 $notifier,
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
            ->with('department:id,name')
            ->withCount('leads')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'status', 'department_id']));

        // What waits on this user in each pipeline — the card's corner badge
        $unread = $access->userId ? $this->notifier->unreadByProject($business, $access->userId) : [];
        $due    = $this->followUps->dueForUserByProject($business, $access);

        foreach ($rows as $project) {
            $project->attention = [
                'unread'         => $unread[$project->id]['count'] ?? 0,
                'follow_ups_due' => $due[$project->id]['due'] ?? 0,
                'overdue'        => $due[$project->id]['overdue'] ?? 0,
                'notifications'  => $unread[$project->id]['items'] ?? [],
                'follow_ups'     => $due[$project->id]['items'] ?? [],
            ];
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
            ->when(array_key_exists((string) $request->query('score'), Lead::SCORES), fn ($q) => $q->where('score', $request->query('score')))
            ->with(['customFieldValues:id,lead_id,custom_field_id,value', 'createdBy:id,name', 'assignedTo:id,name', 'products:id,name', 'assignees.user:id,name', 'stage'])
            ->orderByDesc('id')
            ->get(['id', 'project_id', 'department_id', 'division_id', 'stage_id', 'form_id', 'name', 'company', 'email', 'phone', 'source', 'source_id',
                'estimated_value', 'score', 'expected_close_date', 'notes', 'product_interest_note', 'lost_reason_id', 'lost_reason',
                'on_hold_from_stage_id', 'closed_at', 'assigned_to', 'created_by', 'created_at', 'updated_at',
                'needs_review', 'review_reason', 'verified_at', 'forwarded_at', 'customer_id', 'converted_at', 'checklist_done']);

        $workable = $this->policy->workableLeadIds($access, $leads);

        // Mark leads that already exist as customers (matched by email or phone)
        $emails = $leads->pluck('email')->filter()->unique()->values()->all();
        $phones = $leads->pluck('phone')->filter()->unique()->values()->all();

        $customerEmails = $emails
            ? Customer::where('business_id', $business->id)->whereIn('email', $emails)->pluck('email')->map(fn($e) => strtolower($e))->flip()
            : collect();
        $customerPhones = $phones
            ? Customer::where('business_id', $business->id)->whereIn('phone', $phones)->pluck('phone')->flip()
            : collect();

        $sellPrices = $this->fifoSellPrices($business, $leads->flatMap(fn ($l) => $l->products->pluck('id'))->unique()->values()->all());

        // Follow-up badge (next due / overdue in red) and the suggested Hot / Warm / Cold hint
        $followUps = $this->followUps->summaryForLeads($business, $leads->pluck('id')->map(fn ($id) => (int) $id)->all());
        $suggested = $this->scores->suggestFor($leads);
        // When each lead entered its stage, for the stage's "too long here" flag
        $enteredAt = app(StageEntryActionService::class)->enteredAt($leads);
        // A deal's value: the accepted quotation total, otherwise the estimated value — same as the CRM dashboard
        $acceptedTotals = app(LeadQuotationService::class)->acceptedTotals($leads->pluck('id')->map(fn ($id) => (int) $id)->all());
        // Moves waiting for the head's approval
        $pendingMoves = $this->moveRequests->pendingByLead($leads->pluck('id')->map(fn ($id) => (int) $id)->all());
        // This user's unread notifications on each lead — the card's bell
        $unread = $access->userId
            ? $this->notifier->unreadByLead($business, $access->userId, $leads->pluck('id')->map(fn ($id) => (int) $id)->all())
            : [];

        $leads = $leads->map(function ($lead) use ($customerEmails, $customerPhones, $access, $workable, $sellPrices, $followUps, $suggested, $enteredAt, $acceptedTotals, $pendingMoves, $unread) {
            $byEmail = $lead->email && $customerEmails->has(strtolower($lead->email));
            $byPhone = $lead->phone && $customerPhones->has($lead->phone);
            $lead->is_customer    = $lead->customer_id !== null || $byEmail || $byPhone;
            $lead->custom_fields  = $lead->customFieldValues->pluck('value', 'custom_field_id');
            $lead->created_by_name = $lead->createdBy?->name;
            $lead->assigned_to_name = $lead->assignedTo?->name;
            // Everyone who sees the lead sees who is working it (primary first)
            $lead->assignee_list  = $this->assignments->present($lead->assignees);
            $lead->product_items  = $this->presentLeadProducts($lead, $sellPrices);
            $lead->follow_up       = $followUps[$lead->id] ?? null;
            $lead->suggested_score = $suggested[$lead->id] ?? null;
            $lead->stage_entered_at = $enteredAt[$lead->id] ?? null;
            $lead->deal_value       = $acceptedTotals[$lead->id] ?? ($lead->estimated_value !== null ? (float) $lead->estimated_value : null);
            $lead->pending_move     = $pendingMoves[$lead->id] ?? null;
            $lead->unread           = $unread[$lead->id] ?? null;
            $lead->can = [
                'edit'       => isset($workable[$lead->id]) && $access->can('leads_edit'),
                'move'       => isset($workable[$lead->id]) && $access->can('leads_move'),
                'delete'     => $this->policy->canDelete($access, $lead),
                'assign'     => $this->policy->canAssign($access, $lead),
                'approve'    => $this->policy->canApprove($access, $lead),
                'forward'    => $this->policy->canForward($access, $lead),
                'contribute' => $this->policy->canContribute($access, $lead),
                'convert'    => isset($workable[$lead->id]) && $access->can('leads_edit'),
                'close'      => isset($workable[$lead->id]) && $access->can('leads_close'),
                'reopen'     => $this->policy->canReopen($access, $lead),
            ];
            unset($lead->customFieldValues);
            $lead->unsetRelation('stage');
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
                'value_total'  => (float) $stageLeads->sum('deal_value'),
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
                'custom_statuses'    => $this->customStatuses->present($this->customStatuses->listForBusiness($business)),
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
            $target = LeadStage::where('project_id', $lead->project_id)->find((int) $validated['stage_id']);
            if ($target !== null) {
                $this->abortUnlessMayLeaveClosed($access, $lead, $target);
            }
            // The rest is saved now; the move itself waits for the head
            if ($target?->needs_approval && ! $this->policy->canApprove($access, $lead)) {
                $approvalTarget = $target;
                $validated['stage_id'] = $lead->stage_id;
            }
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
        $pending = isset($approvalTarget)
            ? $this->moveRequests->request($lead, $approvalTarget, $request->user()?->id, $validated)
            : null;

        return response()->json([
            'data'    => $lead->load('stage')->toArray() + ['assignee_list' => $this->assignments->present($this->assignments->assignees($lead))],
            'message' => $pending ? 'Saved. Moving to "' . $approvalTarget->name . '" needs approval — the head has been asked.' : null,
            'pending_move' => $pending ? $this->moveRequests->pendingByLead([(int) $lead->id])[(int) $lead->id] ?? null : null,
        ]);
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
            // Details the stage may ask for on entry (LeadStage::ENTRY_FIELDS)
            'follow_up_at'        => ['nullable', 'date'],
            'expected_close_date' => ['nullable', 'date'],
            'estimated_value'     => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        // Ensure stage belongs to the same project
        $stage = LeadStage::where('project_id', $lead->project_id)->findOrFail($validated['stage_id']);
        $this->abortUnlessStageVisible($request, $business, $lead->project_id, $stage->id);
        $this->abortUnlessMayLeaveClosed($access, $lead, $stage);

        // Only the head / an admin moves straight into a stage that needs approval; others ask (202)
        if ($stage->needs_approval && ! $this->policy->canApprove($access, $lead)) {
            $this->moveRequests->request($lead, $stage, $request->user()?->id, $validated);

            return response()->json([
                'message'      => 'Moving to "' . $stage->name . '" needs approval — the head has been asked.',
                'pending_move' => $this->moveRequests->pendingByLead([(int) $lead->id])[(int) $lead->id] ?? null,
            ], 202);
        }

        $lead = $this->leads->moveStage($lead, $stage->id, $request->user()?->id, $validated);

        return response()->json(['data' => $lead->load('stage')]);
    }

    /** Tick / untick an item of the checklist of the stage the lead is in. */
    public function setLeadChecklistItem(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access = $this->abortUnlessCrm($request, $business, 'leads_edit');

        $lead = $this->visibleLead($request, $business, $leadId);
        $this->abortUnlessAllowed($this->policy->canEdit($access, $lead));

        $validated = $request->validate([
            'item' => ['required', 'string', 'max:120'],
            'done' => ['required', 'boolean'],
        ]);
        $lead = $this->leads->setChecklistItem($lead, $validated['item'], (bool) $validated['done']);

        return response()->json(['data' => ['checklist_done' => $lead->checklist_done ?? []]]);
    }

    /** The head / an admin lets a waiting stage move through. */
    public function approveStageMove(Request $request, int $leadId, int $requestId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access = $this->abortUnlessCrm($request, $business, 'leads_view');

        $lead = $this->visibleLead($request, $business, $leadId);
        $this->abortUnlessAllowed($this->policy->canApprove($access, $lead));

        $lead = $this->moveRequests->approve($this->moveRequests->pendingForLead($lead, $requestId), $request->user()?->id);

        return response()->json(['message' => 'Approved — the lead was moved.', 'data' => $lead->load('stage')]);
    }

    public function rejectStageMove(Request $request, int $leadId, int $requestId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access = $this->abortUnlessCrm($request, $business, 'leads_view');

        $lead = $this->visibleLead($request, $business, $leadId);
        $this->abortUnlessAllowed($this->policy->canApprove($access, $lead));

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $this->moveRequests->reject($this->moveRequests->pendingForLead($lead, $requestId), $request->user()?->id, $validated['note'] ?? null);

        return response()->json(['message' => 'Rejected — the lead stays where it is.']);
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

    /**
     * GET crm/overview — the counts on the CRM Overview tiles, without loading the lists.
     * A count the user may not see is null.
     */
    public function overview(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->crmAccess($request, $business);

        return response()->json(['data' => [
            'contacts'   => $access->can('contacts_view') ? Customer::query()->where('business_id', $business->id)->count() : null,
            'open_tasks' => $access->can('tasks_view') ? $this->tasks->countForBusiness($business, 'open', $access) : null,
        ]]);
    }

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
            'is_protected'       => $this->stages->isProtected($s),
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

    /** The stage's behaviour settings: built-in behaviours on/off, plus the rules any stage can add. */
    public function updateStageBehaviours(Request $request, int $projectId, int $stageId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $this->abortUnlessCrm($request, $business, 'stages_manage');
        $project  = $this->manageableProject($request, $business, $projectId);
        $stage    = LeadStage::where('project_id', $project->id)->findOrFail($stageId);
        $this->abortUnlessStageVisible($request, $business, $project->id, $stage->id);

        $validated = $request->validate([
            'disabled_behaviours'   => ['sometimes', 'nullable', 'array'],
            'disabled_behaviours.*' => ['string', Rule::in(array_keys(LeadStage::BUILT_IN_BEHAVIOURS))],
            'needs_approval'        => ['sometimes', 'boolean'],
            'locks_lead'            => ['sometimes', 'boolean'],
            'no_skip'               => ['sometimes', 'boolean'],
            'exit_checklist'        => ['sometimes', 'nullable', 'array', 'max:10'],
            'exit_checklist.*'      => ['nullable', 'string', 'max:120'],
            'requires_reason'       => ['sometimes', 'boolean'],
        ]);

        $stage = $this->stages->updateBehaviours($stage, $validated);

        return response()->json(['message' => 'Stage behaviour saved.', 'data' => $this->presentStage($stage)]);
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

    public function bulkDeleteStages(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $this->abortUnlessCrm($request, $business, 'stages_manage');
        $project  = $this->manageableProject($request, $business, $projectId);

        $ids = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']])['ids'];
        foreach ($ids as $id) {
            $this->abortUnlessStageVisible($request, $business, $project->id, $id);
        }

        $result = $this->stages->bulkDelete($project, $ids);

        return response()->json([
            'message' => "{$result['deleted']} stage(s) deleted.",
            'data'    => $result,
        ]);
    }

    // ── Custom statuses (the business's own stage templates) ──────────────

    public function customStatuses(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrm($request, $business, 'leads_view');

        return response()->json(['data' => $this->customStatuses->present($this->customStatuses->listForBusiness($business))]);
    }

    public function createCustomStatus(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $this->abortUnlessCrm($request, $business, 'stages_manage');

        $status = $this->customStatuses->create($business, $request->validate($this->customStatusRules()));

        return response()->json(['data' => $this->customStatuses->present(collect([$status]))[0]], 201);
    }

    public function updateCustomStatus(Request $request, int $statusId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $this->abortUnlessCrm($request, $business, 'stages_manage');

        $status = $this->customStatuses->update(
            $this->customStatuses->statusForBusiness($business, $statusId),
            $request->validate($this->customStatusRules()),
        );

        return response()->json(['data' => $this->customStatuses->present(collect([$status->loadCount('stages')]))[0]]);
    }

    /** Stages made from it stay, counting as its standard status. */
    public function deleteCustomStatus(Request $request, int $statusId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $this->abortUnlessCrm($request, $business, 'stages_manage');

        $this->customStatuses->delete($this->customStatuses->statusForBusiness($business, $statusId));

        return response()->json(['message' => 'Status deleted.']);
    }

    /** Add a stage for every standard status the pipeline is missing (deleted ones come back). */
    public function restoreStandardStages(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access   = $this->abortUnlessCrm($request, $business, 'stages_manage');
        $project  = $this->manageableProject($request, $business, $projectId);

        // New stages are slotted between existing ones, which needs the whole pipeline in view
        if ($access->visibleStageIds($project->id) !== null) {
            return response()->json(['message' => 'You need access to every stage of this relation to restore standard stages.'], 403);
        }

        $created = array_values(array_filter($this->stages->seedStandard($project), fn ($r) => $r['action'] === 'created'));

        return response()->json([
            'message' => count($created) . ' standard stage(s) added.',
            'data'    => ['created' => $created],
        ]);
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
        $rules['estimated_value'] = ['nullable', 'numeric', 'min:0', 'max:9999999999'];

        $data   = $request->validate($rules);
        $source = ($data['source'] ?? null) ?: Lead::SOURCE_MANUAL;
        $extra  = array_filter(['estimated_value' => $data['estimated_value'] ?? null], fn ($v) => $v !== null);
        unset($data['source'], $data['estimated_value']);

        $result = $this->forms->submit($form, $data, $source, $request->user()?->id, $extra);

        return $this->intakeResponse($request, $business, $result);
    }

    /**
     * The form behind manual "New Lead" entry — creating an internal "Manual entry" form when
     * the pipeline has none yet. Open to anyone who may add leads, not just form managers, so
     * a salesperson taking a phone call is never blocked on form setup.
     */
    public function manualEntryForm(Request $request, int $projectId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access   = $this->abortUnlessCrm($request, $business, 'leads_create');
        $project  = $this->visibleProject($request, $business, $projectId);
        $this->abortUnlessAllowed($this->policy->canCreateLeadIn($access, $project));

        $form = $this->forms->ensureManualEntryForm($project);

        return response()->json(['data' => [
            'id'               => $form->id,
            'name'             => $form->name,
            'default_stage_id' => $form->default_stage_id,
        ]]);
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
            'custom_status_id' => ['sometimes', 'nullable', 'integer'],
            // A "Custom" stage: behaviour by ticks (system_key is then open / won / lost / on hold)
            'is_custom'       => ['sometimes', 'boolean'],
            'needs_approval'  => ['sometimes', 'boolean'],
            'locks_lead'      => ['sometimes', 'boolean'],
            'no_skip'         => ['sometimes', 'boolean'],
            'exit_checklist'  => ['sometimes', 'nullable', 'array', 'max:10'],
            'exit_checklist.*' => ['nullable', 'string', 'max:120'],
            'requires_reason' => ['sometimes', 'nullable', 'boolean'],
            'icon'        => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^fa-[a-z0-9-]+$/'],
            'description'     => ['sometimes', 'nullable', 'string', 'max:255'],
            // What happens when a lead enters the stage (LeadStage::entryRules)
            'entry_rules'                   => ['sometimes', 'nullable', 'array'],
            'entry_rules.require'           => ['nullable', 'array'],
            'entry_rules.require.*'         => ['string', Rule::in(array_keys(LeadStage::ENTRY_FIELDS))],
            'entry_rules.task'              => ['nullable', 'array'],
            'entry_rules.task.enabled'      => ['nullable', 'boolean'],
            'entry_rules.task.title'        => ['nullable', 'string', 'max:255'],
            'entry_rules.task.due_days'     => ['nullable', 'integer', 'min:0', 'max:365'],
            'entry_rules.notify'            => ['nullable', 'array'],
            'entry_rules.notify.owner'      => ['nullable', 'boolean'],
            'entry_rules.notify.head'       => ['nullable', 'boolean'],
            'entry_rules.notify.user_ids'   => ['nullable', 'array', 'max:20'],
            'entry_rules.notify.user_ids.*' => ['integer'],
            'entry_rules.assign_user_ids'   => ['nullable', 'array', 'max:20'],
            'entry_rules.assign_user_ids.*' => ['integer'],
            'entry_rules.max_days'          => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    private function customStatusRules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:60'],
            'color'           => ['nullable', 'string', 'max:20'],
            'icon'            => ['nullable', 'string', 'max:40', 'regex:/^fa-[a-z0-9-]+$/'],
            'description'     => ['nullable', 'string', 'max:255'],
            'base_key'        => ['required', Rule::in(array_diff(array_keys(LeadStage::STATUSES), LeadStage::RESERVED_STATUSES))],
            'requires_reason' => ['nullable', 'boolean'],
        ] + array_filter($this->stageRules(), fn ($k) => str_starts_with($k, 'entry_rules'), ARRAY_FILTER_USE_KEY);
    }

    private function presentStage(LeadStage $stage): array
    {
        return [
            'id'              => $stage->id,
            'name'            => $stage->name,
            'color'           => $stage->color,
            'icon'            => $stage->icon ?: LeadStage::statusIcon($stage->system_key),
            'has_custom_icon' => filled($stage->icon),
            'description'     => $stage->description,
            'is_won'          => (bool) $stage->is_won,
            'is_lost'         => (bool) $stage->is_lost,
            'system_key'      => $stage->system_key,
            'custom_status_id' => $stage->custom_status_id,
            // A stage made from the business's own status shows that name; reports use system_key
            'status_label'    => match (true) {
                (bool) $stage->is_custom            => 'Custom',
                (bool) $stage->custom_status_id     => $stage->customStatus?->name ?? LeadStage::statusLabel($stage->system_key),
                default                             => LeadStage::statusLabel($stage->system_key),
            },
            'is_custom'       => (bool) $stage->is_custom,
            'needs_approval'  => (bool) $stage->needs_approval,
            'locks_lead'      => (bool) $stage->locks_lead,
            'no_skip'         => (bool) $stage->no_skip,
            'exit_checklist'  => $stage->exitChecklist(),
            'requires_reason' => (bool) $stage->requires_reason,
            'entry_rules'     => $stage->entryRules(),
            'is_reserved'     => $stage->isReserved(),
            'is_on_hold'      => $stage->isOnHold(),
            // What the stage does through its standard status, each switched on or off
            'behaviours'      => $stage->builtInBehaviours(),
            'asks_lost_reason' => $stage->isLostStage() && $stage->behaviourOn('lost_reason'),
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
            'follow_up_at'          => ['sometimes', 'nullable', 'date'],
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

    /**
     * @param  array<int, float>  $sellPrices  product id => FIFO selling price (fifoSellPrices)
     * @return list<array{id:int,name:string,quantity:?float}>
     */
    private function presentLeadProducts(Lead $lead, array $sellPrices = []): array
    {
        return $lead->products->map(fn ($p) => [
            'id'         => $p->id,
            'name'       => $p->name,
            'quantity'   => $p->pivot->quantity !== null ? (float) $p->pivot->quantity : null,
            // A quotation started from the lead pre-fills these — the same price the POS sells at
            'sku'        => $p->sku,
            'unit_price' => array_key_exists((int) $p->id, $sellPrices)
                ? round($sellPrices[(int) $p->id], 2)
                : ($p->unit_price !== null ? round((float) $p->unit_price, 2) : null),
        ])->values()->all();
    }

    /**
     * Selling price of each product's next in-stock FIFO layer (as PosCatalogService prices the
     * POS); products without such a layer are left out and fall back to their own unit_price.
     *
     * @param  list<int>  $productIds
     * @return array<int, float>
     */
    private function fifoSellPrices(Business $business, array $productIds): array
    {
        $prices = [];
        foreach (array_chunk($productIds, 1000) as $chunk) {
            DB::table('product_stock_layers')
                ->where('business_id', $business->id)
                ->whereIn('product_id', $chunk)
                ->where('quantity_remaining', '>', 0)
                ->whereNotNull('selling_unit_price')
                ->orderBy('received_at')
                ->orderBy('id')
                ->get(['product_id', 'selling_unit_price'])
                ->each(function ($row) use (&$prices) {
                    $prices[(int) $row->product_id] ??= (float) $row->selling_unit_price;
                });
        }

        return $prices;
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

    /** A task on a lead is worked by whoever works that lead; other tasks by any CRM user who can change things. */
    private function abortUnlessTaskWorkable(Request $request, Business $business, Task $task): void
    {
        $access = $this->crmAccess($request, $business);
        $lead   = $task->subject_type === (new Lead)->getMorphClass() ? $task->subject : null;

        $this->abortUnlessAllowed($lead instanceof Lead ? $this->policy->canWork($access, $lead) : ! $access->isReadOnly());
    }

    private function abortUnlessAllowed(bool $allowed): void
    {
        if (! $allowed) {
            throw new HttpResponseException(
                response()->json(['message' => 'You do not have permission to perform this action.'], 403)
            );
        }
    }

    /** A won / lost lead only leaves its stage when its head or an admin reopens it (LeadPolicy::canReopen). */
    private function abortUnlessMayLeaveClosed(CrmAccess $access, Lead $lead, LeadStage $to): void
    {
        $lead->loadMissing('stage');
        if (! $lead->isOpen() && (int) $to->id !== (int) $lead->stage_id && ! $this->policy->canReopen($access, $lead)) {
            throw new HttpResponseException(
                response()->json(['message' => 'This lead is closed. Only the department head or an admin can reopen it.'], 403)
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
