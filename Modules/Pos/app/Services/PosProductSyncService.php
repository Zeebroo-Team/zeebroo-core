<?php

namespace Modules\Pos\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductDiscountService;
use Modules\Product\Services\SaleCampaignService;

/**
 * Feeds the mobile app's on-device product cache ("Offline products").
 *
 * The app keeps a local copy of the POS catalog and keeps it fresh by
 * comparing a light per-product fingerprint index against what it holds:
 * new/changed fingerprints are re-downloaded via {@see cardsByIds()}, IDs
 * missing from the index are deleted locally (products are hard-deleted, so
 * there is no "deleted since" log to ask for).
 *
 * Product cards are built with the exact same code path as
 * GET /v1/pos/online/products ({@see PosCatalogService::productCardForProduct()}),
 * so cached cards are identical to the online list. This service only reads.
 */
class PosProductSyncService
{
    /** Bump when the card format changes so every device re-downloads. */
    private const CARD_FORMAT_VERSION = 1;

    public function __construct(
        private readonly PosCatalogService $catalog,
        private readonly ProductDiscountService $discountService,
        private readonly SaleCampaignService $campaignService,
    ) {
    }

    /**
     * @return array{version: string, items: list<array{id: int, fp: string}>}
     */
    public function index(
        Business $business,
        ?int $branchId,
        bool $branchProductSeparate,
        bool $branchStockSeparate,
    ): array {
        $products = $this->sellableQuery($business, $branchId, $branchProductSeparate)
            ->orderBy('id')
            ->get(['id', 'updated_at', 'stock_quantity']);

        $ids = $products->pluck('id')->map(fn ($id) => (int) $id)->all();
        $parts = $this->fingerprintParts($business, $ids, $branchId, $branchStockSeparate);

        $items = [];
        foreach ($products as $product) {
            $id = (int) $product->id;
            $items[] = [
                'id' => $id,
                'fp' => substr(md5(implode('|', [
                    $product->updated_at?->getTimestamp() ?? 0,
                    (string) $product->stock_quantity,
                    $parts['layers'][$id] ?? '',
                    $parts['units'][$id] ?? '',
                    $parts['categories'][$id] ?? '',
                    $parts['discounts'][$id] ?? '',
                    $parts['reserved'][$id] ?? '',
                ])), 0, 16),
            ];
        }

        return [
            // Anything that changes every card at once (pricing markup,
            // branch mode, card format) — a new version means "resync all".
            'version' => substr(md5(implode('|', [
                self::CARD_FORMAT_VERSION,
                (string) get_settings('product.stock_selling_markup_percent', 25, $business),
                $branchId ?? '',
                $branchProductSeparate ? 1 : 0,
                $branchStockSeparate ? 1 : 0,
            ])), 0, 16),
            'items' => $items,
        ];
    }

    /**
     * Full POS product cards for [ids] — same shape as one entry of
     * GET /v1/pos/online/products, including reserved/available stock.
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public function cardsByIds(
        Business $business,
        array $ids,
        ?int $branchId,
        bool $branchProductSeparate,
        bool $branchStockSeparate,
    ): array {
        if ($ids === []) {
            return [];
        }

        $items = $this->sellableQuery($business, $branchId, $branchProductSeparate)
            ->whereIn('id', $ids)
            ->with(['productUnit', 'imageFile', 'categories'])
            ->orderBy('name')
            ->get();

        $productIds = $items->pluck('id')->all();
        $activeDiscounts = $this->discountService->activeForProducts($business, $productIds)
            ->concat($this->campaignService->activeForProducts($business, $productIds));

        $cards = $items
            ->map(fn (Product $product) => $this->catalog->productCardForProduct($product, $branchId, $branchStockSeparate, $activeDiscounts))
            ->values()
            ->all();

        $reserved = $this->reservedQuantities((int) $business->id, $productIds);
        $batchSkus = $this->batchSkus($business, $productIds, $branchId, $branchStockSeparate);
        foreach ($cards as &$card) {
            $qty = $reserved[$card['id']] ?? 0.0;
            $stock = (float) ($card['stock_quantity'] ?? 0);
            $card['reserved_qty'] = round($qty, 3);
            $card['available_stock'] = round(max(0.0, $stock - $qty), 3);
            // Sync-only extra: batch barcodes so the app can scan offline
            // (the card's `layers` don't carry them).
            $card['batch_skus'] = $batchSkus[$card['id']] ?? [];
        }
        unset($card);

        return $cards;
    }

    /** Same product filter as the POS product list (no search/category). */
    private function sellableQuery(Business $business, ?int $branchId, bool $branchProductSeparate): Builder
    {
        $query = Product::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->where('is_bundle', false);

        if ($branchProductSeparate && $branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        return $query;
    }

    /**
     * Per-product strings for everything outside the products row that
     * changes a POS card: stock batches, selling units, categories, active
     * discounts/campaigns and reserved quantities. A handful of grouped
     * queries for the whole catalog, not per product.
     *
     * @param  list<int>  $ids
     * @return array<string, array<int, string>>
     */
    private function fingerprintParts(Business $business, array $ids, ?int $branchId, bool $branchStockSeparate): array
    {
        $parts = ['layers' => [], 'units' => [], 'categories' => [], 'discounts' => [], 'reserved' => []];
        if ($ids === []) {
            return $parts;
        }

        foreach (array_chunk($ids, 1000) as $chunk) {
            DB::table('product_stock_layers')
                ->where('business_id', $business->id)
                ->whereIn('product_id', $chunk)
                ->where('quantity_remaining', '>', 0)
                ->when($branchStockSeparate && $branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->groupBy('product_id')
                ->selectRaw('product_id, COUNT(*) as n, SUM(quantity_remaining) as qty, MAX(updated_at) as changed')
                ->get()
                ->each(function ($row) use (&$parts) {
                    $parts['layers'][(int) $row->product_id] = $row->n.':'.$row->qty.':'.$row->changed;
                });

            DB::table('product_selling_units')
                ->whereIn('product_id', $chunk)
                ->groupBy('product_id')
                ->selectRaw('product_id, COUNT(*) as n, MAX(updated_at) as changed')
                ->get()
                ->each(function ($row) use (&$parts) {
                    $parts['units'][(int) $row->product_id] = $row->n.':'.$row->changed;
                });

            DB::table('product_product_category')
                ->whereIn('product_id', $chunk)
                ->orderBy('product_category_id')
                ->get(['product_id', 'product_category_id'])
                ->groupBy('product_id')
                ->each(function ($rows, $productId) use (&$parts) {
                    $parts['categories'][(int) $productId] = $rows->pluck('product_category_id')->implode(',');
                });

            $this->discountService->activeForProducts($business, $chunk)
                ->concat($this->campaignService->activeForProducts($business, $chunk))
                ->groupBy('product_id')
                ->each(function ($discounts, $productId) use (&$parts) {
                    $parts['discounts'][(int) $productId] = $discounts
                        ->map(fn ($d) => ($d->product_selling_unit_id ?? '').':'.$d->discount_type.':'.$d->discount_value.':'.$d->name)
                        ->sort()
                        ->implode(',');
                });

            foreach ($this->reservedQuantities((int) $business->id, $chunk) as $productId => $qty) {
                $parts['reserved'][$productId] = (string) $qty;
            }
        }

        return $parts;
    }

    /**
     * Batch barcodes of in-stock layers, the same layers the POS card lists.
     *
     * @param  list<int>  $ids
     * @return array<int, list<array{layer_id: int, sku: string}>>
     */
    private function batchSkus(Business $business, array $ids, ?int $branchId, bool $branchStockSeparate): array
    {
        if ($ids === []) {
            return [];
        }

        $out = [];
        DB::table('product_stock_layers')
            ->where('business_id', $business->id)
            ->whereIn('product_id', $ids)
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('batch_sku')
            ->where('batch_sku', '!=', '')
            ->when($branchStockSeparate && $branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('id')
            ->get(['id', 'product_id', 'batch_sku'])
            ->each(function ($row) use (&$out) {
                $out[(int) $row->product_id][] = ['layer_id' => (int) $row->id, 'sku' => (string) $row->batch_sku];
            });

        return $out;
    }

    /**
     * Quantities held by open invoices and active quotations — mirrors
     * PosCatalogApiController::enrichWithReservedStock().
     *
     * @param  list<int>  $ids
     * @return array<int, float>
     */
    private function reservedQuantities(int $businessId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $invoice = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.business_id', $businessId)
            ->whereIn('invoices.status', ['draft', 'sent'])
            ->whereIn('invoice_items.product_id', $ids)
            ->groupBy('invoice_items.product_id')
            ->selectRaw('invoice_items.product_id, SUM(invoice_items.quantity) as qty')
            ->pluck('qty', 'product_id');

        $quotation = DB::table('quotation_items')
            ->join('quotations', 'quotation_items.quotation_id', '=', 'quotations.id')
            ->where('quotations.business_id', $businessId)
            ->whereIn('quotations.status', ['draft', 'sent', 'accepted'])
            ->whereIn('quotation_items.product_id', $ids)
            ->groupBy('quotation_items.product_id')
            ->selectRaw('quotation_items.product_id, SUM(quotation_items.quantity) as qty')
            ->pluck('qty', 'product_id');

        $out = [];
        foreach ($ids as $id) {
            $qty = (float) ($invoice[$id] ?? 0) + (float) ($quotation[$id] ?? 0);
            if ($qty > 0) {
                $out[$id] = $qty;
            }
        }

        return $out;
    }
}
