<?php

namespace Modules\Product\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductBundleItem;

class ProductBundleService
{
    /**
     * @param  list<array{product_id?: int|string, quantity?: float|string}>  $items
     */
    public function syncBundleItems(Product $bundle, bool $isBundle, array $items): void
    {
        $business = $bundle->business;
        if (!$business instanceof Business) {
            throw ValidationException::withMessages(['bundle_items' => 'Business not found for product.']);
        }

        if (!$isBundle) {
            $bundle->bundleItems()->delete();
            $bundle->forceFill(['is_bundle' => false])->save();

            return;
        }

        $normalized = $this->normalizeItems($items);
        if ($normalized === []) {
            throw ValidationException::withMessages([
                'bundle_items' => 'Add at least one product to this bundle.',
            ]);
        }

        $this->assertValidBundleItems($business, $bundle, $normalized);

        DB::transaction(function () use ($bundle, $normalized): void {
            $bundle->bundleItems()->delete();

            foreach ($normalized as $index => $row) {
                ProductBundleItem::query()->create([
                    'bundle_product_id' => $bundle->id,
                    'item_product_id' => $row['product_id'],
                    'quantity' => $row['quantity'],
                    'sort_order' => $index,
                ]);
            }

            $bundle->forceFill(['is_bundle' => true])->save();
        });
    }

    public function componentsTotalPrice(Product $bundle): ?float
    {
        if (!$bundle->is_bundle) {
            return null;
        }

        $bundle->loadMissing('bundleItems.itemProduct');

        $total = 0.0;
        $hasPrice = false;

        foreach ($bundle->bundleItems as $row) {
            $item = $row->itemProduct;
            if (!$item || $item->unit_price === null) {
                continue;
            }
            $hasPrice = true;
            $total += (float) $item->unit_price * (float) $row->quantity;
        }

        return $hasPrice ? round($total, 2) : null;
    }

    /**
     * @param  list<array{product_id?: int|string, quantity?: float|string}>  $items
     * @return list<array{product_id: int, quantity: float}>
     */
    private function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }

            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $quantity = (float) ($row['quantity'] ?? 1);
            if ($quantity <= 0) {
                continue;
            }

            $normalized[] = [
                'product_id' => $productId,
                'quantity' => round($quantity, 3),
            ];
        }

        $unique = [];
        foreach ($normalized as $row) {
            $unique[$row['product_id']] = $row;
        }

        return array_values($unique);
    }

    /**
     * @param  list<array{product_id: int, quantity: float}>  $items
     */
    private function assertValidBundleItems(Business $business, Product $bundle, array $items): void
    {
        $ids = array_column($items, 'product_id');

        if (in_array((int) $bundle->id, $ids, true)) {
            throw ValidationException::withMessages([
                'bundle_items' => 'A bundle cannot include itself.',
            ]);
        }

        $products = $business->products()->whereIn('id', $ids)->get()->keyBy('id');

        if ($products->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'bundle_items' => 'One or more bundled products are invalid for this business.',
            ]);
        }

        foreach ($products as $product) {
            if ($product->is_bundle) {
                throw ValidationException::withMessages([
                    'bundle_items' => 'Nested bundles are not supported. Remove "'.$product->name.'" or use simple products only.',
                ]);
            }
        }

    }

    /**
     * @return array<int, array{id: int, name: string, sku: ?string, unit_price: ?float}>
     */
    public function pickerCatalogForBusiness(Business $business, ?Product $exclude = null): array
    {
        return $business->products()
            ->where('is_bundle', false)
            ->when($exclude, fn ($q) => $q->whereKeyNot($exclude->id))
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit_price'])
            ->map(static fn (Product $p) => [
                'id' => (int) $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'unit_price' => $p->unit_price !== null ? (float) $p->unit_price : null,
            ])
            ->values()
            ->all();
    }
}
