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
        $branchPosSeparate     = (bool) get_settings('business.branch_pos_separate', false, $business);
        $branchProductSeparate = (bool) get_settings('business.branch_product_separate', false, $business);
        $branchStockSeparate   = (bool) get_settings('business.branch_stock_separate', false, $business);
        $effectiveBranchId     = $branchPosSeparate ? $branchId : null;

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

        // Fields the POS card does not carry
        $extra = Product::query()
            ->whereIn('id', array_column($cards, 'id'))
            ->get(['id', 'model_no', 'has_warranty', 'warranty_duration', 'courier_delivery', 'delivery_methods'])
            ->keyBy('id');

        $models = array_map(function (array $card) use ($extra) {
            $p = $extra->get($card['id']);
            $modelNo = trim((string) ($p?->model_no ?? ''));

            return [
                'id'              => $card['id'],
                'name'            => $card['name'],
                'sku'             => $card['sku'],
                'model_no'        => $modelNo !== '' ? $modelNo : null,
                'label'           => $modelNo !== '' ? $modelNo : $card['name'],
                'unit'            => $card['unit'],
                'unit_sell_price' => $card['unit_sell_price'] !== null ? round((float) $card['unit_sell_price'], 2) : 0.0,
                'stock_quantity'  => $card['stock_quantity'] !== null ? (float) $card['stock_quantity'] : null,
                'warranty'        => $this->warranty($p),
                'delivery'        => $this->delivery($p),
            ];
        }, $cards);

        usort($models, fn (array $a, array $b) => strnatcasecmp($a['label'], $b['label']));

        return $models;
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
