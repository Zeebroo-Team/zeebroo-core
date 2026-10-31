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
use Modules\CRM\Services\LeadCloseService;
use Modules\CRM\Services\LostReasonService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Sales\Models\Quotation;

/**
 * Closing a lead from the desktop lead window: Won (optionally creating an invoice, a sales
 * order or a project from the accepted quotation), Lost (with a lost reason) and Reopen (head /
 * admin only). The rules are LeadCloseService's.
 */
class PosCrmLeadCloseApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    private ?CrmAccess $crmAccess = null;

    public function __construct(
        private readonly CrmPermissionService $crmPermissions,
        private readonly LeadPolicy $policy,
        private readonly LeadCloseService $close,
        private readonly LostReasonService $lostReasons,
    ) {}

    /** GET crm/leads/{lead}/close-options — what the Won / Lost / Reopen windows offer. */
    public function options(Request $request, int $leadId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $lead->loadMissing('stage');

        return response()->json(['data' => [
            'status'        => $lead->isWon() ? 'won' : ($lead->isLost() ? 'lost' : 'open'),
            'quotations'    => $this->close->wonQuotations($lead)->map(fn (Quotation $q) => [
                'id'           => (int) $q->id,
                'quote_number' => $q->quote_number,
                'version'      => (int) $q->version,
                'status'       => $q->status,
                'total'        => (float) $q->total,
                'sent_at'      => $q->sent_at?->toIso8601String(),
            ])->values(),
            'create_options' => collect(LeadCloseService::CREATE_OPTIONS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'documents'     => $this->close->documents($lead),
            'lost_reasons'  => $this->lostReasons->present($this->lostReasons->listForBusiness($business)),
            'can'           => [
                'win'    => $this->policy->canEdit($access, $lead) && ! $lead->isLost(),
                'lose'   => $this->policy->canMove($access, $lead) && $lead->isOpen(),
                'reopen' => $this->policy->canReopen($access, $lead) && ! $lead->isOpen(),
            ],
        ]]);
    }

    /** POST crm/leads/{lead}/won — { quotation_id?, create: none|invoice|sales_order|project, project_name? } */
    public function won(Request $request, int $leadId): JsonResponse
    {
        [, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessAllowed($access->can('leads_edit') && $this->policy->canEdit($access, $lead));

        $data = $request->validate([
            'quotation_id' => ['nullable', 'integer'],
            'create'       => ['nullable', 'string', Rule::in(array_keys(LeadCloseService::CREATE_OPTIONS))],
            'project_name' => ['nullable', 'string', 'max:150'],
        ]);

        $result = $this->close->markWon($lead, $data, $request->user()?->id);
        $doc    = $result['document'];

        return response()->json([
            'message' => 'Lead won and linked to ' . ($result['customer']->wasRecentlyCreated ? 'a new customer' : 'the customer "' . $result['customer']->name . '"') . '.'
                . ($doc ? ($result['document_created'] ? ' ' . $doc['label'] . ' ' . $doc['number'] . ' created.' : ' It already has ' . $doc['label'] . ' ' . $doc['number'] . '.') : ''),
            'data' => [
                'lead'             => $result['lead']->toArray(),
                'customer'         => ['id' => (int) $result['customer']->id, 'name' => $result['customer']->name],
                'document'         => $doc,
                'document_created' => $result['document_created'],
                'documents'        => $this->close->documents($result['lead']),
            ],
        ]);
    }

    /** POST crm/leads/{lead}/lost — { lost_reason_id, note? } */
    public function lost(Request $request, int $leadId): JsonResponse
    {
        [, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessAllowed($this->policy->canMove($access, $lead));

        $data = $request->validate([
            'lost_reason_id' => ['required', 'integer'],
            'note'           => ['nullable', 'string', 'max:255'],
        ]);

        $lead = $this->close->markLost($lead, $data['lost_reason_id'], $data['note'] ?? null, $request->user()?->id);

        return response()->json(['message' => 'Lead marked as lost.', 'data' => ['lead' => $lead->load('stage')->toArray()]]);
    }

    /** POST crm/leads/{lead}/reopen — { reason } — the head or an admin. */
    public function reopen(Request $request, int $leadId): JsonResponse
    {
        [, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessAllowed($this->policy->canReopen($access, $lead));

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $lead = $this->close->reopen($lead, $data['reason'], $request->user()?->id);

        return response()->json(['message' => 'Lead reopened.', 'data' => ['lead' => $lead->load('stage')->toArray()]]);
    }

    /** @return array{0: Business, 1: CrmAccess, 2: Lead} */
    private function context(Request $request, int $leadId): array
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->crmAccess ??= $this->crmPermissions->accessFor($business, $request->user());
        if (! $access->can('leads_view')) {
            $this->abortUnlessAllowed(false);
        }

        $lead = Lead::where('business_id', $business->id)->findOrFail($leadId);
        if (! $this->policy->canView($access, $lead)) {
            abort(404);
        }

        return [$business, $access, $lead];
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
