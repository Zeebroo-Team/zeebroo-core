<?php

declare(strict_types=1);

namespace Modules\Pos\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Account;
use Modules\Account\Services\AccountService;
use Modules\Business\Models\Business;
use Modules\Pos\Models\CustomerSubscription;
use Modules\Pos\Models\Sale;
use Modules\Pos\Models\SaleCheque;
use Modules\Pos\Models\SaleRevert;
use Modules\Product\Models\Product;

/**
 * Reverts a POS bill that was entered by mistake: the sale is voided and every
 * side effect of the checkout is undone — stock, gift card, coupon, rentals,
 * subscriptions and the money posted to the deposit account(s). The reason and
 * a snapshot of what was undone are kept in pos_sale_reverts.
 */
class SaleRevertService
{
    private const MONEY_TOLERANCE = 0.005;

    private const QTY_TOLERANCE = 0.0005;

    public function __construct(
        private readonly SaleStockConsumptionService $stockConsumption,
        private readonly ProductRentalService $rentals,
        private readonly CustomerSubscriptionService $subscriptions,
        private readonly GiftCardService $giftCards,
        private readonly CouponService $coupons,
        private readonly AccountService $accounts,
    ) {
    }

    public function revert(Sale $sale, Business $business, User $user, string $reason, ?string $notes): SaleRevert
    {
        if ((int) $sale->business_id !== (int) $business->id) {
            abort(403);
        }

        if (! array_key_exists($reason, SaleRevert::REASONS)) {
            throw ValidationException::withMessages(['reason' => 'Choose why this bill is being reverted.']);
        }

        if ($reason === 'other' && blank($notes)) {
            throw ValidationException::withMessages(['notes' => 'Describe why this bill is being reverted.']);
        }

        $revert = DB::transaction(function () use ($sale, $business, $user, $reason, $notes): SaleRevert {
            // Lock the sale so two cashiers can't revert the same bill at once
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->isVoid()) {
                throw ValidationException::withMessages(['sale' => 'This bill has already been reverted or voided.']);
            }

            $sale->load(['items.product', 'items.serviceItem.products', 'returns.items', 'customer', 'ledgerTransactions']);

            // Quantities already handed back through Returns were restocked then — only restock the rest
            $returnedQtys  = [];
            $returnedTotal = 0.0;
            foreach ($sale->returns as $ret) {
                $returnedTotal += (float) $ret->total;
                foreach ($ret->items as $ri) {
                    $key = (int) $ri->pos_sale_item_id;
                    $returnedQtys[$key] = round(($returnedQtys[$key] ?? 0.0) + (float) $ri->quantity, 3);
                }
            }
            $returnedTotal = round($returnedTotal, 2);

            $itemsSnapshot = [];
            $restoredTotal = 0.0;
            $saleCost      = 0.0;

            foreach ($sale->items as $item) {
                $soldQty     = round((float) $item->quantity, 3);
                $returnedQty = min($soldQty, $returnedQtys[(int) $item->id] ?? 0.0);
                $remaining   = round($soldQty - $returnedQty, 3);
                $restocked   = 0.0;
                $saleCost   += (float) ($item->unit_cost ?? 0) * $soldQty;

                if ($item->product instanceof Product) {
                    if ($remaining > self::QTY_TOLERANCE) {
                        $this->stockConsumption->restoreSaleItem(
                            $item->product_stock_layer_id !== null ? (int) $item->product_stock_layer_id : null,
                            $remaining,
                            $item->product,
                        );
                        $restocked = $remaining;
                    }
                    if ($item->product->is_rental) {
                        $this->rentals->cancelForSaleItem($item->id);
                    }
                } elseif ($item->serviceItem !== null && $remaining > self::QTY_TOLERANCE) {
                    // Service line — put back the products the service consumed
                    foreach ($item->serviceItem->products as $boundProduct) {
                        $restoreQty = round((float) $boundProduct->pivot->qty * $remaining, 3);
                        if ($restoreQty <= self::QTY_TOLERANCE) {
                            continue;
                        }
                        $this->stockConsumption->restoreSaleItem(null, $restoreQty, $boundProduct);
                        $restocked += $restoreQty;
                    }
                }

                $restoredTotal += $restocked;

                $itemsSnapshot[] = [
                    'sale_item_id'       => (int) $item->id,
                    'type'               => $item->service_item_id !== null ? 'service' : 'product',
                    'product_id'         => $item->product_id !== null ? (int) $item->product_id : null,
                    'product_name'       => $item->product_name,
                    'sku'                => $item->sku,
                    'quantity'           => $soldQty,
                    'returned_quantity'  => round($returnedQty, 3),
                    'restocked_quantity' => round($restocked, 3),
                    'unit_sell_price'    => round((float) $item->unit_sell_price, 2),
                    'unit_cost'          => $item->unit_cost !== null ? round((float) $item->unit_cost, 2) : null,
                    'line_total'         => round((float) $item->line_total, 2),
                ];
            }

            $this->giftCards->refundForSale($sale, $user);
            $this->coupons->reverseForSale($sale);

            CustomerSubscription::query()
                ->where('pos_sale_id', $sale->id)
                ->where('status', '!=', CustomerSubscription::STATUS_CANCELLED)
                ->get()
                ->each(fn (CustomerSubscription $sub) => $this->subscriptions->cancel($sub));

            // Take the money this bill put into the deposit account(s) back out and drop
            // the income entries, so finance shows the bill as if it never happened.
            $accountReversed = 0.0;
            foreach ($sale->ledgerTransactions as $entry) {
                $amount = round((float) $entry->amount, 2);
                if ($entry->deduct_account_id !== null && $amount > self::MONEY_TOLERANCE) {
                    $account = Account::query()->whereKey($entry->deduct_account_id)->lockForUpdate()->first();
                    if ($account !== null) {
                        $this->accounts->applyBalanceDeduction($account, $amount);
                        $accountReversed += $amount;
                    }
                }
                $entry->delete();
            }

            // What the customer is owed back: everything paid through the payment method
            // (the gift card part goes back on the card) less what Returns already refunded.
            $giftAmount   = round((float) $sale->gift_card_amount, 2);
            $paidByMethod = round(max(0.0, (float) $sale->amount_paid - $giftAmount), 2);
            $refundDue    = round(max(0.0, $paidByMethod - $returnedTotal), 2);

            // A cheque that was never claimed is handed back, so no money is owed
            $unclaimedCheques = $sale->cheque()
                ->whereIn('status', [SaleCheque::STATUS_PENDING, SaleCheque::STATUS_BOUNCED])
                ->update(['status' => SaleCheque::STATUS_CANCELLED]);
            if ($unclaimedCheques > 0) {
                $refundDue = 0.0;
            }

            $sale->update(['status' => Sale::STATUS_VOID]);

            $revert = SaleRevert::query()->create([
                'business_id'        => $business->id,
                'pos_sale_id'        => $sale->id,
                'user_id'            => $user->id,
                'revert_number'      => $this->nextRevertNumber($business),
                'reason'             => $reason,
                'notes'              => filled($notes) ? trim((string) $notes) : null,
                'sale_number'        => $sale->sale_number,
                'customer_name'      => $sale->customer?->name,
                'payment_method'     => $sale->payment_method,
                'sale_sold_at'       => $sale->sold_at,
                'sale_total'         => round((float) $sale->total, 2),
                'sale_cost'          => round($saleCost, 2),
                'amount_paid'        => round((float) $sale->amount_paid, 2),
                'gift_card_refunded' => $giftAmount,
                'returned_total'     => $returnedTotal,
                'amount_refunded'    => $refundDue,
                'account_reversed'   => round($accountReversed, 2),
                'stock_restored_qty' => round($restoredTotal, 3),
                'items'              => $itemsSnapshot,
                'reverted_at'        => now(),
            ]);

            return $revert;
        });

        try {
            app(\Modules\AutomationEditor\Services\AutomationRunnerService::class)->dispatch('sale.voided', $business, [
                'event'    => 'sale.voided',
                'sale'     => ['id' => $sale->id, 'reference' => $revert->sale_number, 'total' => (float) $revert->sale_total],
                'revert'   => ['number' => $revert->revert_number, 'reason' => $revert->reasonLabel(), 'notes' => $revert->notes, 'refund' => (float) $revert->amount_refunded],
                'customer' => $sale->customer ? ['id' => $sale->customer->id, 'name' => $sale->customer->name, 'email' => $sale->customer->email] : [],
            ]);
        } catch (\Throwable) {
            // Automation errors must never break the revert
        }

        return $revert;
    }

    /**
     * @param  array{q?: string, reason?: string, date_from?: string, date_to?: string}  $filters
     */
    public function indexForBusiness(Business $business, array $filters = []): LengthAwarePaginator
    {
        $query = SaleRevert::query()
            ->where('business_id', $business->id)
            ->with('user:id,name');

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('reverted_at')->orderByDesc('id')->paginate(25);
    }

    /**
     * @param  array{q?: string, reason?: string, date_from?: string, date_to?: string}  $filters
     * @return array{count: int, sale_total: float, amount_refunded: float, account_reversed: float, stock_restored_qty: float}
     */
    public function summaryForBusiness(Business $business, array $filters = []): array
    {
        $query = SaleRevert::query()->where('business_id', $business->id);
        $this->applyFilters($query, $filters);

        $row = $query->selectRaw(
            'COUNT(*) as cnt, COALESCE(SUM(sale_total), 0) as sale_total, COALESCE(SUM(amount_refunded), 0) as refunded,
             COALESCE(SUM(account_reversed), 0) as reversed, COALESCE(SUM(stock_restored_qty), 0) as restored'
        )->first();

        return [
            'count'              => (int) ($row->cnt ?? 0),
            'sale_total'         => round((float) ($row->sale_total ?? 0), 2),
            'amount_refunded'    => round((float) ($row->refunded ?? 0), 2),
            'account_reversed'   => round((float) ($row->reversed ?? 0), 2),
            'stock_restored_qty' => round((float) ($row->restored ?? 0), 3),
        ];
    }

    /** @param  array{q?: string, reason?: string, date_from?: string, date_to?: string}  $filters */
    private function applyFilters(\Illuminate\Database\Eloquent\Builder $query, array $filters): void
    {
        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($b) use ($like) {
                $b->where('revert_number', 'like', $like)
                  ->orWhere('sale_number', 'like', $like)
                  ->orWhere('customer_name', 'like', $like)
                  ->orWhere('notes', 'like', $like);
            });
        }

        $reason = (string) ($filters['reason'] ?? '');
        if ($reason !== '' && $reason !== 'all') {
            $query->where('reason', $reason);
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('reverted_at', '>=', $filters['date_from']);
        }
        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('reverted_at', '<=', $filters['date_to']);
        }
    }

    private function nextRevertNumber(Business $business): string
    {
        $maxSeq = 0;
        $numbers = SaleRevert::query()
            ->where('business_id', $business->id)
            ->pluck('revert_number');

        foreach ($numbers as $num) {
            if (preg_match('/^REV-(\d+)$/', (string) $num, $m)) {
                $maxSeq = max($maxSeq, (int) $m[1]);
            }
        }

        return 'REV-'.str_pad((string) ($maxSeq + 1), 4, '0', STR_PAD_LEFT);
    }
}
