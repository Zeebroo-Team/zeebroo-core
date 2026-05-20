<?php

namespace Modules\Product\Console\Commands;

use Illuminate\Console\Command;
use Modules\Business\Models\Business;
use Modules\Product\Services\DemoProductInsertService;

class InsertDemoProductsCommand extends Command
{
    protected $signature = 'product:demo-insert
        {--business= : Business ID (defaults to the first business)}
        {--count=12 : How many demo products to insert (max 12)}
        {--dry-run : Show what would be created without saving}
        {--force : Skip confirmation prompt}';

    protected $description = 'Insert demo products into the catalog for a business';

    public function __construct(
        private readonly DemoProductInsertService $demoProducts,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $business = $this->resolveBusiness();
        if ($business === null) {
            return self::FAILURE;
        }

        $count = max(1, min(12, (int) $this->option('count')));
        $dryRun = (bool) $this->option('dry-run');

        $this->line('Business: #'.$business->id.' — '.$business->name);
        $this->line('Demo products to insert: '.$count.($dryRun ? ' (dry run)' : ''));

        if (! $dryRun && ! $this->option('force') && ! $this->confirm('Create demo products for this business?', true)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $result = $this->demoProducts->insertForBusiness($business, $count, $dryRun);

        if ($dryRun) {
            $this->info('Would create '.$result['created'].' product(s), skip '.$result['skipped'].' duplicate name(s).');

            return self::SUCCESS;
        }

        if ($result['products']->isNotEmpty()) {
            $this->table(
                ['Name', 'SKU', 'Unit price', 'Stock'],
                $result['products']->map(fn ($product) => [
                    $product->name,
                    $product->sku ?? '—',
                    $product->unit_price !== null ? number_format((float) $product->unit_price, 2) : '—',
                    number_format((float) $product->stock_quantity, 3),
                ])->all(),
            );
        }

        $this->info('Created '.$result['created'].' demo product(s). Skipped '.$result['skipped'].' (name already exists).');

        return self::SUCCESS;
    }

    private function resolveBusiness(): ?Business
    {
        $businessId = $this->option('business');
        if (filled($businessId)) {
            $business = Business::query()->find((int) $businessId);
            if ($business === null) {
                $this->error('Business not found: '.$businessId);
            }

            return $business;
        }

        $business = Business::query()->orderBy('id')->first();
        if ($business === null) {
            $this->error('No business found. Create a business first or pass --business=ID.');
        }

        return $business;
    }
}
