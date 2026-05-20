<?php

namespace Modules\Purchase\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;
use Modules\Purchase\Models\GoodsReceiveNote;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Models\PurchaseItem;

class PurchaseService
{
    public function __construct(
        private readonly GoodsReceiveNoteService $goodsReceiveNoteService,
    ) {
    }

    /**
     * @return Collection<int, Purchase>
     */
    public function listForBusiness(
        Business $business,
        ?string $search = null,
        ?string $status = null,
        ?int $supplierId = null,
    ): Collection {
        $query = $business->purchases()
            ->with(['supplier'])
            ->withCount('items');

        if ($supplierId !== null && $supplierId > 0) {
            $query->where('supplier_id', $supplierId);
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            $allowed = [
                Purchase::STATUS_DRAFT,
                Purchase::STATUS_ORDERED,
                Purchase::STATUS_PARTIALLY_RECEIVED,
                Purchase::STATUS_RECEIVED,
                Purchase::STATUS_CANCELLED,
            ];
            if (in_array($status, $allowed, true)) {
                $query->where('status', $status);
            }
        }

        $term = trim((string) $search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('po_number', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', $like));
            });
        }

        return $query
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get();
    }

    public function businessHasPurchases(Business $business): bool
    {
        return $business->purchases()->exists();
    }

    public function create(Business $business, array $data, array $items): Purchase
    {
        $status = $this->normalizeStatus($data['status'] ?? Purchase::STATUS_DRAFT, allowReceived: true);
        $lines = $this->normalizeItems($business, $items);

        return DB::transaction(function () use ($business, $data, $status, $lines) {
            $purchase = $business->purchases()->create([
                'po_number' => $this->nextPoNumber($business),
                'supplier_id' => $this->nullableInt($data['supplier_id'] ?? null),
                'reference' => filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
                'purchase_date' => $data['purchase_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => $status,
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'subtotal' => 0,
                'total' => 0,
                'stock_applied' => false,
            ]);

            $this->syncItems($purchase, $lines);
            $this->recalculateTotals($purchase);

            if ($purchase->isReceived()) {
                throw ValidationException::withMessages([
                    'status' => 'Use goods receive to add stock. Create the order as draft or ordered, then record a GRN.',
                ]);
            }

            return $purchase->load(['supplier', 'items.product']);
        });
    }

    public function update(Purchase $purchase, array $data, array $items): Purchase
    {
        if (!$purchase->isEditable()) {
            throw ValidationException::withMessages([
                'purchase' => 'Only draft or ordered purchase orders can be edited.',
            ]);
        }

        $status = $this->normalizeStatus($data['status'] ?? $purchase->status, allowReceived: false);
        $lines = $this->normalizeItems($purchase->business, $items);

        return DB::transaction(function () use ($purchase, $data, $status, $lines) {
            $purchase->fill([
                'supplier_id' => $this->nullableInt($data['supplier_id'] ?? null),
                'reference' => filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
                'purchase_date' => $data['purchase_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => $status,
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
            ]);
            $purchase->save();

            $this->syncItems($purchase, $lines);
            $this->recalculateTotals($purchase);

            return $purchase->load(['supplier', 'items.product']);
        });
    }

    public function markOrdered(Purchase $purchase): Purchase
    {
        if ($purchase->isCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'Cancelled purchase orders cannot be placed.',
            ]);
        }

        if ($purchase->isReceived()) {
            return $purchase->load(['supplier', 'items.product']);
        }

        if ($purchase->isOrdered()) {
            return $purchase->load(['supplier', 'items.product']);
        }

        $purchase->status = Purchase::STATUS_ORDERED;
        $purchase->save();

        return $purchase->load(['supplier', 'items.product']);
    }

    public function markReceived(Purchase $purchase, \App\Models\User $user): GoodsReceiveNote
    {
        return $this->goodsReceiveNoteService->receiveAllRemaining($purchase, $user, null, [
            'payment_method' => Purchase::PAYMENT_CREDIT,
        ]);
    }

    public function cancel(Purchase $purchase): Purchase
    {
        if ($purchase->isReceived() || $purchase->isPartiallyReceived()) {
            throw ValidationException::withMessages([
                'status' => 'Purchase orders with goods receipts cannot be cancelled. Adjust stock manually if needed.',
            ]);
        }

        if ($purchase->goodsReceiveNotes()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Purchase orders with goods receive notes cannot be cancelled.',
            ]);
        }

        $purchase->status = Purchase::STATUS_CANCELLED;
        $purchase->save();

        return $purchase->refresh();
    }

    public function delete(Purchase $purchase): bool
    {
        if ($purchase->isReceived() || $purchase->isPartiallyReceived()) {
            throw ValidationException::withMessages([
                'purchase' => 'Purchase orders with goods receipts cannot be deleted.',
            ]);
        }

        if ($purchase->goodsReceiveNotes()->exists()) {
            throw ValidationException::withMessages([
                'purchase' => 'Purchase orders with goods receive notes cannot be deleted.',
            ]);
        }

        return (bool) $purchase->delete();
    }

    public function purchaseForBusiness(Business $business, Purchase $purchase): ?Purchase
    {
        if ((int) $purchase->business_id !== (int) $business->id) {
            return null;
        }

        return $purchase;
    }

    public function nextPoNumber(Business $business): string
    {
        $maxSeq = 0;
        $numbers = $business->purchases()
            ->whereNotNull('po_number')
            ->pluck('po_number');

        foreach ($numbers as $poNumber) {
            if (preg_match('/^PO-(\d+)$/', (string) $poNumber, $matches)) {
                $maxSeq = max($maxSeq, (int) $matches[1]);
            }
        }

        return 'PO-'.str_pad((string) ($maxSeq + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array{product_id: int, quantity: float|string, unit_cost: float|string}>  $items
     * @return list<array{product_id: int, quantity: float, unit_cost: float, line_total: float}>
     */
    private function normalizeItems(Business $business, array $items): array
    {
        $normalized = [];
        foreach ($items as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $quantity = (float) ($row['quantity'] ?? 0);
            $unitCost = (float) ($row['unit_cost'] ?? 0);
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }
            $product = $business->products()->whereKey($productId)->first();
            if (!$product instanceof Product) {
                throw ValidationException::withMessages([
                    'items' => 'One or more products are invalid for this business.',
                ]);
            }
            $lineTotal = round($quantity * $unitCost, 2);
            $normalized[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'line_total' => $lineTotal,
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one product line with quantity.',
            ]);
        }

        return $normalized;
    }

    /**
     * @param  list<array{product_id: int, quantity: float, unit_cost: float, line_total: float}>  $lines
     */
    private function syncItems(Purchase $purchase, array $lines): void
    {
        $purchase->items()->delete();
        foreach ($lines as $index => $line) {
            $purchase->items()->create([
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'line_total' => $line['line_total'],
                'sort_order' => $index,
            ]);
        }
    }

    private function recalculateTotals(Purchase $purchase): void
    {
        $purchase->load('items');
        $subtotal = $purchase->items->sum(fn (PurchaseItem $item) => (float) $item->line_total);
        $purchase->subtotal = round($subtotal, 2);
        $purchase->total = $purchase->subtotal;
        $purchase->save();
    }

    private function normalizeStatus(string $status, bool $allowReceived = true): string
    {
        $allowed = [Purchase::STATUS_DRAFT, Purchase::STATUS_ORDERED, Purchase::STATUS_CANCELLED];
        if ($allowReceived) {
            $allowed[] = Purchase::STATUS_RECEIVED;
        }

        if (!in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid purchase order status.',
            ]);
        }

        return $status;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '0' || $value === 0) {
            return null;
        }

        return (int) $value;
    }
}
