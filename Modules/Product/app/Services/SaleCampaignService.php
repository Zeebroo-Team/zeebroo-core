<?php

namespace Modules\Product\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;
use Modules\Product\Models\SaleCampaign;
use Modules\Product\Support\CampaignDiscountCandidate;

class SaleCampaignService
{
    public function list(Business $business, string $q, string $status): Collection
    {
        $query = $business->saleCampaigns()
            ->with(['items.product', 'items.sellingUnit', 'imageFile'])
            ->orderByDesc('created_at');

        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }

        if ($status === 'active') {
            $today = now()->startOfDay();
            $query->where('is_active', true)
                  ->where(fn ($sq) => $sq->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
                  ->where(fn ($sq) => $sq->where('is_long_term', true)->orWhereNull('ends_at')->orWhere('ends_at', '>=', $today));
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query->get();
    }

    public function create(Business $business, array $data): SaleCampaign
    {
        return DB::transaction(function () use ($business, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            /** @var SaleCampaign $campaign */
            $campaign = $business->saleCampaigns()->create($data);

            if ($campaign->mode === 'individual' && ! empty($items)) {
                $campaign->items()->createMany($this->normalizeItems($items));
            }

            return $campaign;
        });
    }

    public function update(SaleCampaign $campaign, array $data): void
    {
        DB::transaction(function () use ($campaign, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $campaign->update($data);

            if ($campaign->mode === 'individual' && $items !== null) {
                $campaign->items()->delete();
                if (! empty($items)) {
                    $campaign->items()->createMany($this->normalizeItems($items));
                }
            } elseif ($campaign->mode === 'storewide') {
                $campaign->items()->delete();
            }
        });
    }

    public function delete(SaleCampaign $campaign): void
    {
        $campaign->delete();
    }

    public function campaignForBusiness(Business $business, SaleCampaign $campaign): ?SaleCampaign
    {
        return $business->saleCampaigns()->find($campaign->id);
    }

    private function normalizeItems(array $items): array
    {
        return collect($items)->values()->map(fn (array $item, int $i) => [
            'product_id'              => (int) $item['product_id'],
            'product_selling_unit_id' => $item['product_selling_unit_id'] ?? null,
            'discount_type'           => $item['discount_type'],
            'discount_value'          => $item['discount_value'],
            'sort_order'              => $i,
        ])->all();
    }

    /**
     * All currently-active campaign discounts applicable to the given product
     * IDs, normalized into CampaignDiscountCandidate entries so they can be
     * merged with ProductDiscountService::activeForProducts()'s results.
     *
     * @return Collection<int, CampaignDiscountCandidate>
     */
    public function activeForProducts(Business $business, array $productIds): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        $today = now()->startOfDay();

        $campaigns = $business->saleCampaigns()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
            ->where(fn ($q) => $q->where('is_long_term', true)->orWhereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->with(['items' => fn ($q) => $q->whereIn('product_id', $productIds)])
            ->get();

        $candidates = collect();

        foreach ($campaigns as $campaign) {
            if ($campaign->mode === 'storewide') {
                if ($campaign->discount_type === null || $campaign->discount_value === null) {
                    continue;
                }
                foreach ($productIds as $productId) {
                    $candidates->push(new CampaignDiscountCandidate(
                        product_id: (int) $productId,
                        product_selling_unit_id: null,
                        name: $campaign->name,
                        discount_type: $campaign->discount_type,
                        discount_value: (float) $campaign->discount_value,
                        campaign_id: (int) $campaign->id,
                    ));
                }
            } else {
                foreach ($campaign->items as $item) {
                    $candidates->push(new CampaignDiscountCandidate(
                        product_id: (int) $item->product_id,
                        product_selling_unit_id: $item->product_selling_unit_id,
                        name: $campaign->name,
                        discount_type: $item->discount_type,
                        discount_value: (float) $item->discount_value,
                        campaign_id: (int) $campaign->id,
                    ));
                }
            }
        }

        return $candidates;
    }

    /**
     * Product IDs targeted by currently-active campaigns. Returns `true` when
     * any active storewide campaign exists, since that effectively discounts
     * every sellable product (the caller should skip ID filtering in that case).
     *
     * @return list<int>|true
     */
    public function activeCampaignProductIds(Business $business): array|true
    {
        $today = now()->startOfDay();

        $campaigns = $business->saleCampaigns()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
            ->where(fn ($q) => $q->where('is_long_term', true)->orWhereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->with('items:id,sale_campaign_id,product_id')
            ->get();

        $ids = [];
        foreach ($campaigns as $campaign) {
            if ($campaign->mode === 'storewide') {
                return true;
            }
            foreach ($campaign->items as $item) {
                $ids[] = (int) $item->product_id;
            }
        }

        return array_values(array_unique($ids));
    }
}
