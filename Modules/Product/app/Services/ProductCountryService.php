<?php

namespace Modules\Product\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCountry;

class ProductCountryService
{
    /**
     * @return Collection<int, ProductCountry> each with a `products_count` attribute
     */
    public function list(Business $business, string $q = '', string $status = ''): Collection
    {
        $query = $business->productCountries();

        if ($q !== '') {
            $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"));
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $countries = $query->get();
        $counts    = $this->usageCounts($business, $countries->pluck('id')->all());

        return $countries->each(fn (ProductCountry $c) => $c->setAttribute('products_count', $counts[$c->id] ?? 0));
    }

    public function create(Business $business, array $data): ProductCountry
    {
        $country = $business->productCountries()->create($this->normalize($data));

        return $country->setAttribute('products_count', 0);
    }

    public function update(ProductCountry $country, array $data): ProductCountry
    {
        $country->fill($this->normalize($data))->save();
        $country->refresh();

        return $country->setAttribute('products_count', $this->usageCount($country));
    }

    public function delete(ProductCountry $country): bool
    {
        return (bool) $country->delete();
    }

    public function usageCount(ProductCountry $country): int
    {
        return $country->productsUsing()->count();
    }

    public function countryForBusiness(Business $business, ProductCountry $country): ?ProductCountry
    {
        return (int) $country->business_id === (int) $business->id ? $country : null;
    }

    /**
     * For imports: the business's country whose name or code matches
     * (case-insensitive), created as active when there is none. Null for blank input.
     */
    public function findOrCreateByName(Business $business, ?string $name): ?ProductCountry
    {
        $name = mb_substr(trim((string) $name), 0, 120);
        if ($name === '') {
            return null;
        }

        $lower = mb_strtolower($name);
        $match = $business->productCountries()
            ->where(fn ($w) => $w->whereRaw('LOWER(name) = ?', [$lower])->orWhereRaw('LOWER(code) = ?', [$lower]))
            ->orderByRaw('LOWER(name) = ? DESC', [$lower])
            ->first();

        return $match ?? $this->create($business, ['name' => $name, 'is_active' => true]);
    }

    /** True when the id is null or names a country of this business. */
    public function belongsToBusiness(Business $business, mixed $id): bool
    {
        if ($id === null || $id === '') {
            return true;
        }

        return $business->productCountries()->whereKey((int) $id)->exists();
    }

    /**
     * Products per country, counting a product once even when it uses the
     * same country as both origin and manufacture country.
     *
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    private function usageCounts(Business $business, array $ids): array
    {
        if (! $ids) {
            return [];
        }

        $origin = Product::query()
            ->where('business_id', $business->id)
            ->whereIn('origin_country_id', $ids)
            ->select(['id as product_id', 'origin_country_id as country_id']);

        $manufacture = Product::query()
            ->where('business_id', $business->id)
            ->whereIn('manufacture_country_id', $ids)
            ->select(['id as product_id', 'manufacture_country_id as country_id']);

        return DB::query()
            ->fromSub($origin->union($manufacture), 'u')
            ->groupBy('country_id')
            ->selectRaw('country_id, COUNT(DISTINCT product_id) as cnt')
            ->pluck('cnt', 'country_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    private function normalize(array $data): array
    {
        if (array_key_exists('name', $data)) {
            $data['name'] = trim((string) $data['name']);
        }
        if (array_key_exists('code', $data)) {
            $code         = strtoupper(trim((string) $data['code']));
            $data['code'] = $code !== '' ? $code : null;
        }

        return $data;
    }
}
