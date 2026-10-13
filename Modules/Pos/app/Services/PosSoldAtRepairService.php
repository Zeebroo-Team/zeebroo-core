<?php

namespace Modules\Pos\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Pos\Models\Sale;

/**
 * Repairs sale times rewritten by the old "ON UPDATE CURRENT_TIMESTAMP" on
 * pos_sales.sold_at (see migration 2026_10_12_120000).
 *
 * Every sale gets sold_at = now() in the same INSERT that sets created_at, so
 * for a normal sale the two are equal and created_at is the true sale time.
 * The bug only ever moved sold_at forward, so a normal sale whose sold_at is
 * more than [GRACE_SECONDS] after created_at was rewritten.
 *
 * Offline sales from the mobile app (client_uuid set) carry the phone's sale
 * time instead, which can't be recovered from created_at — they are listed
 * but never changed.
 */
class PosSoldAtRepairService
{
    public const GRACE_SECONDS = 60;

    /** True while the column still rewrites itself (run the migration first). */
    public function columnStillAutoUpdates(): bool
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }
        $info = DB::selectOne(
            "SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_sales' AND COLUMN_NAME = 'sold_at'",
        );

        return $info !== null && stripos((string) $info->EXTRA, 'on update') !== false;
    }

    /** Normal sales whose sold_at was rewritten. */
    public function damaged(?int $businessId = null): Builder
    {
        return Sale::query()
            ->whereNull('client_uuid')
            ->whereNotNull('created_at')
            ->whereRaw('sold_at > created_at + INTERVAL '.self::GRACE_SECONDS.' SECOND')
            ->when($businessId !== null, fn ($q) => $q->where('business_id', $businessId));
    }

    /** Offline sales whose phone time was later rewritten (not repairable). */
    public function damagedOffline(?int $businessId = null): Builder
    {
        return Sale::query()
            ->whereNotNull('client_uuid')
            ->whereRaw('sold_at > created_at + INTERVAL '.self::GRACE_SECONDS.' SECOND')
            ->when($businessId !== null, fn ($q) => $q->where('business_id', $businessId));
    }

    /** @return Collection<int, object> a few examples for the dry run */
    public function sample(?int $businessId = null, int $limit = 10): Collection
    {
        return $this->damaged($businessId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'business_id', 'sale_number', 'status', 'created_at', 'sold_at', 'updated_at']);
    }

    /**
     * Sets sold_at back to created_at for every damaged normal sale, in
     * chunks. updated_at is left alone. Returns the number of rows fixed.
     */
    public function repair(?int $businessId = null): int
    {
        $fixed = 0;
        $this->damaged($businessId)->select('id')->chunkById(500, function ($rows) use (&$fixed) {
            $fixed += DB::table('pos_sales')
                ->whereIn('id', $rows->pluck('id'))
                ->update(['sold_at' => DB::raw('created_at')]);
        });

        return $fixed;
    }
}
