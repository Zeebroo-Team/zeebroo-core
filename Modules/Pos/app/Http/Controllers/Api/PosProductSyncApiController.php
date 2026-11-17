<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosProductSyncService;

/**
 * Mobile app "Offline products" sync. Read-only; used only by the mobile
 * app's on-device catalog cache — the regular product endpoints are unchanged.
 */
class PosProductSyncApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    private const MAX_IDS = 100;

    public function __construct(private readonly PosProductSyncService $sync)
    {
    }

    /** GET online/products/sync-index — every sellable product's id + fingerprint. */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        [$branchId, $productSeparate, $stockSeparate] = $this->branchScope($request, $business);

        return response()->json([
            'data' => $this->sync->index($business, $branchId, $productSeparate, $stockSeparate),
        ]);
    }

    /** GET online/products/by-ids?ids=1,2,3 — full POS cards (max 100). */
    public function byIds(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        [$branchId, $productSeparate, $stockSeparate] = $this->branchScope($request, $business);

        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($id) => trim($id))
            ->filter(fn ($id) => ctype_digit($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->count() > self::MAX_IDS) {
            return response()->json(['message' => 'Request at most '.self::MAX_IDS.' products at a time.'], 422);
        }

        return response()->json([
            'data' => $this->sync->cardsByIds($business, $ids->all(), $branchId, $productSeparate, $stockSeparate),
        ]);
    }

    /**
     * Same branch resolution as PosCatalogApiController::products().
     *
     * @return array{0: ?int, 1: bool, 2: bool}
     */
    protected function branchScope(Request $request, Business $business): array
    {
        $branchId = $request->query('branch') ?? $request->header('X-Branch-Id');
        $branchId = is_numeric($branchId) ? (int) $branchId : null;

        $branchPosSeparate = (bool) get_settings('business.branch_pos_separate', false, $business);

        return [
            $branchPosSeparate ? $branchId : null,
            (bool) get_settings('business.branch_product_separate', false, $business),
            (bool) get_settings('business.branch_stock_separate', false, $business),
        ];
    }
}
