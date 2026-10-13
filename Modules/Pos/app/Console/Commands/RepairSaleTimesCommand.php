<?php

namespace Modules\Pos\Console\Commands;

use Illuminate\Console\Command;
use Modules\Pos\Services\PosSoldAtRepairService;

/**
 * php artisan pos:repair-sold-at            → dry run: shows what would change
 * php artisan pos:repair-sold-at --apply    → changes it (back up the DB first)
 */
class RepairSaleTimesCommand extends Command
{
    protected $signature = 'pos:repair-sold-at
        {--apply : Actually update the rows (default is a dry run)}
        {--business= : Only this business id}';

    protected $description = 'Restore POS sale times (sold_at) rewritten by the old ON UPDATE CURRENT_TIMESTAMP column default';

    public function handle(PosSoldAtRepairService $repair): int
    {
        $business = $this->option('business') !== null ? (int) $this->option('business') : null;

        if ($repair->columnStillAutoUpdates()) {
            $this->error('pos_sales.sold_at still has ON UPDATE CURRENT_TIMESTAMP. Run "php artisan migrate" first.');

            return self::FAILURE;
        }

        $count = $repair->damaged($business)->count();
        $offline = $repair->damagedOffline($business)->count();

        $this->info("Sales whose time was rewritten: {$count}".($business !== null ? " (business {$business})" : ''));
        if ($count > 0) {
            $this->table(
                ['id', 'business', 'sale', 'status', 'created_at (true time)', 'sold_at now', 'updated_at'],
                $repair->sample($business)->map(fn ($s) => [
                    $s->id, $s->business_id, $s->sale_number, $s->status,
                    $s->created_at, $s->sold_at, $s->updated_at,
                ])->all(),
            );
        }
        if ($offline > 0) {
            $this->warn("{$offline} mobile offline sale(s) also had their time rewritten. Their phone time can't be recovered, so they are left unchanged.");
        }

        if (! $this->option('apply')) {
            $this->line('Dry run — nothing changed. Back up the database, then run again with --apply.');

            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('Nothing to repair.');

            return self::SUCCESS;
        }

        $fixed = $repair->repair($business);
        $this->info("Repaired {$fixed} sale(s): sold_at set back to created_at.");

        return self::SUCCESS;
    }
}
