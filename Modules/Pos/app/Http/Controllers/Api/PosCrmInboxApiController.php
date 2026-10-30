<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Lead;
use Modules\CRM\Policies\LeadPolicy;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CrmPermissionService;
use Modules\CRM\Services\LeadIntakeService;
use Modules\CRM\Services\MarketingInboxService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

/** The marketing inbox for the desktop: list, clean up, verify, forward, clear review, merge. */
class PosCrmInboxApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    private ?CrmAccess $crmAccess = null;

    public function __construct(
        private readonly MarketingInboxService $inbox,
        private readonly LeadIntakeService $intake,
        private readonly CrmPermissionService $crmPermissions,
        private readonly LeadPolicy $policy,
    ) {}

    /** GET /api/v1/pos/crm/inbox?filter=all|review|unverified|verified&q= */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->abortUnlessInbox($request, $business);

        $filter = array_key_exists((string) $request->query('filter'), MarketingInboxService::FILTERS) ? (string) $request->query('filter') : 'all';
        $intake = $this->intake->intakeProject($business);

        $leads = $this->inbox->list($business, $access, $filter, trim((string) $request->query('q', '')));

        return response()->json([
            'data' => [
                'leads'           => $leads->map(fn (Lead $lead) => $this->inbox->present($lead, $access, withDuplicates: (bool) $lead->needs_review))->values(),
                'counts'          => $this->inbox->counts($business, $access),
                'filters'         => collect(MarketingInboxService::FILTERS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
                'intake_project'  => $intake ? ['id' => $intake->id, 'name' => $intake->name] : null,
                'forward_targets' => $this->inbox->forwardTargets($business),
                'sources'         => Lead::sourceOptions(),
            ],
        ]);
    }

    /** GET /api/v1/pos/crm/inbox/leads/{lead} — one lead with its matching leads and customers. */
    public function show(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->abortUnlessInbox($request, $business);
        $lead     = $this->inboxLead($request, $business, $leadId);

        return response()->json(['data' => $this->inbox->present($lead, $access, withDuplicates: true)]);
    }

    /** PUT /api/v1/pos/crm/inbox/leads/{lead} — clean up the contact details. */
    public function update(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access = $this->abortUnlessInbox($request, $business);
        $lead   = $this->inboxLead($request, $business, $leadId);
        $this->abortUnlessAllowed($this->policy->canEdit($access, $lead));

        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:150'],
            'company'               => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'                 => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone'                 => ['sometimes', 'nullable', 'string', 'max:50'],
            'source'                => ['sometimes', 'nullable', Rule::in(array_keys(Lead::SOURCES))],
            'notes'                 => ['sometimes', 'nullable', 'string', 'max:5000'],
            'product_interest_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $lead = $this->inbox->update($lead, $data, $request->user()?->id);

        return $this->leadResponse($request, $business, $lead, 'Lead details saved.');
    }

    /** POST /api/v1/pos/crm/inbox/leads/{lead}/verify */
    public function verify(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access = $this->abortUnlessInbox($request, $business);
        $lead   = $this->inboxLead($request, $business, $leadId);
        $this->abortUnlessAllowed($this->policy->canEdit($access, $lead));

        $lead = $this->inbox->verify($lead, $request->user()?->id);

        return $this->leadResponse($request, $business, $lead, 'Lead verified.');
    }

    /** POST /api/v1/pos/crm/inbox/leads/{lead}/review — clear the "needs review" flag. */
    public function review(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access = $this->abortUnlessInbox($request, $business);
        $lead   = $this->inboxLead($request, $business, $leadId);
        $this->abortUnlessAllowed($this->policy->canReview($access, $lead));

        $lead = $this->inbox->clearReview($lead, $request->user()?->id);

        return $this->leadResponse($request, $business, $lead, 'Review flag cleared.');
    }

    /** POST /api/v1/pos/crm/inbox/leads/{lead}/forward — { department_id, division_id?, note? } */
    public function forward(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access = $this->abortUnlessInbox($request, $business);
        $lead   = $this->inboxLead($request, $business, $leadId);
        $this->abortUnlessAllowed($this->policy->canForward($access, $lead));

        $data = $request->validate([
            'department_id' => ['required', 'integer'],
            'division_id'   => ['nullable', 'integer'],
            'note'          => ['nullable', 'string', 'max:2000'],
        ]);

        $lead = $this->inbox->forward(
            $lead,
            (int) $data['department_id'],
            isset($data['division_id']) ? (int) $data['division_id'] : null,
            $data['note'] ?? null,
            $request->user()?->id,
        );

        return $this->leadResponse($request, $business, $lead, 'Lead forwarded to ' . ($lead->department?->name ?? 'the department') . '.');
    }

    /** POST /api/v1/pos/crm/inbox/leads/{lead}/merge — { target_lead_id }: merge this lead into the target and delete it. */
    public function merge(Request $request, int $leadId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $access = $this->abortUnlessInbox($request, $business);
        $lead   = $this->inboxLead($request, $business, $leadId);
        $this->abortUnlessAllowed($this->policy->canReview($access, $lead));

        $data   = $request->validate(['target_lead_id' => ['required', 'integer']]);
        $target = Lead::query()->where('business_id', $business->id)->findOrFail($data['target_lead_id']);

        $target = $this->inbox->merge($lead, $target, $request->user()?->id);

        return response()->json([
            'message' => 'Lead merged into "' . $target->name . '".',
            'data'    => ['merged_lead_id' => $leadId, 'target_lead_id' => $target->id],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** The lead as the inbox shows it — or null once it moved somewhere the user cannot see. */
    private function leadResponse(Request $request, Business $business, Lead $lead, string $message): JsonResponse
    {
        $access = $this->access($request, $business);
        $lead   = $lead->fresh(['stage', 'project', 'customer', 'createdBy', 'verifiedBy', 'requestedDepartment', 'duplicateOf.stage', 'duplicateOf.project']);

        return response()->json([
            'message' => $message,
            'data'    => $this->policy->canView($access, $lead) ? $this->inbox->present($lead, $access, withDuplicates: true) : null,
        ]);
    }

    private function access(Request $request, Business $business): CrmAccess
    {
        return $this->crmAccess ??= $this->crmPermissions->accessFor($business, $request->user());
    }

    private function abortUnlessInbox(Request $request, Business $business): CrmAccess
    {
        $access = $this->access($request, $business);
        if (! $access->usesInbox() || ! $access->can('leads_view')) {
            throw new HttpResponseException(response()->json(['message' => 'You do not have access to the marketing inbox.'], 403));
        }

        return $access;
    }

    /** A lead of this business the user can see, 404 otherwise. */
    private function inboxLead(Request $request, Business $business, int $leadId): Lead
    {
        $lead = Lead::where('business_id', $business->id)->findOrFail($leadId);
        if (! $this->policy->canView($this->access($request, $business), $lead)) {
            abort(404);
        }

        return $lead;
    }

    private function abortUnlessAllowed(bool $allowed): void
    {
        if (! $allowed) {
            throw new HttpResponseException(
                response()->json(['message' => 'You do not have permission to perform this action.'], 403)
            );
        }
    }
}
