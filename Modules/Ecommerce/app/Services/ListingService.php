<?php

namespace Modules\Ecommerce\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Ecommerce\Models\Listing;
use Modules\Product\Models\Product;

class ListingService
{
    public function list(Business $business, array $filters = []): Collection
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $state  = (string) ($filters['state'] ?? '');

        return Listing::query()
            ->where('ecom_listings.business_id', $business->id)
            ->with('product.imageFile')
            ->when($state === 'published', fn ($q) => $q->where('is_published', true))
            ->when($state === 'hidden', fn ($q) => $q->where('is_published', false))
            ->when($state === 'featured', fn ($q) => $q->where('is_featured', true))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('title', 'like', "%{$search}%")
                      ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();
    }

    /** Catalog products not yet on the store — for the "Add products" picker. */
    public function candidates(Business $business, string $search = '', int $limit = 50): Collection
    {
        $listed = Listing::where('business_id', $business->id)->pluck('product_id');

        return Product::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->whereNotIn('id', $listed)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
            ->with('imageFile')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /** @param int[] $productIds */
    public function publish(Business $business, array $productIds): int
    {
        $valid = Product::where('business_id', $business->id)->whereIn('id', $productIds)->pluck('id');
        $next  = (int) Listing::where('business_id', $business->id)->max('sort_order');
        $added = 0;

        foreach ($valid as $productId) {
            $listing = Listing::firstOrCreate(
                ['business_id' => $business->id, 'product_id' => $productId],
                ['is_published' => true, 'sort_order' => ++$next],
            );
            if ($listing->wasRecentlyCreated) {
                $added++;
            }
        }

        return $added;
    }

    public function update(Listing $listing, array $data): Listing
    {
        $listing->fill($data)->save();

        return $listing->fresh('product.imageFile');
    }

    /** Published listings for the public storefront (active products only). */
    public function storefront(Business $business, string $search = ''): Collection
    {
        return Listing::query()
            ->where('business_id', $business->id)
            ->where('is_published', true)
            ->whereHas('product', fn ($p) => $p->where('is_active', true))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn ($w) => $w->where('title', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%")));
            })
            ->with('product.imageFile')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();
    }

    public function format(Listing $l): array
    {
        $p = $l->product;

        return [
            'id'               => $l->id,
            'product_id'       => $l->product_id,
            'title'            => $l->displayTitle(),
            'custom_title'     => $l->title,
            'description'      => $l->description,
            'product_name'     => $p?->name,
            'sku'              => $p?->sku,
            'image_url'        => $p?->imageUrl(),
            'base_price'       => (float) ($p?->unit_price ?? 0),
            'online_price'     => $l->online_price !== null ? (float) $l->online_price : null,
            'compare_at_price' => $l->compare_at_price !== null ? (float) $l->compare_at_price : null,
            'price'            => $l->price(),
            'stock_quantity'   => (float) ($p?->stock_quantity ?? 0),
            'product_active'   => (bool) ($p?->is_active ?? false),
            'is_published'     => $l->is_published,
            'is_featured'      => $l->is_featured,
            'sort_order'       => $l->sort_order,
        ];
    }
}
