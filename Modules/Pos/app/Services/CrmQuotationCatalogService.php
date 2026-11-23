<?php

namespace Modules\Pos\Services;

use Modules\Business\Models\Business;
use Modules\Product\Http\Controllers\ProductController;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductBrand;

/**
 * The CRM quotation editor's Brand → Model product picker.
 *
 * A "model" is a sellable product of the brand, labelled by its model no. (falling back to its name).
 * Each comes with the product information the quotation shows under the dropdowns: warranty and delivery.
 */
class CrmQuotationCatalogService
{
    private const MAX_MODELS = 1000;

    public function __construct(private readonly PosCatalogService $catalog) {}

    /**
     * Active brands that have at least one sellable (active, non-bundle) product.
     *
     * @return list<array{id: int, name: string, product_count: int}>
     */
    public function brands(Business $business): array
    {
        return ProductBrand::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->withCount(['products as product_count' => fn ($q) => $q
                ->where('products.business_id', $business->id)
                ->where('products.is_active', true)
                ->where('products.is_bundle', false)])
            ->orderBy('name')
            ->get()
            ->filter(fn (ProductBrand $b) => $b->product_count > 0)
            ->map(fn (ProductBrand $b) => [
                'id'            => (int) $b->id,
                'name'          => $b->name,
                'product_count' => (int) $b->product_count,
            ])
            ->values()
            ->all();
    }

    /**
     * The brand's sellable products, priced and stocked the same way as the POS catalog.
     *
     * @return list<array<string, mixed>>
     */
    public function models(Business $business, int $brandId, ?int $branchId = null): array
    {
        [$effectiveBranchId, $branchProductSeparate, $branchStockSeparate] = $this->branchScope($business, $branchId);

        // The POS catalog pages at 100 — walk the pages so a big brand is not cut short
        $cards = [];
        $page  = 1;
        do {
            $result = $this->catalog->productCardsForPos(
                $business,
                null,
                null,
                $page,
                100,
                $effectiveBranchId,
                $branchProductSeparate,
                $branchStockSeparate,
                null,
                $brandId,
            );
            array_push($cards, ...$result['data']);
        } while ($page++ < $result['meta']['last_page'] && count($cards) < self::MAX_MODELS);

        $models = $this->present($business, $cards);
        usort($models, fn (array $a, array $b) => strnatcasecmp($a['label'], $b['label']));

        return $models;
    }

    /**
     * Sellable products of any brand whose name or SKU matches every word of $term — for picking
     * a product straight away, without choosing its brand first. Each carries its brand (if any).
     *
     * @return list<array<string, mixed>>
     */
    public function search(Business $business, string $term, ?int $branchId = null, int $limit = 30): array
    {
        if (trim($term) === '') {
            return [];
        }
        [$effectiveBranchId, $branchProductSeparate, $branchStockSeparate] = $this->branchScope($business, $branchId);

        $result = $this->catalog->productCardsForPos(
            $business,
            $term,
            null,
            1,
            max(1, min(100, $limit)),
            $effectiveBranchId,
            $branchProductSeparate,
            $branchStockSeparate,
        );

        return $this->present($business, $result['data']);
    }

    /**
     * POS cards → picker models, with the fields the POS card does not carry (model no., warranty,
     * delivery, brand).
     *
     * @param  list<array<string, mixed>>  $cards
     * @return list<array<string, mixed>>
     */
    private function present(Business $business, array $cards): array
    {
        $extra = Product::query()
            ->where('business_id', $business->id)
            ->whereIn('id', array_column($cards, 'id'))
            ->with(['brands' => fn ($q) => $q
                ->where($q->getRelated()->qualifyColumn('is_active'), true)
                ->orderBy($q->getRelated()->qualifyColumn('name'))])
            ->get(['id', 'model_no', 'has_warranty', 'warranty_duration', 'courier_delivery', 'delivery_methods'])
            ->keyBy('id');

        return array_map(function (array $card) use ($extra) {
            $p = $extra->get($card['id']);
            $modelNo = trim((string) ($p?->model_no ?? ''));
            $brand = $p?->brands->first();

            return [
                'id'              => $card['id'],
                'name'            => $card['name'],
                'sku'             => $card['sku'],
                'model_no'        => $modelNo !== '' ? $modelNo : null,
                'label'           => $modelNo !== '' ? $modelNo : $card['name'],
                'brand_id'        => $brand ? (int) $brand->id : null,
                'brand_name'      => $brand?->name,
                'unit'            => $card['unit'],
                'unit_sell_price' => $card['unit_sell_price'] !== null ? round((float) $card['unit_sell_price'], 2) : 0.0,
                'stock_quantity'  => $card['stock_quantity'] !== null ? (float) $card['stock_quantity'] : null,
                'warranty'        => $this->warranty($p),
                'delivery'        => $this->delivery($p),
            ];
        }, $cards);
    }

    /**
     * Each product's standard selling price — the same one the Brand → Model picker shows (POS
     * catalog: the next stock batch's sell price, else the product's price). Products of other
     * businesses, and products with no price set, are left out — their price is typed in.
     *
     * @param  int[]  $productIds
     * @return array<int, float> product id => price
     */
    public function standardPrices(Business $business, array $productIds, ?int $branchId = null): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === []) {
            return [];
        }
        [$effectiveBranchId, , $branchStockSeparate] = $this->branchScope($business, $branchId);

        $prices = [];
        Product::query()
            ->where('business_id', $business->id)
            ->whereIn('id', $productIds)
            ->get()
            ->each(function (Product $p) use (&$prices, $effectiveBranchId, $branchStockSeparate) {
                $layers = $this->catalog->sellableLayersForProduct($p, $effectiveBranchId, $branchStockSeparate);
                $price  = $layers[0]['unit_sell_price'] ?? $this->catalog->posMetaForProduct($p, $effectiveBranchId, $branchStockSeparate)['unit_sell_price'];
                if ($price !== null && (float) $price > 0) {
                    $prices[(int) $p->id] = round((float) $price, 2);
                }
            });

        return $prices;
    }

    /**
     * Automatic pricing for a quotation's lines: every product line gets its standard price as
     * list_price. A user who may not change prices has unit_price set to it as well; one who may
     * keeps the price they typed — the difference is shown to the approver (price changes).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function applyStandardPrices(Business $business, array $items, bool $mayChangePrice, ?int $branchId = null): array
    {
        $isProduct = fn (array $i) => ($i['item_type'] ?? null) === 'product' && ! empty($i['product_id']);
        $prices    = $this->standardPrices($business, array_column(array_filter($items, $isProduct), 'product_id'), $branchId);

        return array_map(function (array $item) use ($isProduct, $prices, $mayChangePrice) {
            $standard = $isProduct($item) ? ($prices[(int) $item['product_id']] ?? null) : null;
            $item['list_price'] = $standard;
            if ($standard !== null && ! $mayChangePrice) {
                $item['unit_price'] = $standard;
            }

            return $item;
        }, $items);
    }

    /** @return array{0: ?int, 1: bool, 2: bool} effective branch, branch-separate products, branch-separate stock */
    private function branchScope(Business $business, ?int $branchId): array
    {
        $branchPosSeparate = (bool) get_settings('business.branch_pos_separate', false, $business);

        return [
            $branchPosSeparate ? $branchId : null,
            (bool) get_settings('business.branch_product_separate', false, $business),
            (bool) get_settings('business.branch_stock_separate', false, $business),
        ];
    }

    /**
     * One product's quotation info (for lines added from the inventory search) — null when not this business's.
     *
     * @return array{id: int, model_no: ?string, warranty: ?string, delivery: ?string}|null
     */
    public function productInfo(Business $business, int $productId): ?array
    {
        $p = Product::query()
            ->where('business_id', $business->id)
            ->find($productId, ['id', 'model_no', 'has_warranty', 'warranty_duration', 'courier_delivery', 'delivery_methods']);
        if (! $p) {
            return null;
        }
        $modelNo = trim((string) $p->model_no);

        return [
            'id'       => (int) $p->id,
            'model_no' => $modelNo !== '' ? $modelNo : null,
            'warranty' => $this->warranty($p),
            'delivery' => $this->delivery($p),
        ];
    }

    private function warranty(?Product $p): ?string
    {
        if (! $p?->has_warranty) {
            return null;
        }
        $duration = trim((string) $p->warranty_duration);

        return $duration !== '' ? $duration : 'Yes';
    }

    /** Courier partners the product ships with, e.g. "DHL Express (1,500.00), PickMe"; null when not delivered. */
    private function delivery(?Product $p): ?string
    {
        if (! $p?->courier_delivery) {
            return null;
        }
        $methods = collect($p->delivery_methods ?? [])
            ->filter(fn ($m) => is_array($m) && ! empty($m['key']))
            ->map(function (array $m) {
                $label = ProductController::DELIVERY_PARTNER_LABELS[$m['key']] ?? ucfirst((string) $m['key']);

                return isset($m['price']) && $m['price'] !== null
                    ? $label . ' (' . number_format((float) $m['price'], 2) . ')'
                    : $label;
            });

        return $methods->isNotEmpty() ? $methods->implode(', ') : 'Courier delivery';
    }
}
