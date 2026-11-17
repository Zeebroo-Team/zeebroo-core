<?php

namespace Modules\Pos\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Models\CreditPayment;
use Modules\Pos\Models\Customer;
use Modules\Pos\Models\Sale;

/**
 * Customer credit ("pay later") balances: the outstanding due across a customer's
 * credit sales, the optional credit limit on top of it, and receiving payments
 * against those dues (which frees the limit up again).
 */
class CustomerCreditService
{
    private const MONEY_TOLERANCE = 0.005;

    public function __construct(
        private readonly SalePaymentSettlementService $payments,
        private readonly PosSettingsService $posSettings,
    ) {
    }

    public function outstanding(Customer $customer): float
    {
        return round((float) Sale::query()
            ->where('pos_customer_id', $customer->id)
            ->where('payment_method', Sale::PAYMENT_CREDIT)
            ->where('status', Sale::STATUS_COMPLETED)
            ->sum(DB::raw('total - amount_paid')), 2);
    }

    /**
     * @return array{credit_limit: ?float, credit_outstanding: float, credit_available: ?float}
     */
    public function summary(Customer $customer, ?float $outstanding = null): array
    {
        $outstanding = round(max(0, $outstanding ?? $this->outstanding($customer)), 2);
        $limit       = $customer->credit_limit !== null ? round((float) $customer->credit_limit, 2) : null;

        return [
            'credit_limit'       => $limit,
            'credit_outstanding' => $outstanding,
            'credit_available'   => $limit !== null ? round(max(0, $limit - $outstanding), 2) : null,
        ];
    }

    /**
     * Rejects a new credit sale of $amount when it would push the customer's dues past their limit.
     * Locks the customer row so two tills can't both spend the same available credit.
     */
    public function assertWithinLimit(Business $business, int $customerId, float $amount): void
    {
        $customer = Customer::query()
            ->where('business_id', $business->id)
            ->whereKey($customerId)
            ->lockForUpdate()
            ->first();

        if ($customer === null || $customer->credit_limit === null) {
            return;
        }

        $summary = $this->summary($customer);
        if ($amount > $summary['credit_available'] + self::MONEY_TOLERANCE) {
            throw ValidationException::withMessages([
                'pos_customer_id' => sprintf(
                    'Credit limit exceeded for %s. Limit %s, outstanding %s, available %s, this sale %s.',
                    $customer->name,
                    number_format($summary['credit_limit'], 2),
                    number_format($summary['credit_outstanding'], 2),
                    number_format($summary['credit_available'], 2),
                    number_format($amount, 2),
                ),
            ]);
        }
    }

    /**
     * Payments received from the customer, newest first, one entry per receipt.
     *
     * @return list<array{reference: string, paid_at: ?string, amount: float, payment_method: string, received_by: ?string, sales: list<array{sale_id: int, sale_number: ?string, amount: float}>}>
     */
    public function paymentHistory(Customer $customer, int $limit = 50): array
    {
        return CreditPayment::query()
            ->where('pos_customer_id', $customer->id)
            ->with(['sale:id,sale_number', 'user:id,name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit($limit * 5)
            ->get()
            ->groupBy('reference')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'reference'      => $first->reference,
                    'paid_at'        => $first->paid_at?->toIso8601String(),
                    'amount'         => round((float) $rows->sum('amount'), 2),
                    'payment_method' => $first->payment_method,
                    'received_by'    => $first->user?->name,
                    'sales'          => $rows->map(fn (CreditPayment $p) => [
                        'sale_id'     => (int) $p->pos_sale_id,
                        'sale_number' => $p->sale?->sale_number,
                        'amount'      => round((float) $p->amount, 2),
                    ])->values()->all(),
                ];
            })
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Applies a payment to the customer's unpaid credit sales, oldest first —
     * or only to $saleId when the cashier is paying off one particular bill.
     *
     * @return array{reference: string, amount: float, applied: list<array{sale_id: int, sale_number: string, amount: float, remaining_due: float}>}
     */
    public function receivePayment(
        Business $business,
        Customer $customer,
        User $user,
        float $amount,
        string $paymentMethod = Sale::PAYMENT_CASH,
        ?int $depositAccountId = null,
        ?int $saleId = null,
    ): array {
        $amount = round($amount, 2);
        if ($amount <= self::MONEY_TOLERANCE) {
            throw ValidationException::withMessages(['amount' => 'Enter a payment amount greater than zero.']);
        }

        $depositAccountId ??= $this->posSettings->forBusiness($business)['default_deposit_account_id'] ?? null;
        $depositAccountId = $depositAccountId !== null ? (int) $depositAccountId : null;

        return DB::transaction(function () use ($business, $customer, $user, $amount, $paymentMethod, $depositAccountId, $saleId) {
            $sales = Sale::query()
                ->where('business_id', $business->id)
                ->where('pos_customer_id', $customer->id)
                ->where('payment_method', Sale::PAYMENT_CREDIT)
                ->where('status', Sale::STATUS_COMPLETED)
                // Literal, not a binding: MySQL compared a bound 0.005 against the decimal and matched nothing
                ->whereRaw('total - amount_paid > 0.005')
                ->when($saleId !== null, fn ($q) => $q->whereKey($saleId))
                ->orderBy('sold_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $outstanding = round($sales->sum(fn (Sale $s) => (float) $s->total - (float) $s->amount_paid), 2);
            if ($outstanding <= self::MONEY_TOLERANCE) {
                throw ValidationException::withMessages(['amount' => $saleId !== null
                    ? 'This sale has nothing left to pay.'
                    : 'This customer has no outstanding credit to pay.']);
            }
            if ($amount > $outstanding + self::MONEY_TOLERANCE) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment ('.number_format($amount, 2).') is more than the outstanding due ('.number_format($outstanding, 2).').',
                ]);
            }

            $left      = $amount;
            $applied   = [];
            $reference = 'CP-'.now()->format('ymdHis').'-'.strtoupper(Str::random(4));
            $paidAt    = now();
            foreach ($sales as $sale) {
                if ($left <= self::MONEY_TOLERANCE) {
                    break;
                }
                $due   = round((float) $sale->total - (float) $sale->amount_paid, 2);
                $share = round(min($due, $left), 2);

                $sale->update(['amount_paid' => round((float) $sale->amount_paid + $share, 2)]);
                CreditPayment::create([
                    'business_id'     => $business->id,
                    'pos_customer_id' => $customer->id,
                    'pos_sale_id'     => $sale->id,
                    'user_id'         => $user->id,
                    'reference'       => $reference,
                    'amount'          => $share,
                    'payment_method'  => $paymentMethod,
                    'paid_at'         => $paidAt,
                ]);
                $this->payments->settle($sale, $business, $user, $depositAccountId, $share, $paymentMethod, $paidAt, 'pos_credit_payment');

                $left      = round($left - $share, 2);
                $applied[] = [
                    'sale_id'       => $sale->id,
                    'sale_number'   => $sale->sale_number,
                    'amount'        => $share,
                    'remaining_due' => round($due - $share, 2),
                ];
            }

            return ['reference' => $reference, 'amount' => $amount, 'applied' => $applied];
        });
    }
}
