<?php

namespace Modules\Pos\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;
use Modules\Product\Models\ProductSellingUnit;
use Modules\Product\Models\ProductStockLayer;
use Modules\Product\Services\ProductStockLayerService;

class PosCatalogService
{
    public function __construct(
        private readonly ProductStockLayerService $stockLayers,
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
    public function posCategories(Business $business): Collection
    {
        return $business->productCategories()
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query
                ->where('is_active', true)
                ->where('is_bundle', false))
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
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));

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

        $total    = $query->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = min($page, $lastPage);
        $items    = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return [
            'data' => $items
                ->map(fn (Product $product) => $this->productCardForProduct($product))
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
     * @return array<string, mixed>
     */
    public function productCardForProduct(Product $product): array
    {
        $product->loadMissing(['productUnit', 'imageFile', 'categories', 'business', 'sellingUnits']);
        $layers = $this->sellableLayersForProduct($product);
        $meta = $this->posMetaForProduct($product);
        $defaultLayer = $layers[0] ?? null;
        $sellPrices = array_values(array_unique(array_map(
            static fn (array $layer) => number_format((float) $layer['unit_sell_price'], 2, '.', ''),
            $layers,
        )));

        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'unit' => $product->productUnit?->name ?: $product->unit,
            'image_url' => $product->imageUrl(),
            'unit_sell_price' => $defaultLayer['unit_sell_price'] ?? $meta['unit_sell_price'],
            'stock_quantity' => $meta['stock_quantity'],
            'has_layers' => $layers !== [],
            'layer_count' => count($layers),
            'requires_layer_pick' => count($layers) > 1 && count($sellPrices) > 1,
            'has_multiple_prices' => count($sellPrices) > 1,
            'layers' => $layers,
            'selling_units' => $this->sellingUnitsForProduct($product),
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
    public function sellableLayersForProduct(Product $product): array
    {
        $product->loadMissing('business');

        $layers = ProductStockLayer::query()
            ->where('product_id', $product->id)
            ->where('business_id', $product->business_id)
            ->where('quantity_remaining', '>', 0)
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
    public function posMetaForProduct(Product $product): array
    {
        $product->loadMissing('business');
        $layer = $this->nextFifoLayer($product);

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
     * @return list<array{id: int, label: string, conversion_factor: float, selling_price: ?float, display_price: float, stock_in_units: float}>
     */
    private function sellingUnitsForProduct(Product $product): array
    {
        $basePrice = $product->unit_price !== null ? (float) $product->unit_price : null;
        $stockQty  = (float) $product->stock_quantity;

        return $product->sellingUnits->map(function (ProductSellingUnit $u) use ($basePrice, $stockQty) {
            $factor = max(0.000001, (float) $u->conversion_factor);
            return [
                'id'              => (int) $u->id,
                'label'           => $u->label,
                'conversion_factor' => $factor,
                'selling_price'   => $u->selling_price !== null ? round((float) $u->selling_price, 2) : null,
                'display_price'   => $u->displaySellingPrice($basePrice),
                'stock_in_units'  => floor($stockQty / $factor),
            ];
        })->all();
    }

    public function nextFifoLayer(Product $product): ?ProductStockLayer
    {
        return ProductStockLayer::query()
            ->where('product_id', $product->id)
            ->where('business_id', $product->business_id)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->first();
    }
}
