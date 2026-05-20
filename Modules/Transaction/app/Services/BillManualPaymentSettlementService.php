<?php

namespace Modules\Transaction\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Account;
use Modules\Account\Models\Bill;
use Modules\Account\Services\AccountService;
use Modules\Account\Services\BillService;
use Modules\Business\Models\Business;
use Modules\Transaction\Models\LedgerTransaction;

class BillManualPaymentSettlementService
{
    private const MONEY_TOLERANCE = 0.005;

    public function __construct(
        private readonly BillService $billSchedule,
        private readonly AccountService $accountService,
    ) {}

    /**
     * Post one or more ledger debits against a scheduled billing occurrence (full, partial, or split across accounts).
     *
     * @param  list<array{deduct_account_id: int, amount: float|string}>  $lines
     * @return Collection<int, LedgerTransaction>
     */
    public function settlePaymentLines(
        Bill $bill,
        Business $business,
        User $user,
        string $occurrenceDateYmd,
        array $lines,
        string $paymentUiOption,
        ?float $periodChargeDeclarationFromRequest = null,
    ): Collection {
        if ($bill->user_id !== $user->id || (int) $bill->business_id !== (int) $business->id) {
            abort(403);
        }

        $bill->loadMissing(['business']);

        try {
            $occurrence = Carbon::parse($occurrenceDateYmd)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'occurrence_date' => 'Invalid billing date.',
            ]);
        }

        $schedule = $this->billSchedule->billScheduledBillingDates($bill);
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
                'occurrence_date' => 'That date is not on this bill schedule.',
            ]);
        }

        $normalized = [];
        foreach ($lines as $line) {
            $id = (int) ($line['deduct_account_id'] ?? 0);
            $amt = round((float) ($line['amount'] ?? 0), 2);
            if ($id <= 0 || $amt <= 0) {
                continue;
            }
            $normalized[] = ['deduct_account_id' => $id, 'amount' => $amt];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'deduct_account_id' => 'Add at least one valid payment line with an account and a positive amount.',
            ]);
        }

        $periodsTotal = $schedule->count();

        return DB::transaction(function () use (
            $bill,
            $user,
            $business,
            $occurrence,
            $normalized,
            $periodNumber,
            $periodsTotal,
            $paymentUiOption,
            $periodChargeDeclarationFromRequest,
        ): Collection {
            LedgerTransaction::query()
                ->where('transactionable_type', Bill::class)
                ->where('transactionable_id', $bill->getKey())
                ->whereDate('occurrence_date', $occurrence->toDateString())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $bill->unsetRelation('ledgerTransactions');
            $bill->load('ledgerTransactions');

            $paidSoFar = $this->billSchedule->billAmountPaidTowardScheduledDate($bill, $occurrence);
            $lockedDeclared = $this->billSchedule->billPeriodChargeDeclaredTotal($bill, $occurrence);

            if ($bill->amount_varies_by_usage) {
                $periodExpected = $lockedDeclared ?? round((float) ($periodChargeDeclarationFromRequest ?? 0), 2);
                if ($periodExpected <= self::MONEY_TOLERANCE) {
                    throw ValidationException::withMessages([
                        'period_charge_total' => 'Enter this period’s invoice or metered charge before recording payment.',
                    ]);
                }
            } else {
                $periodExpected = round((float) $bill->recurring_cost, 2);
                if ($periodExpected <= 0.0) {
                    throw ValidationException::withMessages([
                        'occurrence_date' => 'Bill amount is zero; cannot record payment.',
                    ]);
                }
            }

            $outstanding = round(max(0.0, $periodExpected - $paidSoFar), 2);

            if ($outstanding <= self::MONEY_TOLERANCE) {
                throw ValidationException::withMessages([
                    'occurrence_date' => 'This billing date is already fully paid.',
                ]);
            }

            $batchTotal = round(array_sum(array_column($normalized, 'amount')), 2);

            if ($batchTotal > $outstanding + self::MONEY_TOLERANCE) {
                throw ValidationException::withMessages([
                    'partial_amount' => 'Total payment cannot exceed the outstanding amount for this billing date ('.number_format($outstanding, 2, '.', ',').').',
                    'split_rows' => 'Split amounts cannot exceed the outstanding amount for this billing date ('.number_format($outstanding, 2, '.', ',').').',
                ]);
            }

            $currency = (string) (get_settings('business.currency', '', $bill->business) ?: '');
            $portionCount = count($normalized);
            $created = new Collection;

            foreach ($normalized as $index => $line) {
                $account = Account::query()
                    ->whereKey($line['deduct_account_id'])
                    ->where('user_id', $user->id)
                    ->where('business_id', $business->id)
                    ->lockForUpdate()
                    ->first();

                if ($account === null) {
                    throw ValidationException::withMessages([
                        'deduct_account_id' => 'Each payment must use an account belonging to your business.',
                    ]);
                }

                $this->accountService->applyBalanceDeduction($account, $line['amount']);

                $metaOption = $paymentUiOption;
                if ($portionCount > 1) {
                    $metaOption = 'split';
                }

                $created->push($bill->ledgerTransactions()->create([
                    'business_id' => $bill->business_id,
                    'user_id' => $bill->user_id,
                    'deduct_account_id' => $account->id,
                    'occurrence_date' => $occurrence->toDateString(),
                    'period_number' => $periodNumber,
                    'amount' => $line['amount'],
                    'currency' => $currency !== '' ? $currency : null,
                    'cadence_snapshot' => $bill->isOneTime() ? Bill::PAYMENT_MODE_ONE_TIME : $bill->recurring_type,
                    'periods_total_snapshot' => $periodsTotal,
                    'meta' => [
                        'settlement_source' => 'manual_bill',
                        'bill_name_snapshot' => $bill->name,
                        'bill_category_snapshot' => $bill->categoryDisplayLabel(),
                        'payment_option' => $metaOption,
                        'scheduled_amount_snapshot' => $periodExpected,
                        'period_charge_total' => $periodExpected,
                        'amount_varies_by_usage_snapshot' => $bill->amount_varies_by_usage,
                        'portion_index' => $index + 1,
                        'portion_count' => $portionCount,
                    ],
                ]));
            }

            return $created;
        });
    }
}
