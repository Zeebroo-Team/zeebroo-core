<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Services\CrmBadgeCountService;
use Modules\CRM\Services\CrmPermissionService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

/** The desktop CRM ribbon's notification counters (My Follow-ups, Quotation Approvals, …). */
class PosCrmBadgeApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly CrmPermissionService $crmPermissions,
        private readonly CrmBadgeCountService $badges,
    ) {}

    /** GET crm/badge-counts → { data: { followups, approvals, inbox, inquiries } } (null = no access) */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->crmPermissions->accessFor($business, $request->user());

        if (! $access->hasCrm() || $access->userId === null) {
            return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
        }

        return response()->json(['data' => $this->badges->counts($business, $access)]);
    }
}
