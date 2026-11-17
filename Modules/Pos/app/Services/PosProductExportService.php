<?php

namespace Modules\Pos\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductStockLayer;
use Modules\Product\Services\ProductStockLayerService;

/**
 * Flat, full-detail product rows for Inventory → Export Products (CSV / PDF).
 *
 * Deliberately lighter than PosCatalogService::productCardsForPos — stock layers are
 * loaded in one query per page instead of per product, so exporting thousands of
 * products stays fast. Filters and branch scoping mirror the inventory list.
 */
class PosProductExportService
{
    public const MAX_PER_PAGE = 1000;

    public const LOW_STOCK_THRESHOLD = 5;

    public function __construct(
        private readonly ProductStockLayerService $stockLayers,
    ) {
    }

    /**
     * @param  array{q?: ?string, category_id?: ?int, brand_id?: ?int, stock_status?: ?string, sort?: ?string, ids?: list<int>}  $filters
     * @return array{data: list<array<string, mixed>>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function rows(Business $business, array $filters, int $page = 1, int $perPage = 500, ?int $branchId = null): array
    {
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        $branchPosSeparate     = (bool) get_settings('business.branch_pos_separate', false, $business);
        $branchProductSeparate = (bool) get_settings('business.branch_product_separate', false, $business);
        $branchStockSeparate   = (bool) get_settings('business.branch_stock_separate', false, $business);
        $branchId              = $branchPosSeparate ? $branchId : null;
        $layerBranchId         = $branchStockSeparate ? $branchId : null;

        $query = $this->query($business, $filters, $branchProductSeparate ? $branchId : null);

        $total    = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = min(max(1, $page), $lastPage);

        $products = $query
            ->with(['productUnit:id,name,abbreviation', 'categories:id,name', 'brands:id,name', 'branch:id,name'])
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $layersByProduct = ProductStockLayer::query()
            ->where('business_id', $business->id)
            ->whereIn('product_id', $products->pluck('id')->all() ?: [0])
            ->where('quantity_remaining', '>', 0)
            ->when($layerBranchId !== null, fn ($q) => $q->where('branch_id', $layerBranchId))
            ->orderBy('received_at')
            ->orderBy('id')
            ->get(['id', 'product_id', 'batch_sku', 'quantity_remaining', 'unit_cost', 'selling_unit_price'])
            ->groupBy('product_id');

        return [
            'data' => $products
                ->map(fn (Product $p) => $this->row($business, $p, $layersByProduct->get($p->id, collect()), $layerBranchId !== null))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
                'total'        => $total,
            ],
        ];
    }

    private function query(Business $business, array $filters, ?int $branchId): Builder
    {
        $query = $business->products()->getQuery()
            ->where('is_active', true)
            ->where('is_bundle', false);

        if ($branchId !== null) {
            $query->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'));
        }

        $ids = array_values(array_filter(array_map('intval', $filters['ids'] ?? [])));
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        if (! empty($filters['category_id'])) {
            $query->whereHas('categories', fn ($b) => $b->whereKey((int) $filters['category_id']));
        }

        if (! empty($filters['brand_id'])) {
            $query->whereHas('brands', fn ($b) => $b->whereKey((int) $filters['brand_id']));
        }

        match ($filters['stock_status'] ?? null) {
            'in_stock'     => $query->where('stock_quantity', '>', self::LOW_STOCK_THRESHOLD),
            'low_stock'    => $query->where('stock_quantity', '>', 0)->where('stock_quantity', '<=', self::LOW_STOCK_THRESHOLD),
            'out_of_stock' => $query->where('stock_quantity', '<=', 0),
            default        => null,
        };

        foreach (preg_split('/\s+/', trim((string) ($filters['q'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $query->where(fn ($b) => $b->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhereHas('stockLayers', fn ($l) => $l->where('batch_sku', 'like', $like)));
        }

        match ($filters['sort'] ?? 'name_asc') {
            'name_desc'  => $query->orderByDesc('name'),
            'price_asc'  => $query->orderBy('unit_price')->orderBy('name'),
            'price_desc' => $query->orderByDesc('unit_price')->orderBy('name'),
            'stock_asc'  => $query->orderBy('stock_quantity')->orderBy('name'),
            'stock_desc' => $query->orderByDesc('stock_quantity')->orderBy('name'),
            default      => $query->orderBy('name'),
        };

        return $query->orderBy('id');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProductStockLayer>  $layers  Remaining layers, oldest (next FIFO) first
     * @return array<string, mixed>
     */
    private function row(Business $business, Product $p, $layers, bool $branchScopedStock): array
    {
        $stock = $branchScopedStock
            ? (float) $layers->sum('quantity_remaining')
            : (float) $p->stock_quantity;

        // Selling price — same resolution as the POS card: next FIFO layer's price, else the product's.
        $next = $layers->first();
        $sell = $next?->selling_unit_price !== null
            ? (float) $next->selling_unit_price
            : ($next !== null
                ? $this->stockLayers->defaultSellingUnitPrice($business, $p, (float) $next->unit_cost)
                : ($p->unit_price !== null ? (float) $p->unit_price : null));

        // Cost — weighted average of the stock on hand, falling back to the product's cost price.
        $layerQty = (float) $layers->sum('quantity_remaining');
        $cost = $layerQty > 0
            ? $layers->sum(fn ($l) => (float) $l->quantity_remaining * (float) $l->unit_cost) / $layerQty
            : ($p->cost_price !== null ? (float) $p->cost_price : null);

        $status = $stock <= 0 ? 'Out of stock' : ($stock <= self::LOW_STOCK_THRESHOLD ? 'Low stock' : 'In stock');

        return [
            'id'                 => (int) $p->id,
            'name'               => $p->name,
            'sku'                => $p->sku,
            'barcodes'           => $layers->pluck('batch_sku')->filter()->unique()->values()->implode(' | '),
            'category'           => $p->categories->pluck('name')->implode(', '),
            'brand'              => $p->brands->pluck('name')->implode(', '),
            'unit'               => $p->productUnit?->abbreviation ?: ($p->productUnit?->name ?? $p->unit),
            'selling_price'      => $sell !== null ? round($sell, 2) : null,
            'cost_price'         => $cost !== null ? round($cost, 2) : null,
            'wholesale_price'    => $p->wholesale_price !== null ? round((float) $p->wholesale_price, 2) : null,
            'stock_quantity'     => round($stock, 3),
            'stock_status'       => $status,
            'stock_cost_value'   => $cost !== null ? round(max(0, $stock) * $cost, 2) : null,
            'stock_retail_value' => $sell !== null ? round(max(0, $stock) * $sell, 2) : null,
            'model_no'           => $p->model_no,
            'size'               => $p->size,
            'mfg_date'           => $p->mfg_date?->toDateString(),
            'exp_date'           => $p->exp_date?->toDateString(),
            'warranty'           => $p->has_warranty ? (string) ($p->warranty_duration ?? 'Yes') : null,
            'tags'               => is_array($p->tags) ? implode(', ', $p->tags) : null,
            'description'        => $p->description,
            'branch'             => $p->branch?->name,
            'created_at'         => $p->created_at?->toDateTimeString(),
            'updated_at'         => $p->updated_at?->toDateTimeString(),
        ];
    }
}
