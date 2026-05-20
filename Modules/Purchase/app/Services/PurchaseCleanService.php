<?php

namespace Modules\Purchase\Services;

use Illuminate\Support\Facades\DB;
use Modules\Account\Models\Account;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductStockLayerService;
use Modules\Purchase\Models\ChequePayment;
use Modules\Purchase\Models\GoodsReceiveNote;
use Modules\Purchase\Models\GoodsReceiveNoteItem;
use Modules\Purchase\Models\Purchase;
use Modules\Transaction\Models\LedgerTransaction;

class PurchaseCleanService
{
    public function __construct(
        private readonly ProductStockLayerService $stockLayers,
    ) {
    }

    /**
     * @return array{
     *     businesses: int,
     *     purchases: int,
     *     purchase_items: int,
     *     goods_receive_notes: int,
     *     goods_receive_note_items: int,
     *     cheque_payments: int,
     *     ledger_transactions: int,
     *     stock_lines_reversed: int,
     *     ledger_amount_restored: float,
     *     purchases_status_reset: int,
     * }
     */
    public function clean(?int $businessId = null, bool $grnsOnly = false, bool $dryRun = false): array
    {
        $stats = [
            'businesses' => $businessId ? 1 : Business::query()->count(),
            'purchases' => 0,
            'purchase_items' => 0,
            'goods_receive_notes' => 0,
            'goods_receive_note_items' => 0,
            'cheque_payments' => 0,
            'ledger_transactions' => 0,
            'stock_lines_reversed' => 0,
            'ledger_amount_restored' => 0.0,
            'purchases_status_reset' => 0,
        ];

        $grnQuery = GoodsReceiveNote::query()->when($businessId, fn ($q) => $q->where('business_id', $businessId));
        $purchaseQuery = Purchase::query()->when($businessId, fn ($q) => $q->where('business_id', $businessId));

        $stats['goods_receive_notes'] = (clone $grnQuery)->count();
        $stats['goods_receive_note_items'] = GoodsReceiveNoteItem::query()
            ->whereIn('goods_receive_note_id', (clone $grnQuery)->select('id'))
            ->count();
        $stats['purchases'] = $grnsOnly ? 0 : (clone $purchaseQuery)->count();
        $stats['purchase_items'] = $grnsOnly ? 0 : (int) \Modules\Purchase\Models\PurchaseItem::query()
            ->whereIn('purchase_id', (clone $purchaseQuery)->select('id'))
            ->count();

        $grnIds = (clone $grnQuery)->pluck('id');
        $ledgerQuery = LedgerTransaction::query()
            ->where('transactionable_type', GoodsReceiveNote::class)
            ->whereIn('transactionable_id', $grnIds);

        $stats['ledger_transactions'] = (clone $ledgerQuery)->count();
        $stats['cheque_payments'] = ChequePayment::query()
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->when($grnIds->isNotEmpty(), fn ($q) => $q->where(function ($inner) use ($grnIds, $ledgerQuery): void {
                $inner->whereIn('goods_receive_note_id', $grnIds)
                    ->orWhereIn('ledger_transaction_id', (clone $ledgerQuery)->select('id'));
            }))
            ->count();

        if ($dryRun) {
            $stats['stock_lines_reversed'] = GoodsReceiveNoteItem::query()
                ->whereIn('goods_receive_note_id', $grnIds)
                ->whereHas('goodsReceiveNote', fn ($q) => $q->where('stock_applied', true))
                ->count();
            $stats['ledger_amount_restored'] = round((float) (clone $ledgerQuery)->sum('amount'), 2);
            $stats['purchases_status_reset'] = Purchase::query()
                ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
                ->whereIn('status', [Purchase::STATUS_RECEIVED, Purchase::STATUS_PARTIALLY_RECEIVED])
                ->when($grnsOnly, fn ($q) => $q->whereIn('id', GoodsReceiveNote::query()
                    ->when($businessId, fn ($inner) => $inner->where('business_id', $businessId))
                    ->select('purchase_id')))
                ->count();

            return $stats;
        }

        DB::transaction(function () use ($businessId, $grnsOnly, $grnQuery, $purchaseQuery, &$stats): void {
            $grns = (clone $grnQuery)
                ->with(['items.product'])
                ->lockForUpdate()
                ->get();

            foreach ($grns as $grn) {
                if (! $grn->stock_applied) {
                    continue;
                }

                $itemCount = $grn->items->count();
                $this->stockLayers->reverseForGrn($grn);
                $stats['stock_lines_reversed'] += $itemCount;
            }

            $grnIds = $grns->pluck('id');

            $ledgers = LedgerTransaction::query()
                ->where('transactionable_type', GoodsReceiveNote::class)
                ->whereIn('transactionable_id', $grnIds)
                ->lockForUpdate()
                ->get();

            foreach ($ledgers as $ledger) {
                if ($ledger->deduct_account_id === null) {
                    continue;
                }

                $account = Account::query()
                    ->whereKey($ledger->deduct_account_id)
                    ->lockForUpdate()
                    ->first();

                if ($account === null) {
                    continue;
                }

                $amount = round((float) $ledger->amount, 2);
                $account->update([
                    'current_balance' => round((float) $account->current_balance + $amount, 2),
                ]);
                $stats['ledger_amount_restored'] += $amount;
            }

            $ledgerIds = $ledgers->pluck('id');

            ChequePayment::query()
                ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
                ->where(function ($q) use ($grnIds, $ledgerIds): void {
                    $q->whereIn('goods_receive_note_id', $grnIds);
                    if ($ledgerIds->isNotEmpty()) {
                        $q->orWhereIn('ledger_transaction_id', $ledgerIds);
                    }
                })
                ->delete();

            LedgerTransaction::query()
                ->whereIn('id', $ledgerIds)
                ->delete();

            GoodsReceiveNoteItem::query()
                ->whereIn('goods_receive_note_id', $grnIds)
                ->delete();

            $affectedPurchaseIds = $grns->pluck('purchase_id')->filter()->unique()->values();

            GoodsReceiveNote::query()
                ->whereIn('id', $grnIds)
                ->delete();

            $stats['purchases_status_reset'] = Purchase::query()
                ->whereIn('id', $affectedPurchaseIds)
                ->whereIn('status', [Purchase::STATUS_RECEIVED, Purchase::STATUS_PARTIALLY_RECEIVED])
                ->update([
                    'status' => Purchase::STATUS_ORDERED,
                    'stock_applied' => false,
                ]);

            if (! $grnsOnly) {
                Purchase::query()
                    ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
                    ->delete();
            }
        });

        $stats['ledger_amount_restored'] = round($stats['ledger_amount_restored'], 2);

        return $stats;
    }
}
