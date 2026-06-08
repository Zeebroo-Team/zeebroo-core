<?php

namespace Modules\Purchase\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductStockLayerService;
use Modules\Purchase\Models\GoodsReceiveNote;
use Modules\Purchase\Models\GoodsReceiveNoteItem;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Models\PurchaseItem;

class GoodsReceiveNoteService
{
    public function __construct(
        private readonly GrnPaymentSettlementService $paymentSettlement,
        private readonly ProductStockLayerService $stockLayers,
    ) {
    }

    /**
     * @return EloquentCollection<int, GoodsReceiveNote>
     */
    public function listForBusiness(
        Business $business,
        ?string $search = null,
        ?string $paymentFilter = null,
        ?int $supplierId = null,
    ): EloquentCollection {
        $query = $business->goodsReceiveNotes()
            ->with(['purchase.supplier', 'chequePayments'])
            ->withCount('items')
            ->withSum('ledgerTransactions as ledger_paid_total', 'amount');

        if ($supplierId !== null && $supplierId > 0) {
            $query->whereHas('purchase', fn ($purchaseQuery) => $purchaseQuery->where('supplier_id', $supplierId));
        }

        $term = trim((string) $search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('grn_number', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('purchase', function ($purchaseQuery) use ($like) {
                        $purchaseQuery->where('po_number', 'like', $like)
                            ->orWhere('reference', 'like', $like)
                            ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', $like));
                    });
            });
        }

        $notes = $query
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->get();

        if ($paymentFilter !== null && $paymentFilter !== '' && $paymentFilter !== 'all') {
            $allowed = [
                GrnPaymentSettlementService::STATUS_PAID_FULL,
                GrnPaymentSettlementService::STATUS_PAID_PARTIAL,
                GrnPaymentSettlementService::STATUS_PENDING,
                GrnPaymentSettlementService::STATUS_NO_AMOUNT,
            ];
            if (in_array($paymentFilter, $allowed, true)) {
                $notes = $notes
                    ->filter(fn (GoodsReceiveNote $note) => $note->paymentStatus() === $paymentFilter)
                    ->values();
            }
        }

        return $notes;
    }

    public function businessHasGrns(Business $business): bool
    {
        return $business->goodsReceiveNotes()->exists();
    }

    /**
     * Purchase orders with their goods receive notes for the GRN index (newest PO first).
     *
     * @return Collection<int, array{purchase: Purchase, notes: EloquentCollection<int, GoodsReceiveNote>}>
     */
    public function listGroupedByPurchaseForIndex(
        Business $business,
        EloquentCollection $openPurchaseOrders,
        ?string $search = null,
        ?string $paymentFilter = null,
        ?int $supplierId = null,
    ): Collection {
        $notes = $this->listForBusiness($business, $search, $paymentFilter, $supplierId);
        $notesByPurchase = $notes->groupBy(fn (GoodsReceiveNote $note) => (int) $note->purchase_id);

        $paymentFilterActive = filled($paymentFilter) && $paymentFilter !== 'all';
        $openForIndex = $paymentFilterActive
            ? collect()
            : $this->filterOpenPurchaseOrdersForIndex($openPurchaseOrders, $search, $supplierId);

        $purchaseIds = $notesByPurchase->keys()
            ->merge($openForIndex->pluck('id'))
            ->unique()
            ->filter(fn ($id) => (int) $id > 0)
            ->values();

        if ($purchaseIds->isEmpty()) {
            return collect();
        }

        $purchases = Purchase::query()
            ->with('supplier')
            ->where('business_id', $business->id)
            ->whereIn('id', $purchaseIds)
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get()
            ->keyBy('id');

        return $purchaseIds
            ->map(function ($purchaseId) use ($purchases, $notesByPurchase) {
                $purchase = $purchases->get((int) $purchaseId);
                if (!$purchase instanceof Purchase) {
                    return null;
                }

                $purchaseNotes = $notesByPurchase->get((int) $purchaseId, collect())
                    ->sortByDesc(fn (GoodsReceiveNote $note) => $note->received_date->timestamp)
                    ->values();

                return [
                    'purchase' => $purchase,
                    'notes' => $purchaseNotes,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $group) => $group['purchase']->purchase_date->timestamp)
            ->values();
    }

    /**
     * @param  EloquentCollection<int, Purchase>  $openPurchaseOrders
     * @return Collection<int, Purchase>
     */
    private function filterOpenPurchaseOrdersForIndex(
        EloquentCollection $openPurchaseOrders,
        ?string $search,
        ?int $supplierId,
    ): Collection {
        $filtered = $openPurchaseOrders;

        if ($supplierId !== null && $supplierId > 0) {
            $filtered = $filtered->filter(fn (Purchase $purchase) => (int) $purchase->supplier_id === $supplierId);
        }

        $term = trim((string) $search);
        if ($term !== '') {
            $needle = mb_strtolower($term);
            $filtered = $filtered->filter(function (Purchase $purchase) use ($needle) {
                $haystacks = [
                    (string) ($purchase->po_number ?? ''),
                    (string) ($purchase->reference ?? ''),
                    (string) ($purchase->supplier?->name ?? ''),
                ];

                foreach ($haystacks as $haystack) {
                    if ($haystack !== '' && str_contains(mb_strtolower($haystack), $needle)) {
                        return true;
                    }
                }

                return false;
            });
        }

        return $filtered->values();
    }

    /**
     * @param  array{received_date: string, reference?: ?string, notes?: ?string, payment_method: string, payment_reference?: ?string, deduct_account_id?: int|string|null}  $data
     * @param  list<array{purchase_item_id: int, quantity_received: float|string}>  $items
     */
    public function createForPurchase(Purchase $purchase, User $user, array $data, array $items): GoodsReceiveNote
    {
        if (!$purchase->canReceiveGoods()) {
            throw ValidationException::withMessages([
                'purchase' => 'This purchase order cannot receive more goods.',
            ]);
        }

        $lines = $this->normalizeReceiveLines($purchase, $items);

        return DB::transaction(function () use ($purchase, $user, $data, $lines) {
            $purchase->load('business');

            $branchId = isset($data['branch_id']) && (int) $data['branch_id'] > 0
                ? (int) $data['branch_id']
                : ($purchase->branch_id ?? null);

            $grn = $purchase->goodsReceiveNotes()->create([
                'business_id' => $purchase->business_id,
                'branch_id' => $branchId,
                'grn_number' => $this->nextGrnNumber($purchase->business),
                'received_date' => $data['received_date'],
                'reference' => filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'payment_method' => $data['payment_method'],
                'payment_reference' => $this->normalizePaymentReference($data),
                'cheque_due_date' => $this->normalizeChequeDueDate($data),
                'subtotal' => 0,
                'total' => 0,
                'stock_applied' => false,
            ]);

            foreach ($lines as $index => $line) {
                $grn->items()->create([
                    'purchase_item_id' => $line['purchase_item_id'],
                    'product_id' => $line['product_id'],
                    'quantity_received' => $line['quantity_received'],
                    'unit_cost' => $line['unit_cost'],
                    'selling_unit_price' => $line['selling_unit_price'] ?? null,
                    'line_total' => $line['line_total'],
                    'sort_order' => $index,
                ]);
            }

            $this->recalculateTotals($grn);
            $this->stockLayers->applyFromGrn($grn);
            $this->syncPurchaseReceiptStatus($purchase);
            $this->maybeSettlePayment($grn, $purchase->business, $user, $data);

            return $grn->load([
                'purchase.supplier',
                'items.product',
                'items.purchaseItem',
                'ledgerTransactions.deductAccount',
                'chequePayments',
            ]);
        });
    }

    /**
     * @param  array{payment_method?: string, payment_reference?: ?string, deduct_account_id?: int|string|null}  $paymentData
     */
    public function receiveAllRemaining(Purchase $purchase, User $user, ?string $receivedDate = null, array $paymentData = []): GoodsReceiveNote
    {
        $purchase->load('items');

        $lines = [];
        foreach ($purchase->items as $item) {
            $remaining = $item->quantityRemaining();
            if ($remaining <= 0) {
                continue;
            }
            $lines[] = [
                'purchase_item_id' => $item->id,
                'quantity_received' => $remaining,
            ];
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'items' => 'Nothing left to receive on this purchase order.',
            ]);
        }

        $paymentMethod = $paymentData['payment_method'] ?? Purchase::PAYMENT_CREDIT;

        return $this->createForPurchase($purchase, $user, [
            'received_date' => $receivedDate ?? now()->toDateString(),
            'reference' => null,
            'notes' => null,
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentData['payment_reference'] ?? null,
            'cheque_due_date' => $paymentData['cheque_due_date'] ?? null,
            'deduct_account_id' => $paymentData['deduct_account_id'] ?? null,
        ], $lines);
    }

    public function grnForBusiness(Business $business, GoodsReceiveNote $grn): ?GoodsReceiveNote
    {
        if ((int) $grn->business_id !== (int) $business->id) {
            return null;
        }

        return $grn;
    }

    public function nextGrnNumber(Business $business): string
    {
        $maxSeq = 0;
        $numbers = $business->goodsReceiveNotes()->pluck('grn_number');

        foreach ($numbers as $grnNumber) {
            if (preg_match('/^GRN-(\d+)$/', (string) $grnNumber, $matches)) {
                $maxSeq = max($maxSeq, (int) $matches[1]);
            }
        }

        return 'GRN-'.str_pad((string) ($maxSeq + 1), 4, '0', STR_PAD_LEFT);
    }

    public function syncPurchaseReceiptStatus(Purchase $purchase): void
    {
        $purchase->load('items');

        if ($purchase->items->isEmpty()) {
            return;
        }

        $anyReceived = false;
        $allReceived = true;

        foreach ($purchase->items as $item) {
            $received = $item->quantityReceived();
            if ($received > 0) {
                $anyReceived = true;
            }
            if ($received + 0.0001 < (float) $item->quantity) {
                $allReceived = false;
            }
        }

        if ($allReceived) {
            $purchase->status = Purchase::STATUS_RECEIVED;
            $purchase->stock_applied = true;
        } elseif ($anyReceived) {
            $purchase->status = Purchase::STATUS_PARTIALLY_RECEIVED;
        }

        $purchase->save();
    }

    /**
     * @param  array{payment_method: string, payment_reference?: ?string, deduct_account_id?: int|string|null, payment_option?: string, pay_amount?: float|string|null}  $data
     */
    private function maybeSettlePayment(GoodsReceiveNote $grn, Business $business, User $user, array $data): void
    {
        if (!$this->paymentSettlement->requiresImmediatePayment($grn)) {
            return;
        }

        $accountId = $this->nullableInt($data['deduct_account_id'] ?? null);
        if ($accountId === null) {
            throw ValidationException::withMessages([
                'deduct_account_id' => 'Select the account to pay from.',
            ]);
        }

        $payAmount = null;
        if (($data['payment_option'] ?? 'full') === 'partial') {
            $payAmount = round((float) ($data['pay_amount'] ?? 0), 2);
        }

        $this->paymentSettlement->settle(
            $grn,
            $business,
            $user,
            $accountId,
            $payAmount,
            $data['payment_method'] ?? $grn->payment_method,
            $this->normalizePaymentReference($data),
            $this->normalizeChequeDueDate($data),
        );
    }

    /**
     * @param  array{payment_method: string, payment_reference?: ?string}  $data
     */
    private function normalizePaymentReference(array $data): ?string
    {
        if (($data['payment_method'] ?? '') !== Purchase::PAYMENT_CHEQUE) {
            return null;
        }

        $ref = trim((string) ($data['payment_reference'] ?? ''));

        return $ref !== '' ? $ref : null;
    }

    /**
     * @param  array{payment_method: string, cheque_due_date?: ?string}  $data
     */
    private function normalizeChequeDueDate(array $data): ?string
    {
        if (($data['payment_method'] ?? '') !== Purchase::PAYMENT_CHEQUE) {
            return null;
        }

        $date = $data['cheque_due_date'] ?? null;

        return filled($date) ? (string) $date : null;
    }

    /**
     * @param  list<array{purchase_item_id: int, quantity_received: float|string}>  $items
     * @return list<array{purchase_item_id: int, product_id: int, quantity_received: float, unit_cost: float, selling_unit_price: ?float, line_total: float}>
     */
    private function normalizeReceiveLines(Purchase $purchase, array $items): array
    {
        $purchase->load(['items.product', 'business']);
        $normalized = [];

        foreach ($items as $row) {
            $purchaseItemId = (int) ($row['purchase_item_id'] ?? 0);
            $quantityReceived = (float) ($row['quantity_received'] ?? 0);

            if ($purchaseItemId <= 0 || $quantityReceived <= 0) {
                continue;
            }

            $purchaseItem = $purchase->items->firstWhere('id', $purchaseItemId);
            if (!$purchaseItem instanceof PurchaseItem) {
                throw ValidationException::withMessages([
                    'items' => 'One or more lines are invalid for this purchase order.',
                ]);
            }

            $remaining = $purchaseItem->quantityRemaining();
            if ($quantityReceived > $remaining + 0.0001) {
                throw ValidationException::withMessages([
                    'items' => 'Received quantity cannot exceed the remaining amount for a line.',
                ]);
            }

            $unitCost = (float) $purchaseItem->unit_cost;
            $product = $purchaseItem->product;
            $sellingProvided = array_key_exists('selling_unit_price', $row) && $row['selling_unit_price'] !== null && $row['selling_unit_price'] !== ''
                ? (float) $row['selling_unit_price']
                : null;
            $sellingUnitPrice = $product instanceof Product
                ? $this->stockLayers->resolveSellingUnitPrice($purchase->business, $product, $unitCost, $sellingProvided)
                : $sellingProvided;

            $normalized[] = [
                'purchase_item_id' => $purchaseItemId,
                'product_id' => (int) $purchaseItem->product_id,
                'quantity_received' => $quantityReceived,
                'unit_cost' => $unitCost,
                'selling_unit_price' => $sellingUnitPrice,
                'line_total' => round($quantityReceived * $unitCost, 2),
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'items' => 'Enter at least one quantity to receive.',
            ]);
        }

        return $normalized;
    }

    private function recalculateTotals(GoodsReceiveNote $grn): void
    {
        $grn->load('items');
        $subtotal = $grn->items->sum(fn (GoodsReceiveNoteItem $item) => (float) $item->line_total);
        $grn->subtotal = round($subtotal, 2);
        $grn->total = $grn->subtotal;
        $grn->save();
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '0' || $value === 0) {
            return null;
        }

        return (int) $value;
    }
}
