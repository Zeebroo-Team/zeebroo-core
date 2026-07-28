<?php

namespace Modules\AutomationEditor\Console\Commands;

use Illuminate\Console\Command;
use Modules\AutomationEditor\Services\AutomationRunnerService;
use Modules\Business\Models\Business;
use Modules\Purchase\Models\ChequePayment;

class DetectExpiredChequesCommand extends Command
{
    protected $signature = 'automation:detect-expired-cheques';
    protected $description = 'Dispatch cheque.expired automation trigger for overdue cheques';

    public function handle(AutomationRunnerService $runner): int
    {
        $today = now()->toDateString();

        $overdue = ChequePayment::query()
            ->where('status', ChequePayment::STATUS_PENDING)
            ->whereDate('due_date', '<', $today)
            ->with(['goodsReceiveNote'])
            ->get();

        foreach ($overdue as $cheque) {
            try {
                $business = Business::find($cheque->business_id);
                if (! $business) {
                    continue;
                }

                $runner->dispatch('cheque.expired', $business, [
                    'event'  => 'cheque.expired',
                    'cheque' => [
                        'id'            => $cheque->id,
                        'cheque_number' => $cheque->cheque_number,
                        'amount'        => (float) $cheque->amount,
                        'due_date'      => $cheque->due_date instanceof \Carbon\Carbon
                            ? $cheque->due_date->toDateString()
                            : (string) $cheque->due_date,
                        'days_overdue'  => (int) now()->diffInDays($cheque->due_date, false) * -1,
                        'grn_number'    => $cheque->goodsReceiveNote?->grn_number,
                    ],
                ]);
            } catch (\Throwable) {}
        }

        $this->info("Processed {$overdue->count()} overdue cheque(s).");

        return self::SUCCESS;
    }
}
