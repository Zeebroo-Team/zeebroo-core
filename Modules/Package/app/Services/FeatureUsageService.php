<?php

namespace Modules\Package\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Models\Business;

/**
 * Answers "has this business actually used feature X?" by counting its records
 * in the tables listed under config('features.usage'). Used to warn only about
 * features with real data when moving to a package that doesn't include them.
 */
class FeatureUsageService
{
    /** @var array<string, bool> */
    private array $usableTables = [];

    /**
     * @param  list<string>  $featureKeys
     * @return array<string, array{total: int, items: list<array{label: string, count: int}>}>
     *               Only features with at least one record are returned.
     */
    public function usage(Business $business, array $featureKeys): array
    {
        $map = (array) config('features.usage', []);
        $result = [];

        foreach ($featureKeys as $key) {
            $items = [];
            $total = 0;

            foreach ((array) ($map[$key] ?? []) as $table => $label) {
                if (! $this->tableUsable($table)) {
                    continue;
                }

                $count = (int) DB::table($table)->where('business_id', $business->id)->count();
                if ($count > 0) {
                    $items[] = ['label' => $label, 'count' => $count];
                    $total += $count;
                }
            }

            if ($total > 0) {
                $result[$key] = ['total' => $total, 'items' => $items];
            }
        }

        return $result;
    }

    private function tableUsable(string $table): bool
    {
        return $this->usableTables[$table] ??= Schema::hasTable($table) && Schema::hasColumn($table, 'business_id');
    }
}
