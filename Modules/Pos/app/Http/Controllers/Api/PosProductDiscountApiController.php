<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Product\Models\ProductDiscount;
use Modules\Product\Services\ProductDiscountService;

class PosProductDiscountApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly ProductDiscountService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $q      = (string) $request->query('q', '');
        $status = $request->query('status', '');

        $query = $business->productDiscounts()
            ->with(['product', 'sellingUnit'])
            ->orderByDesc('created_at');

        if ($q !== '') {
            $query->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$q}%"))
                  ->orWhere('name', 'like', "%{$q}%");
        }

        if ($status === 'active') {
            $today = now()->startOfDay();
            $query->where('is_active', true)
                  ->where(fn ($sq) => $sq->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
                  ->where(fn ($sq) => $sq->whereNull('ends_at')->orWhere('ends_at', '>=', $today));
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $discounts = $query->get();

        return response()->json([
            'data' => $discounts->map(fn ($d) => $this->format($d)),
        ]);
    }

    public function productOptions(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $q       = (string) $request->query('q', '');
        $perPage = 30;

        $query = $business->products()
            ->with('sellingUnits')
            ->where('is_active', true)
            ->orderBy('name');

        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }

        $products = $query->limit($perPage)->get();

        return response()->json([
            'data' => $products->map(fn ($p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'selling_price'=> (float) ($p->selling_price ?? 0),
                'selling_units' => $p->sellingUnits->map(fn ($su) => [
                    'id'           => $su->id,
                    'label'        => $su->label ?? $su->name,
                    'selling_price'=> (float) ($su->selling_price ?? 0),
                ]),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $data = $request->validate([
            'name'                   => 'required|string|max:191',
            'product_id'             => 'required|integer',
            'product_selling_unit_id'=> 'nullable|integer',
            'discount_type'          => 'required|in:flat,percentage',
            'discount_value'         => 'required|numeric|min:0.01',
            'starts_at'              => 'nullable|date',
            'ends_at'                => 'nullable|date|after_or_equal:starts_at',
            'is_active'              => 'boolean',
        ]);

        // Verify the product belongs to this business
        $product = $business->products()->find($data['product_id']);
        if (! $product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $discount = $this->service->create($business, $data);
        $discount->load(['product', 'sellingUnit']);

        return response()->json([
            'message' => 'Discount created.',
            'data'    => $this->format($discount),
        ], 201);
    }

    public function update(Request $request, ProductDiscount $discount): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->discountForBusiness($business, $discount)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'name'                   => 'sometimes|required|string|max:191',
            'product_id'             => 'sometimes|required|integer',
            'product_selling_unit_id'=> 'nullable|integer',
            'discount_type'          => 'sometimes|required|in:flat,percentage',
            'discount_value'         => 'sometimes|required|numeric|min:0.01',
            'starts_at'              => 'nullable|date',
            'ends_at'                => 'nullable|date|after_or_equal:starts_at',
            'is_active'              => 'boolean',
        ]);

        $this->service->update($discount, $data);
        $discount->refresh()->load(['product', 'sellingUnit']);

        return response()->json([
            'message' => 'Discount updated.',
            'data'    => $this->format($discount),
        ]);
    }

    public function destroy(Request $request, ProductDiscount $discount): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->discountForBusiness($business, $discount)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $this->service->delete($discount);

        return response()->json(['message' => 'Discount deleted.']);
    }

    private function format(ProductDiscount $d): array
    {
        $isActive = $d->isCurrentlyActive();

        return [
            'id'                     => $d->id,
            'name'                   => $d->name,
            'product_id'             => $d->product_id,
            'product_name'           => $d->product?->name ?? '—',
            'product_selling_unit_id'=> $d->product_selling_unit_id,
            'selling_unit_label'     => $d->sellingUnit ? ($d->sellingUnit->label ?? $d->sellingUnit->name) : null,
            'discount_type'          => $d->discount_type,
            'discount_value'         => (float) $d->discount_value,
            'starts_at'              => $d->starts_at?->toDateString(),
            'ends_at'                => $d->ends_at?->toDateString(),
            'is_active'              => (bool) $d->is_active,
            'is_currently_active'    => $isActive,
            'original_price'         => (float) $d->originalPrice(),
            'discount_amount'        => (float) $d->discountAmount(),
            'final_price'            => (float) $d->finalPrice(),
        ];
    }
}
