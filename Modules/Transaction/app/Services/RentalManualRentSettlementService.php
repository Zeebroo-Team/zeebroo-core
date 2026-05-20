<?php

namespace Modules\Transaction\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Account;
use Modules\Account\Models\Rental;
use Modules\Account\Services\AccountService;
use Modules\Account\Services\RentalService;
use Modules\Business\Models\Business;
use Modules\Transaction\Models\LedgerTransaction;

class RentalManualRentSettlementService
{
    public function __construct(
        private readonly RentalService $rentalSchedule,
        private readonly AccountService $accountService,
    ) {}

    /**
     * Ledger row for one scheduled rent billing date; debits the selected account balance.
     */
    public function settle(
        Rental $rental,
        Business $business,
        User $user,
        string $occurrenceDateYmd,
        int $deductAccountId,
    ): LedgerTransaction {
        if ($rental->user_id !== $user->id || (int) $rental->business_id !== (int) $business->id) {
            abort(403);
        }

        $rental->loadMissing(['business']);

        try {
            $occurrence = Carbon::parse($occurrenceDateYmd)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'occurrence_date' => 'Invalid billing date.',
            ]);
        }

        $schedule = $this->rentalSchedule->rentalScheduledBillingDates($rental);
        $periodNumber = 0;
        $onSchedule = false;
        foreach ($schedule as $idx => $dueDate) {
            if ($dueDate->copy()->startOfDay()->toDateString() === $occurrence->toDateString()) {
                $periodNumber = $idx + 1;
                $onSchedule = true;
                break;
            }
        }
        if (! $onSchedule || $schedule->isEmpty()) {
            throw ValidationException::withMessages([
                'occurrence_date' => 'That date is not on this rental billing schedule.',
            ]);
        }

        $amount = (float) $rental->recurring_cost;
        if ($amount <= 0.0) {
            throw ValidationException::withMessages([
                'occurrence_date' => 'Recurring rent amount is zero; cannot record payment.',
            ]);
        }

        $periodsTotal = $schedule->count();

        return DB::transaction(function () use (
            $rental,
            $user,
            $business,
            $occurrence,
            $deductAccountId,
            $amount,
            $periodNumber,
            $periodsTotal,
        ): LedgerTransaction {
            $ledgerExists = LedgerTransaction::query()
                ->where('transactionable_type', Rental::class)
                ->where('transactionable_id', $rental->getKey())
                ->whereDate('occurrence_date', $occurrence->toDateString())
                ->lockForUpdate()
                ->exists();

            if ($ledgerExists) {
                throw ValidationException::withMessages([
                    'occurrence_date' => 'This billing date is already recorded.',
                ]);
            }

            $account = Account::query()
                ->whereKey($deductAccountId)
                ->where('user_id', $user->id)
                ->where('business_id', $business->id)
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                throw ValidationException::withMessages([
                    'deduct_account_id' => 'Choose an account belonging to your business.',
                ]);
            }

            $this->accountService->applyBalanceDeduction($account, $amount);

            $currency = (string) (get_settings('business.currency', '', $rental->business) ?: '');

            return $rental->ledgerTransactions()->create([
                'business_id' => $rental->business_id,
                'user_id' => $rental->user_id,
                'deduct_account_id' => $account->id,
                'occurrence_date' => $occurrence->toDateString(),
                'period_number' => $periodNumber,
                'amount' => $amount,
                'currency' => $currency !== '' ? $currency : null,
                'cadence_snapshot' => $rental->recurring_type,
                'periods_total_snapshot' => $periodsTotal,
                'meta' => [
                    'settlement_source' => 'manual_rental',
                    'property_type_snapshot' => $rental->property_type,
                ],
            ]);
        });
    }
}
