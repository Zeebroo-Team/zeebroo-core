<?php

namespace Modules\Ecommerce\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Business\Models\Business;
use Modules\Ecommerce\Models\Listing;
use Modules\Ecommerce\Models\Order;
use Modules\Ecommerce\Models\Store;

class StoreService
{
    /** The business's store, created on first access with sensible defaults (unpublished). */
    public function forBusiness(Business $business): Store
    {
        $store = Store::where('business_id', $business->id)->first();
        if ($store) {
            return $store;
        }

        return Store::create([
            'business_id' => $business->id,
            'name'        => (string) $business->name,
            'slug'        => $this->uniqueSlug((string) $business->name),
            'tagline'     => $business->short_description ?: null,
            'about'       => $business->description ?: null,
            'currency'    => (string) ($business->getSetting('business.currency', '') ?: '') ?: null,
            'is_published' => false,
        ]);
    }

    public function update(Store $store, array $data): Store
    {
        if (array_key_exists('slug', $data)) {
            $data['slug'] = $this->uniqueSlug((string) $data['slug'], $store->id);
        }

        $store->fill($data)->save();

        return $store->fresh();
    }

    public function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'store';
        $base = Str::limit($base, 70, '');
        $slug = $base;
        $n    = 2;

        while (Store::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }

    /** Headline numbers + 30-day revenue series + top products for the desktop dashboard. */
    public function dashboard(Business $business): array
    {
        $orders = Order::where('business_id', $business->id);
        $live   = (clone $orders)->where('status', '!=', 'cancelled');

        $byStatus = (clone $orders)
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        $since  = Carbon::today()->subDays(29);
        $series = (clone $live)
            ->where('placed_at', '>=', $since)
            ->select(DB::raw('DATE(placed_at) as d'), DB::raw('SUM(total) as revenue'), DB::raw('COUNT(*) as orders'))
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $days = [];
        for ($i = 0; $i < 30; $i++) {
            $d      = $since->copy()->addDays($i)->toDateString();
            $days[] = [
                'date'    => $d,
                'revenue' => round((float) ($series[$d]->revenue ?? 0), 2),
                'orders'  => (int) ($series[$d]->orders ?? 0),
            ];
        }

        $top = DB::table('ecom_order_items as i')
            ->join('ecom_orders as o', 'o.id', '=', 'i.order_id')
            ->where('o.business_id', $business->id)
            ->where('o.status', '!=', 'cancelled')
            ->select('i.name', DB::raw('SUM(i.quantity) as qty'), DB::raw('SUM(i.line_total) as revenue'))
            ->groupBy('i.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'quantity' => (float) $r->qty, 'revenue' => round((float) $r->revenue, 2)]);

        return [
            'total_orders'     => (int) (clone $orders)->count(),
            'pending_orders'   => (int) ($byStatus['pending'] ?? 0),
            'orders_today'     => (int) (clone $orders)->whereDate('placed_at', Carbon::today())->count(),
            'revenue_total'    => round((float) (clone $live)->sum('total'), 2),
            'revenue_30d'      => round(array_sum(array_column($days, 'revenue')), 2),
            'status_counts'    => collect(Order::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($byStatus[$s] ?? 0)]),
            'listed_products'  => (int) Listing::where('business_id', $business->id)->where('is_published', true)->count(),
            'series'           => $days,
            'top_products'     => $top,
        ];
    }
}
