<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * pos_sales.sold_at and pos_sale_returns.returned_at were created as plain
 * NOT NULL timestamps. On MySQL/MariaDB servers running with
 * explicit_defaults_for_timestamp=OFF, the first such column in a table is
 * silently given "DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", so
 * every later UPDATE of the row (checkout totals, void, settlement…) rewrites
 * the sale time with the database clock.
 *
 * This only changes the column definition (drops ON UPDATE, keeps the
 * default and every stored value). It does nothing on databases that never
 * had the problem. Rows already rewritten are repaired separately with
 * `php artisan pos:repair-sold-at` (dry run first).
 */
return new class extends Migration
{
    /** @var array<string, string> table => column */
    private array $columns = [
        'pos_sales'        => 'sold_at',
        'pos_sale_returns' => 'returned_at',
    ];

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($this->columns as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $info = DB::selectOne(
                'SELECT IS_NULLABLE, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column],
            );
            if (! $info || stripos((string) $info->EXTRA, 'on update') === false) {
                continue; // already fine
            }

            $null = $info->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` TIMESTAMP {$null} DEFAULT CURRENT_TIMESTAMP");
        }
    }

    public function down(): void
    {
        // Intentionally empty: putting ON UPDATE back would re-introduce the bug.
    }
};
