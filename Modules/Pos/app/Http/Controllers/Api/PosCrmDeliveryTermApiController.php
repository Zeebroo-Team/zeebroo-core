<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CrmPermissionService;
use Modules\CRM\Services\DeliveryTermService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

/**
 * Delivery terms for CRM quotations ("Within 04-06 weeks from confirmed PO", …) — the quotation
 * editor's Delivery dropdown. Anyone who can see quotations reads them; whoever creates or
 * approves quotations manages the list (CRM ribbon → Delivery Terms).
 */
class PosCrmDeliveryTermApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly CrmPermissionService $crmPermissions,
        private readonly DeliveryTermService $terms,
    ) {}

    /** GET crm/delivery-terms?all=1 — active terms (all of them with all=1, for the manage window). */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->access($request, $business);
        $this->abortUnlessAllowed($access->can('quotations_view'));

        $canManage = $this->canManage($access);
        $all       = $canManage && $request->boolean('all');

        return response()->json([
            'data' => $this->terms->present($this->terms->listForBusiness($business, ! $all)),
            'meta' => ['can_manage' => $canManage],
        ]);
    }

    /** POST crm/delivery-terms — { label, is_active? } */
    public function store(Request $request): JsonResponse
    {
        $business = $this->manageableBusiness($request);
        $term     = $this->terms->create($business, $request->validate($this->rules(true)));

        return response()->json(['message' => 'Delivery term added.', 'data' => $this->terms->present(collect([$term]))[0]], 201);
    }

    /** PUT crm/delivery-terms/{term} — { label?, is_active? } */
    public function update(Request $request, int $termId): JsonResponse
    {
        $business = $this->manageableBusiness($request);
        $term     = $this->terms->update($this->terms->termForBusiness($business, $termId), $request->validate($this->rules(false)));

        return response()->json(['message' => 'Delivery term saved.', 'data' => $this->terms->present(collect([$term]))[0]]);
    }

    /** DELETE crm/delivery-terms/{term} — quotations already saved keep their text. */
    public function destroy(Request $request, int $termId): JsonResponse
    {
        $business = $this->manageableBusiness($request);
        $this->terms->delete($this->terms->termForBusiness($business, $termId));

        return response()->json(['message' => 'Delivery term deleted.']);
    }

    /** POST crm/delivery-terms/reorder — { ids: [..] } in the new order. */
    public function reorder(Request $request): JsonResponse
    {
        $business = $this->manageableBusiness($request);
        $data     = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer']]);
        $this->terms->reorder($business, $data['ids']);

        return response()->json(['message' => 'Order saved.', 'data' => $this->terms->present($this->terms->listForBusiness($business, false))]);
    }

    private function rules(bool $creating): array
    {
        return [
            'label'     => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function canManage(CrmAccess $access): bool
    {
        return $access->can('quotations_manage') || $access->can('quotations_approve');
    }

    private function manageableBusiness(Request $request): Business
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessAllowed($this->canManage($this->access($request, $business)));

        return $business;
    }

    private function access(Request $request, Business $business): CrmAccess
    {
        return $this->crmPermissions->accessFor($business, $request->user());
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
