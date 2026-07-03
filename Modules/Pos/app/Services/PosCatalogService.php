<?php

namespace Modules\Pos\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;
use Modules\Service\Models\ServiceItem;
use Modules\Product\Models\ProductCategory;
use Modules\Product\Models\ProductDiscount;
use Modules\Product\Models\ProductSellingUnit;
use Modules\Product\Models\ProductStockLayer;
use Modules\Product\Services\ProductDiscountService;
use Modules\Product\Services\ProductStockLayerService;

class PosCatalogService
{
    public function __construct(
        private readonly ProductStockLayerService $stockLayers,
        private readonly ProductDiscountService $discountService,
    ) {
    }

    /**
     * @return EloquentCollection<int, Product>
     */
    public function sellableProducts(Business $business, ?string $search = null, ?int $categoryId = null): EloquentCollection
    {
        $query = $business->products()
            ->where('is_active', true)
            ->where('is_bundle', false)
            ->with(['productUnit', 'imageFile', 'categories'])
            ->orderBy('name');

        if ($categoryId !== null && $categoryId > 0) {
            $query->whereHas('categories', fn ($builder) => $builder->whereKey($categoryId));
        }

        $term = trim((string) $search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like);
            });
        }

        return $query->get();
    }

    /**
     * @return Collection<int, ProductCategory>
     */
    public function posCategories(
        Business $business,
        ?int $branchId = null,
        bool $branchProductSeparate = false,
    ): Collection {
        return $business->productCategories()
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query
                ->where('is_active', true)
                ->where('is_bundle', false)
                ->when(
                    $branchProductSeparate && $branchId !== null,
                    fn ($q) => $q->where('branch_id', $branchId),
                ))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function productCardsForPos(
        Business $business,
        ?string $search = null,
        ?int $categoryId = null,
        int $page = 1,
        int $perPage = 40,
        ?int $branchId = null,
        bool $branchProductSeparate = false,
        bool $branchStockSeparate = false,
        ?string $stockStatus = null,
        ?int $brandId = null,
        string $sort = 'name_asc',
        bool $recentSales = false,
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $query = $business->products()
            ->where('is_active', true)
            ->where('is_bundle', false)
            ->with(['productUnit', 'imageFile', 'categories']);

        // Sort
        if ($sort === 'recent_sales') {
            $query->selectRaw('products.*, (
                SELECT MAX(ps.sold_at)
                FROM pos_sale_items psi
                INNER JOIN pos_sales ps ON ps.id = psi.pos_sale_id
                WHERE psi.product_id = products.id
                  AND ps.business_id = ?
            ) as last_sold_at', [$business->id])
                ->orderByRaw('(last_sold_at IS NULL), last_sold_at DESC, products.name ASC');
        } else {
            match ($sort) {
                'name_desc'  => $query->orderByDesc('name'),
                'price_asc'  => $query->orderBy('unit_sell_price')->orderBy('name'),
                'price_desc' => $query->orderByDesc('unit_sell_price')->orderBy('name'),
                'stock_asc'  => $query->orderBy('stock_quantity')->orderBy('name'),
                'stock_desc' => $query->orderByDesc('stock_quantity')->orderBy('name'),
                default      => $query->orderBy('name'),
            };
        }

        if ($branchProductSeparate && $branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        if ($categoryId !== null && $categoryId > 0) {
            $query->whereHas('categories', fn ($builder) => $builder->whereKey($categoryId));
        }

        if ($brandId !== null && $brandId > 0) {
            $query->whereHas('brands', fn ($builder) => $builder->whereKey($brandId));
        }

        // Recent sales filter — only products that have been sold at least once
        if ($recentSales) {
            $businessId = $business->id;
            $query->whereExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('pos_sale_items as psi')
                ->join('pos_sales as ps', 'ps.id', '=', 'psi.pos_sale_id')
                ->whereColumn('psi.product_id', 'products.id')
                ->where('ps.business_id', $businessId)
            );
        }

        // Stock status filter (uses the stock_quantity column directly)
        match ($stockStatus) {
            'in_stock'    => $query->where('stock_quantity', '>', 5),
            'low_stock'   => $query->where('stock_quantity', '>', 0)->where('stock_quantity', '<=', 5),
            'out_of_stock'=> $query->where('stock_quantity', '<=', 0),
            default       => null,
        };

        $term = trim((string) $search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like);
            });
        }

        $total    = $query->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = min($page, $lastPage);
        $items    = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $productIds      = $items->pluck('id')->all();
        $activeDiscounts = $this->discountService->activeForProducts($business, $productIds);

        return [
            'data' => $items
                ->map(fn (Product $product) => $this->productCardForProduct($product, $branchId, $branchStockSeparate, $activeDiscounts))
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

    /**
     * @param  Collection<int, ProductDiscount>|null  $activeDiscounts  Pre-loaded discounts for a batch; null triggers single-product fetch.
     * @return array<string, mixed>
     */
    public function productCardForProduct(
        Product $product,
        ?int $branchId = null,
        bool $branchStockSeparate = false,
        ?Collection $activeDiscounts = null,
    ): array {
        $product->loadMissing(['productUnit', 'imageFile', 'categories', 'business', 'sellingUnits']);
        $layers = $this->sellableLayersForProduct($product, $branchId, $branchStockSeparate);
        $meta = $this->posMetaForProduct($product, $branchId, $branchStockSeparate);
        $defaultLayer = $layers[0] ?? null;
        $sellPrices = array_values(array_unique(array_map(
            static fn (array $layer) => number_format((float) $layer['unit_sell_price'], 2, '.', ''),
            $layers,
        )));

        // Resolve discounts — use pre-loaded batch or fetch for this single product
        if ($activeDiscounts === null) {
            $product->loadMissing('business');
            $activeDiscounts = $this->discountService->activeForProducts($product->business, [$product->id]);
        }

        $productDiscounts = $activeDiscounts->where('product_id', $product->id);
        $baseDiscount     = $productDiscounts->firstWhere('product_selling_unit_id', null);
        $suDiscountById   = $productDiscounts
            ->filter(fn ($d) => $d->product_selling_unit_id !== null)
            ->keyBy('product_selling_unit_id');

        // Base price with discount applied
        $rawUnitSellPrice = $defaultLayer['unit_sell_price'] ?? $meta['unit_sell_price'];
        $discountData     = null;
        $discountedSellPrice = $rawUnitSellPrice;

        if ($baseDiscount !== null && $rawUnitSellPrice !== null) {
            $rawPrice = (float) $rawUnitSellPrice;
            $amount = $baseDiscount->discount_type === 'percentage'
                ? round($rawPrice * ((float) $baseDiscount->discount_value / 100), 2)
                : min((float) $baseDiscount->discount_value, $rawPrice);
            $finalPrice          = max(0.0, $rawPrice - $amount);
            $discountedSellPrice = $finalPrice;
            $discountData = [
                'name'        => $baseDiscount->name,
                'type'        => $baseDiscount->discount_type,
                'value'       => (float) $baseDiscount->discount_value,
                'amount'      => round($amount, 2),
                'final_price' => round($finalPrice, 2),
            ];

            // Apply the discount to every layer's sell price so the JS cart always
            // reads the discounted price — layer prices take precedence over data-unit-price.
            $discountType  = $baseDiscount->discount_type;
            $discountValue = (float) $baseDiscount->discount_value;
            $layers = array_map(static function (array $layer) use ($discountType, $discountValue): array {
                $raw = (float) $layer['unit_sell_price'];
                $off = $discountType === 'percentage'
                    ? round($raw * ($discountValue / 100), 2)
                    : min($discountValue, $raw);
                $layer['unit_sell_price'] = round(max(0.0, $raw - $off), 2);
                return $layer;
            }, $layers);

            // Recompute sell-price uniqueness after discount
            $sellPrices = array_values(array_unique(array_map(
                static fn (array $layer) => number_format((float) $layer['unit_sell_price'], 2, '.', ''),
                $layers,
            )));
        }

        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'unit' => $product->productUnit?->name ?: $product->unit,
            'image_url' => $product->imageUrl(),
            'unit_sell_price' => $rawUnitSellPrice,
            'discounted_sell_price' => $discountData !== null ? round((float) $discountedSellPrice, 2) : null,
            'discount' => $discountData,
            'stock_quantity' => $meta['stock_quantity'],
            'has_layers' => $layers !== [],
            'layer_count' => count($layers),
            'requires_layer_pick' => count($layers) > 1 && count($sellPrices) > 1,
            'has_multiple_prices' => count($sellPrices) > 1,
            'layers' => $layers,
            'selling_units' => $this->sellingUnitsForProduct($product, $suDiscountById),
            'category_ids' => $product->categories->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     label: string,
     *     quantity_remaining: float,
     *     unit_cost: float,
     *     unit_sell_price: float,
     *     received_at: ?string,
     * }>
     */
    public function sellableLayersForProduct(
        Product $product,
        ?int $branchId = null,
        bool $branchStockSeparate = false,
    ): array {
        $product->loadMissing('business');

        $layers = ProductStockLayer::query()
            ->where('product_id', $product->id)
            ->where('business_id', $product->business_id)
            ->where('quantity_remaining', '>', 0)
            ->when(
                $branchStockSeparate && $branchId !== null,
                fn ($q) => $q->where('branch_id', $branchId),
            )
            ->with(['goodsReceiveNoteItem.goodsReceiveNote'])
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($layers as $layer) {
            $sell = $layer->selling_unit_price !== null
                ? (float) $layer->selling_unit_price
                : ($this->stockLayers->defaultSellingUnitPrice(
                    $product->business,
                    $product,
                    (float) $layer->unit_cost,
                ) ?? (float) $layer->unit_cost);

            $grn = $layer->goodsReceiveNoteItem?->goodsReceiveNote;
            $label = $layer->received_at?->format('M j, Y') ?? ('Batch #'.$layer->id);
            if ($grn?->grn_number) {
                $label .= ' · '.$grn->grn_number;
            }

            $out[] = [
                'id' => (int) $layer->id,
                'label' => $label,
                'quantity_remaining' => round((float) $layer->quantity_remaining, 3),
                'unit_cost' => round((float) $layer->unit_cost, 2),
                'unit_sell_price' => round($sell, 2),
                'received_at' => $layer->received_at?->toDateString(),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id: int, name: string, price: float, item_type: string}>
     */
    public function serviceCardsForPos(Business $business): array
    {
        return ServiceItem::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price'])
            ->map(static fn (ServiceItem $s): array => [
                'id'        => (int) $s->id,
                'name'      => $s->name,
                'price'     => round((float) $s->price, 2),
                'item_type' => 'service',
            ])
            ->all();
    }

    public function findSellableProductBySku(Business $business, string $sku): ?Product
    {
        $term = trim($sku);
        if ($term === '') {
            return null;
        }

        return $business->products()
            ->where('is_active', true)
            ->where('is_bundle', false)
            ->where('sku', $term)
            ->first();
    }

    /**
     * @return array{
     *     unit_sell_price: ?float,
     *     stock_quantity: float,
     *     has_layers: bool,
     * }
     */
    public function posMetaForProduct(
        Product $product,
        ?int $branchId = null,
        bool $branchStockSeparate = false,
    ): array {
        $product->loadMissing('business');
        $layer = $this->nextFifoLayer($product, $branchId, $branchStockSeparate);

        $unitSell = $layer !== null
            ? ($layer->selling_unit_price !== null ? (float) $layer->selling_unit_price : null)
            : ($product->unit_price !== null ? (float) $product->unit_price : null);

        if ($unitSell === null && $layer !== null) {
            $unitSell = $this->stockLayers->defaultSellingUnitPrice(
                $product->business,
                $product,
                (float) $layer->unit_cost,
            );
        }

        return [
            'unit_sell_price' => $unitSell,
            'stock_quantity' => round((float) $product->stock_quantity, 3),
            'has_layers' => $layer !== null,
        ];
    }

    /**
     * @param  Collection<int, ProductDiscount>  $suDiscountById  Keyed by product_selling_unit_id
     * @return list<array{id: int, label: string, conversion_factor: float, selling_price: ?float, display_price: float, discounted_price: ?float, discount: ?array, stock_in_units: float}>
     */
    private function sellingUnitsForProduct(Product $product, Collection $suDiscountById = new Collection()): array
    {
        $basePrice = $product->unit_price !== null ? (float) $product->unit_price : null;
        $stockQty  = (float) $product->stock_quantity;

        return $product->sellingUnits->map(function (ProductSellingUnit $u) use ($basePrice, $stockQty, $suDiscountById) {
            $factor       = max(0.000001, (float) $u->conversion_factor);
            $displayPrice = $u->displaySellingPrice($basePrice);
            $discount     = $suDiscountById->get($u->id);
            $discountedPrice = null;
            $discountData    = null;

            if ($discount !== null) {
                $discount->setRelation('sellingUnit', $u);
                $amount           = $discount->discount_type === 'percentage'
                    ? round($displayPrice * ((float) $discount->discount_value / 100), 2)
                    : (float) $discount->discount_value;
                $final            = max(0.0, $displayPrice - $amount);
                $discountedPrice  = round($final, 2);
                $discountData     = [
                    'name'        => $discount->name,
                    'type'        => $discount->discount_type,
                    'value'       => (float) $discount->discount_value,
                    'amount'      => round($amount, 2),
                    'final_price' => $discountedPrice,
                ];
            }

            return [
                'id'              => (int) $u->id,
                'label'           => $u->label,
                'conversion_factor' => $factor,
                'selling_price'   => $u->selling_price !== null ? round((float) $u->selling_price, 2) : null,
                'display_price'   => $discountedPrice ?? $displayPrice,
                'original_display_price' => $discountedPrice !== null ? $displayPrice : null,
                'discount'        => $discountData,
                'stock_in_units'  => floor($stockQty / $factor),
            ];
        })->all();
    }

    public function nextFifoLayer(
        Product $product,
        ?int $branchId = null,
        bool $branchStockSeparate = false,
    ): ?ProductStockLayer {
        return ProductStockLayer::query()
            ->where('product_id', $product->id)
            ->where('business_id', $product->business_id)
            ->where('quantity_remaining', '>', 0)
            ->when(
                $branchStockSeparate && $branchId !== null,
                fn ($q) => $q->where('branch_id', $branchId),
            )
            ->orderBy('received_at')
            ->orderBy('id')
            ->first();
    }
}
