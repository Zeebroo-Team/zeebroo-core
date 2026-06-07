<?php

namespace Modules\Transaction\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Modules\Account\Models\Bill;
use Modules\Account\Models\Loan;
use Modules\Account\Models\Rental;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\PayrollCycle;
use Modules\Transaction\Models\LedgerTransaction;

class TransactionService
{
    /** @return LengthAwarePaginator<LedgerTransaction>|Collection<int, LedgerTransaction> */
    public function listForBusiness(?Business $business, int $perPage = 20): LengthAwarePaginator|Collection
    {
        if (! $business) {
            return collect();
        }

        return LedgerTransaction::query()
            ->where('business_id', $business->id)
            ->with([
                'transactionable' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        Loan::class => ['bank'],
                        Rental::class => ['warehouse'],
                        Bill::class => ['warehouse'],
                        PayrollCycle::class => ['ruleSet'],
                    ]);
                },
                'deductAccount.bankType',
                'deductAccount.bank',
            ])
            ->orderByDesc('occurrence_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
