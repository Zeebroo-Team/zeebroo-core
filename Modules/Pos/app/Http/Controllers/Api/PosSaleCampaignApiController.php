<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosCatalogService;
use Modules\Product\Models\SaleCampaign;
use Modules\Product\Services\SaleCampaignService;

class PosSaleCampaignApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly SaleCampaignService $service,
        private readonly PosCatalogService $catalog,
    ) {
    }

    /** Products for the POS "Campaign" filter, grouped by active campaign. */
    public function products(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $branchId = $request->query('branch') ?? $request->header('X-Branch-Id');
        $branchId = is_numeric($branchId) ? (int) $branchId : null;

        $branchPosSeparate     = (bool) get_settings('business.branch_pos_separate', false, $business);
        $branchProductSeparate = (bool) get_settings('business.branch_product_separate', false, $business);
        $branchStockSeparate   = (bool) get_settings('business.branch_stock_separate', false, $business);
        $effectiveBranchId     = $branchPosSeparate ? $branchId : null;

        return response()->json([
            'data' => $this->catalog->productsGroupedByCampaign(
                $business,
                $effectiveBranchId,
                $branchProductSeparate,
                $branchStockSeparate,
            ),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $q      = (string) $request->query('q', '');
        $status = (string) $request->query('status', '');

        $campaigns = $this->service->list($business, $q, $status);

        return response()->json([
            'data' => $campaigns->map(fn ($c) => $this->format($c)),
        ]);
    }

    public function show(Request $request, SaleCampaign $campaign): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->campaignForBusiness($business, $campaign)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $campaign->load(['items.product', 'items.sellingUnit', 'imageFile']);

        return response()->json(['data' => $this->format($campaign)]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $data = $this->validateData($request, $business);

        $campaign = $this->service->create($business, $data);
        $campaign->load(['items.product', 'items.sellingUnit', 'imageFile']);

        return response()->json([
            'message' => 'Sale campaign created.',
            'data'    => $this->format($campaign),
        ], 201);
    }

    public function update(Request $request, SaleCampaign $campaign): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->campaignForBusiness($business, $campaign)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $this->validateData($request, $business, $campaign);

        $this->service->update($campaign, $data);
        $campaign->refresh()->load(['items.product', 'items.sellingUnit', 'imageFile']);

        return response()->json([
            'message' => 'Sale campaign updated.',
            'data'    => $this->format($campaign),
        ]);
    }

    public function destroy(Request $request, SaleCampaign $campaign): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->campaignForBusiness($business, $campaign)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $this->service->delete($campaign);

        return response()->json(['message' => 'Sale campaign deleted.']);
    }

    private function validateData(Request $request, $business, ?SaleCampaign $campaign = null): array
    {
        $sometimes = $campaign !== null ? 'sometimes|' : '';

        $data = $request->validate([
            'name'                     => "{$sometimes}required|string|max:191",
            'description'              => 'nullable|string|max:2000',
            'file_manager_file_id'     => 'nullable|integer',
            'mode'                     => "{$sometimes}required|in:storewide,individual",
            'discount_type'            => [Rule::requiredIf(fn () => $request->input('mode') === 'storewide'), 'nullable', 'in:flat,percentage'],
            'discount_value'           => [Rule::requiredIf(fn () => $request->input('mode') === 'storewide'), 'nullable', 'numeric', 'min:0.01'],
            'is_long_term'             => 'boolean',
            'starts_at'                => 'nullable|date',
            'ends_at'                  => [Rule::requiredIf(fn () => ! $request->boolean('is_long_term')), 'nullable', 'date', 'after_or_equal:starts_at'],
            'is_active'                => 'boolean',
            'items'                    => [Rule::requiredIf(fn () => $request->input('mode') === 'individual'), 'nullable', 'array'],
            'items.*.product_id'       => 'required_with:items|integer',
            'items.*.product_selling_unit_id' => 'nullable|integer',
            'items.*.discount_type'    => 'required_with:items|in:flat,percentage',
            'items.*.discount_value'   => 'required_with:items|numeric|min:0.01',
        ]);

        if (($data['mode'] ?? $campaign?->mode) === 'individual') {
            // discount_type/discount_value double as an optional default here —
            // the desktop client pre-fills newly added items with it, but each
            // item still stores (and can override) its own discount.
            foreach ($data['items'] ?? [] as $item) {
                if (! $business->products()->whereKey($item['product_id'])->exists()) {
                    abort(response()->json(['message' => "Product #{$item['product_id']} not found."], 404));
                }
            }
        } else {
            unset($data['items']);
        }

        if (! empty($data['file_manager_file_id']) && ! $business->fileManagerFiles()->whereKey($data['file_manager_file_id'])->exists()) {
            $data['file_manager_file_id'] = null;
        }

        if (! empty($data['is_long_term'])) {
            $data['ends_at'] = null;
        }

        return $data;
    }

    private function format(SaleCampaign $c): array
    {
        return [
            'id'                   => $c->id,
            'name'                 => $c->name,
            'description'          => $c->description,
            'image_url'            => $c->imageUrl(),
            'file_manager_file_id' => $c->file_manager_file_id,
            'mode'                 => $c->mode,
            'discount_type'        => $c->discount_type,
            'discount_value'       => $c->discount_value !== null ? (float) $c->discount_value : null,
            'is_long_term'         => (bool) $c->is_long_term,
            'starts_at'            => $c->starts_at?->toDateString(),
            'ends_at'              => $c->ends_at?->toDateString(),
            'is_active'            => (bool) $c->is_active,
            'is_currently_active'  => $c->isCurrentlyActive(),
            'is_expired'           => $c->isExpired(),
            'item_count'           => $c->mode === 'individual' ? $c->items->count() : null,
            'items'                => $c->mode === 'individual'
                ? $c->items->map(fn ($item) => [
                    'id'                       => $item->id,
                    'product_id'               => $item->product_id,
                    'product_name'             => $item->product?->name ?? '—',
                    'product_selling_unit_id'  => $item->product_selling_unit_id,
                    'product_price'            => (float) ($item->product_selling_unit_id
                                                    ? ($item->sellingUnit?->selling_price ?? 0)
                                                    : ($item->product?->unit_price ?? 0)),
                    'discount_type'            => $item->discount_type,
                    'discount_value'           => (float) $item->discount_value,
                ])
                : [],
        ];
    }
}
