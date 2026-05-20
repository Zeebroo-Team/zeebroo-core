<?php

namespace Modules\Pos\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Account;
use Modules\Account\Services\AccountService;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Sale;
use Modules\Transaction\Models\LedgerTransaction;

class SalePaymentSettlementService
{
    private const MONEY_TOLERANCE = 0.005;

    public function __construct(
        private readonly AccountService $accountService,
    ) {
    }

    public function settle(
        Sale $sale,
        Business $business,
        User $user,
        int $creditAccountId,
        ?float $amount = null,
        string $paymentMethod = Sale::PAYMENT_CASH,
    ): ?LedgerTransaction {
        if ((int) $sale->business_id !== (int) $business->id) {
            abort(403);
        }

        $saleTotal = round((float) $sale->total, 2);
        if ($saleTotal <= self::MONEY_TOLERANCE) {
            return null;
        }

        $payAmount = $amount !== null ? round((float) $amount, 2) : $saleTotal;
        if ($payAmount <= self::MONEY_TOLERANCE) {
            throw ValidationException::withMessages([
                'amount_paid' => 'Enter a payment amount greater than zero for cash sales.',
            ]);
        }

        return DB::transaction(function () use ($sale, $business, $user, $creditAccountId, $payAmount, $paymentMethod) {
            $account = Account::query()
                ->with('bankType')
                ->whereKey($creditAccountId)
                ->where('user_id', $user->id)
                ->where('business_id', $business->id)
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                throw ValidationException::withMessages([
                    'credit_account_id' => 'Choose an account belonging to your business.',
                ]);
            }

            $this->accountService->applyBalanceAddition($account, $payAmount);

            $currency = (string) (get_settings('business.currency', '', $business) ?: '');

            return $sale->ledgerTransactions()->create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'deduct_account_id' => $account->id,
                'occurrence_date' => $sale->sold_at->toDateString(),
                'period_number' => null,
                'amount' => $payAmount,
                'currency' => $currency !== '' ? $currency : null,
                'cadence_snapshot' => null,
                'periods_total_snapshot' => null,
                'meta' => [
                    'payment_method' => $paymentMethod,
                    'sale_number' => $sale->sale_number,
                    'settlement_source' => 'pos_sale',
                    'direction' => 'income',
                ],
            ]);
        });
    }
}
