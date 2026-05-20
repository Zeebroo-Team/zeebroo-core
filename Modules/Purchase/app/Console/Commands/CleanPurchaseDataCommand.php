<?php

namespace Modules\Purchase\Console\Commands;

use Illuminate\Console\Command;
use Modules\Business\Models\Business;
use Modules\Purchase\Services\PurchaseCleanService;

class CleanPurchaseDataCommand extends Command
{
    protected $signature = 'purchase:clean
        {--business= : Limit cleanup to a business ID}
        {--grns-only : Remove goods receive notes only; keep purchase orders}
        {--dry-run : Show what would be deleted without making changes}
        {--force : Skip confirmation prompt}';

    protected $description = 'Remove purchase orders and/or goods receive notes (reverses stock and ledger payments first)';

    public function __construct(
        private readonly PurchaseCleanService $cleanService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $businessId = $this->option('business');
        $businessId = filled($businessId) ? (int) $businessId : null;
        $grnsOnly = (bool) $this->option('grns-only');
        $dryRun = (bool) $this->option('dry-run');

        if ($businessId !== null && ! Business::query()->whereKey($businessId)->exists()) {
            $this->error('Business not found: '.$businessId);

            return self::FAILURE;
        }

        $scopeLabel = $businessId !== null
            ? 'business #'.$businessId.' ('.(Business::query()->find($businessId)?->name ?? 'unknown').')'
            : 'all businesses';

        $modeLabel = $grnsOnly
            ? 'goods receive notes only (purchase orders kept)'
            : 'purchase orders and goods receive notes';

        $this->warn($dryRun
            ? 'Dry run — no data will be changed.'
            : 'This will permanently delete '.$modeLabel.' for '.$scopeLabel.'.');
        $this->line('Stock quantities will be reversed and account balances restored for GRN payments.');

        if (! $dryRun && ! $this->option('force') && ! $this->confirm('Continue?', false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $stats = $this->cleanService->clean($businessId, $grnsOnly, $dryRun);

        $this->table(
            ['Metric', 'Count / amount'],
            [
                ['Business scope', (string) $stats['businesses']],
                ['Purchase orders', (string) $stats['purchases']],
                ['Purchase line items', (string) $stats['purchase_items']],
                ['Goods receive notes', (string) $stats['goods_receive_notes']],
                ['GRN line items', (string) $stats['goods_receive_note_items']],
                ['Cheque payments', (string) $stats['cheque_payments']],
                ['Ledger transactions', (string) $stats['ledger_transactions']],
                ['Stock lines reversed', (string) $stats['stock_lines_reversed']],
                ['Ledger amount restored', number_format($stats['ledger_amount_restored'], 2)],
                ['PO statuses reset to ordered', (string) $stats['purchases_status_reset']],
            ],
        );

        $this->info($dryRun ? 'Dry run complete.' : 'Purchase data cleaned successfully.');

        return self::SUCCESS;
    }
}
