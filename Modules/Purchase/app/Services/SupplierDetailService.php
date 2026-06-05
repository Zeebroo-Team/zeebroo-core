<?php

namespace Modules\Purchase\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Modules\Pos\Models\SaleReturnItem;
use Modules\Purchase\Models\ChequePayment;
use Modules\Purchase\Models\GoodsReceiveNote;
use Modules\Purchase\Models\GoodsReceiveNoteItem;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Models\Supplier;
use Modules\Transaction\Models\LedgerTransaction;

class SupplierDetailService
{
    public function __construct(
        private readonly GrnPaymentSettlementService $paymentSettlement,
    ) {
    }

    /**
     * @return array{
     *     summary: array<string, int|float>,
     *     purchases: EloquentCollection<int, Purchase>,
     *     grns: EloquentCollection<int, GoodsReceiveNote>,
     *     cashPayments: EloquentCollection<int, LedgerTransaction>,
     *     cheques: EloquentCollection<int, ChequePayment>,
     *     creditGrns: EloquentCollection<int, GoodsReceiveNote>,
     *     supplierReturnedItems: EloquentCollection<int, SaleReturnItem>,
     * }
     */
    public function forShowPage(Supplier $supplier): array
    {
        $purchases = $this->purchasesForSupplier($supplier);
        $grns = $this->grnsForSupplier($supplier);
        $cashPayments = $this->cashLedgerForSupplier($supplier);
        $cheques = $this->chequesForSupplier($supplier);
        $creditGrns = $this->creditGrnsForSupplier($grns);
        $supplierReturnedItems = $this->supplierReturnedItems($supplier);

        return [
            'summary' => $this->buildSummary($purchases, $grns, $cashPayments, $cheques, $creditGrns),
            'purchases' => $purchases,
            'grns' => $grns,
            'cashPayments' => $cashPayments,
            'cheques' => $cheques,
            'creditGrns' => $creditGrns,
            'supplierReturnedItems' => $supplierReturnedItems,
        ];
    }

    /**
     * Returns SaleReturnItems for products sourced from this supplier, filtered to quality-related reasons.
     *
     * @return EloquentCollection<int, SaleReturnItem>
     */
    public function supplierReturnedItems(Supplier $supplier): EloquentCollection
    {
        $productIds = GoodsReceiveNoteItem::query()
            ->whereHas('goodsReceiveNote.purchase', fn ($q) => $q->where('supplier_id', $supplier->id))
            ->pluck('product_id')
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return new EloquentCollection;
        }

        $qualityReasons = ['expired', 'damaged', 'low_quality', 'wrong_item'];

        return SaleReturnItem::query()
            ->whereIn('product_id', $productIds)
            ->whereHas('saleReturn', fn ($q) => $q->whereIn('refund_reason', $qualityReasons))
            ->with(['saleReturn.sale:id,sale_number', 'product:id,name,sku'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return EloquentCollection<int, Purchase>
     */
    public function purchasesForSupplier(Supplier $supplier): EloquentCollection
    {
        return $supplier->purchases()
            ->withCount('items')
            ->withCount('goodsReceiveNotes')
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return EloquentCollection<int, GoodsReceiveNote>
     */
    public function grnsForSupplier(Supplier $supplier): EloquentCollection
    {
        return GoodsReceiveNote::query()
            ->whereHas('purchase', fn ($query) => $query->where('supplier_id', $supplier->id))
            ->with(['purchase'])
            ->withCount('items')
            ->withSum('ledgerTransactions as ledger_paid_total', 'amount')
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return EloquentCollection<int, LedgerTransaction>
     */
    public function cashLedgerForSupplier(Supplier $supplier): EloquentCollection
    {
        return LedgerTransaction::query()
            ->where('business_id', $supplier->business_id)
            ->where('transactionable_type', GoodsReceiveNote::class)
            ->whereHasMorph('transactionable', [GoodsReceiveNote::class], function ($grnQuery) use ($supplier) {
                $grnQuery->whereHas('purchase', fn ($purchaseQuery) => $purchaseQuery->where('supplier_id', $supplier->id));
            })
            ->where(function ($query) {
                $query->where('meta->payment_method', Purchase::PAYMENT_CASH)
                    ->orWhereNull('meta->payment_method');
            })
            ->with(['deductAccount.bankType'])
            ->orderByDesc('occurrence_date')
            ->orderByDesc('id')
            ->get()
            ->loadMorph('transactionable', [
                GoodsReceiveNote::class => ['purchase'],
            ]);
    }

    /**
     * @return EloquentCollection<int, ChequePayment>
     */
    public function chequesForSupplier(Supplier $supplier): EloquentCollection
    {
        return ChequePayment::query()
            ->where('business_id', $supplier->business_id)
            ->whereHas('goodsReceiveNote.purchase', fn ($query) => $query->where('supplier_id', $supplier->id))
            ->with(['goodsReceiveNote.purchase', 'deductAccount.bankType', 'ledgerTransaction'])
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, GoodsReceiveNote>  $grns
     * @return EloquentCollection<int, GoodsReceiveNote>
     */
    public function creditGrnsForSupplier(EloquentCollection $grns): EloquentCollection
    {
        return $grns
            ->filter(function (GoodsReceiveNote $grn) {
                if ($grn->payment_method === Purchase::PAYMENT_CREDIT) {
                    return true;
                }

                return $this->paymentSettlement->amountOutstanding($grn) > 0.005;
            })
            ->values();
    }

    /**
     * @param  EloquentCollection<int, Purchase>  $purchases
     * @param  EloquentCollection<int, GoodsReceiveNote>  $grns
     * @param  EloquentCollection<int, LedgerTransaction>  $cashPayments
     * @param  EloquentCollection<int, ChequePayment>  $cheques
     * @param  EloquentCollection<int, GoodsReceiveNote>  $creditGrns
     * @return array<string, int|float>
     */
    private function buildSummary(
        EloquentCollection $purchases,
        EloquentCollection $grns,
        EloquentCollection $cashPayments,
        EloquentCollection $cheques,
        EloquentCollection $creditGrns,
    ): array {
        $outstanding = $grns->sum(fn (GoodsReceiveNote $grn) => $this->paymentSettlement->amountOutstanding($grn));
        $chequeOpen = $cheques
            ->filter(fn (ChequePayment $cheque) => ! $cheque->isCleared())
            ->sum(fn (ChequePayment $cheque) => (float) $cheque->amount);
        $creditOutstanding = $creditGrns->sum(fn (GoodsReceiveNote $grn) => $this->paymentSettlement->amountOutstanding($grn));

        return [
            'purchases_count' => $purchases->count(),
            'grns_count' => $grns->count(),
            'purchases_total' => round((float) $purchases->sum('total'), 2),
            'grns_total' => round((float) $grns->sum('total'), 2),
            'cash_paid_total' => round((float) $cashPayments->sum('amount'), 2),
            'cheques_count' => $cheques->count(),
            'cheques_open_amount' => round((float) $chequeOpen, 2),
            'credit_grns_count' => $creditGrns->count(),
            'credit_outstanding' => round((float) $creditOutstanding, 2),
            'outstanding_total' => round((float) $outstanding, 2),
        ];
    }
}
