<?php

namespace Modules\Restaurant\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Restaurant\Models\Ingredient;
use Modules\Restaurant\Models\IngredientGrn;
use Modules\Restaurant\Models\IngredientGrnItem;
use Modules\Restaurant\Models\IngredientPurchaseOrder;
use Modules\Restaurant\Models\IngredientPurchaseOrderItem;

class IngredientPurchaseService
{
    public function __construct(
        private readonly IngredientStockService $stockService,
    ) {}

    public function listForBusiness(Business $business, string $status = 'all'): \Illuminate\Database\Eloquent\Collection
    {
        $query = IngredientPurchaseOrder::where('business_id', $business->id)
            ->with(['supplier'])
            ->withCount('items');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return $query->orderByDesc('purchase_date')->orderByDesc('id')->get();
    }

    public function create(Business $business, array $data, array $rawItems): IngredientPurchaseOrder
    {
        $lines = $this->normalizeItems($business, $rawItems);

        return DB::transaction(function () use ($business, $data, $lines) {
            $po = IngredientPurchaseOrder::create([
                'business_id'            => $business->id,
                'supplier_id'            => $data['supplier_id'] ?? null,
                'po_number'              => $this->nextPoNumber($business),
                'purchase_date'          => $data['purchase_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status'                 => $data['status'] ?? IngredientPurchaseOrder::STATUS_DRAFT,
                'notes'                  => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'subtotal'               => 0,
                'total'                  => 0,
            ]);

            $this->syncItems($po, $lines);
            $this->recalculate($po);

            return $po->load(['supplier', 'items.ingredient']);
        });
    }

    public function update(IngredientPurchaseOrder $po, array $data, array $rawItems): IngredientPurchaseOrder
    {
        if (!$po->isEditable()) {
            throw ValidationException::withMessages([
                'purchase' => 'Only draft or ordered purchase orders can be edited.',
            ]);
        }

        $lines = $this->normalizeItems($po->business, $rawItems);

        return DB::transaction(function () use ($po, $data, $lines) {
            $po->update([
                'supplier_id'            => $data['supplier_id'] ?? null,
                'purchase_date'          => $data['purchase_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status'                 => $data['status'] ?? $po->status,
                'notes'                  => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
            ]);

            $this->syncItems($po, $lines);
            $this->recalculate($po);

            return $po->load(['supplier', 'items.ingredient']);
        });
    }

    public function markOrdered(IngredientPurchaseOrder $po): IngredientPurchaseOrder
    {
        if ($po->isCancelled()) {
            throw ValidationException::withMessages(['status' => 'Cancelled orders cannot be placed.']);
        }

        if (!$po->isDraft()) {
            return $po;
        }

        $po->update(['status' => IngredientPurchaseOrder::STATUS_ORDERED]);

        return $po->fresh(['supplier', 'items.ingredient']);
    }

    public function cancel(IngredientPurchaseOrder $po): IngredientPurchaseOrder
    {
        if ($po->isReceived() || $po->isPartiallyReceived()) {
            throw ValidationException::withMessages([
                'status' => 'Orders with goods receipts cannot be cancelled.',
            ]);
        }

        $po->update(['status' => IngredientPurchaseOrder::STATUS_CANCELLED]);

        return $po->refresh();
    }

    public function delete(IngredientPurchaseOrder $po): void
    {
        if ($po->isReceived() || $po->isPartiallyReceived()) {
            throw ValidationException::withMessages([
                'purchase' => 'Orders with goods receipts cannot be deleted.',
            ]);
        }

        if ($po->grns()->exists()) {
            throw ValidationException::withMessages([
                'purchase' => 'Orders with goods receive notes cannot be deleted.',
            ]);
        }

        $po->delete();
    }

    public function createGrn(IngredientPurchaseOrder $po, array $data, array $rawLines): IngredientGrn
    {
        if (!$po->canReceiveGoods()) {
            throw ValidationException::withMessages([
                'purchase' => 'This purchase order cannot receive more goods.',
            ]);
        }

        $po->load(['items.ingredient', 'business']);
        $lines = $this->normalizeGrnLines($po, $rawLines);

        return DB::transaction(function () use ($po, $data, $lines) {
            $grn = IngredientGrn::create([
                'business_id'       => $po->business_id,
                'purchase_order_id' => $po->id,
                'grn_number'        => $this->nextGrnNumber($po->business),
                'received_date'     => $data['received_date'],
                'payment_method'    => $data['payment_method'] ?? 'credit',
                'reference'         => filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
                'notes'             => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'subtotal'          => 0,
                'total'             => 0,
            ]);

            foreach ($lines as $i => $line) {
                IngredientGrnItem::create([
                    'grn_id'                 => $grn->id,
                    'purchase_order_item_id' => $line['purchase_order_item_id'],
                    'ingredient_id'          => $line['ingredient_id'],
                    'quantity_received'      => $line['quantity_received'],
                    'unit_cost'              => $line['unit_cost'],
                    'line_total'             => $line['line_total'],
                    'sort_order'             => $i,
                ]);

                $ingredient = Ingredient::find($line['ingredient_id']);
                if ($ingredient) {
                    $this->stockService->addStockFromGrn($ingredient, $line['quantity_received'], $grn);
                    // Update ingredient's cost_per_unit to reflect latest purchase cost
                    if ($line['unit_cost'] > 0) {
                        $ingredient->update(['cost_per_unit' => $line['unit_cost']]);
                    }
                }
            }

            $subtotal = collect($lines)->sum(fn ($l) => $l['line_total']);
            $grn->update(['subtotal' => round($subtotal, 2), 'total' => round($subtotal, 2)]);

            $this->syncPoReceiptStatus($po);

            return $grn->load(['purchaseOrder.supplier', 'items.ingredient']);
        });
    }

    public function listGrnsForBusiness(Business $business): \Illuminate\Database\Eloquent\Collection
    {
        return IngredientGrn::where('business_id', $business->id)
            ->with(['purchaseOrder.supplier'])
            ->withCount('items')
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->get();
    }

    private function syncItems(IngredientPurchaseOrder $po, array $lines): void
    {
        $po->items()->delete();
        foreach ($lines as $i => $line) {
            $po->items()->create([
                'ingredient_id' => $line['ingredient_id'],
                'quantity'      => $line['quantity'],
                'unit_cost'     => $line['unit_cost'],
                'line_total'    => $line['line_total'],
                'sort_order'    => $i,
            ]);
        }
    }

    private function recalculate(IngredientPurchaseOrder $po): void
    {
        $po->load('items');
        $subtotal = $po->items->sum(fn ($item) => (float) $item->line_total);
        $po->update(['subtotal' => round($subtotal, 2), 'total' => round($subtotal, 2)]);
    }

    private function syncPoReceiptStatus(IngredientPurchaseOrder $po): void
    {
        $po->load('items');
        if ($po->items->isEmpty()) {
            return;
        }

        $anyReceived = false;
        $allReceived = true;

        foreach ($po->items as $item) {
            $received = $item->quantityReceived();
            if ($received > 0) {
                $anyReceived = true;
            }
            if ($received + 0.0001 < (float) $item->quantity) {
                $allReceived = false;
            }
        }

        if ($allReceived) {
            $po->update(['status' => IngredientPurchaseOrder::STATUS_RECEIVED]);
        } elseif ($anyReceived) {
            $po->update(['status' => IngredientPurchaseOrder::STATUS_PARTIALLY_RECEIVED]);
        }
    }

    /**
     * @return list<array{ingredient_id: int, quantity: float, unit_cost: float, line_total: float}>
     */
    private function normalizeItems(Business $business, array $rawItems): array
    {
        $lines = [];
        foreach ($rawItems as $row) {
            $ingredientId = (int) ($row['ingredient_id'] ?? 0);
            $quantity     = (float) ($row['quantity'] ?? 0);
            $unitCost     = (float) ($row['unit_cost'] ?? 0);

            if ($ingredientId <= 0 || $quantity <= 0) {
                continue;
            }

            $exists = Ingredient::where('id', $ingredientId)
                ->where('business_id', $business->id)
                ->exists();

            if (!$exists) {
                throw ValidationException::withMessages([
                    'items' => 'One or more ingredients are invalid.',
                ]);
            }

            $lines[] = [
                'ingredient_id' => $ingredientId,
                'quantity'      => round($quantity, 3),
                'unit_cost'     => round($unitCost, 4),
                'line_total'    => round($quantity * $unitCost, 2),
            ];
        }

        if (empty($lines)) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one ingredient line.',
            ]);
        }

        return $lines;
    }

    /**
     * @return list<array{purchase_order_item_id: int, ingredient_id: int, quantity_received: float, unit_cost: float, line_total: float}>
     */
    private function normalizeGrnLines(IngredientPurchaseOrder $po, array $rawLines): array
    {
        $lines = [];
        foreach ($rawLines as $row) {
            $poItemId          = (int) ($row['purchase_order_item_id'] ?? 0);
            $quantityReceived  = (float) ($row['quantity_received'] ?? 0);

            if ($poItemId <= 0 || $quantityReceived <= 0) {
                continue;
            }

            /** @var IngredientPurchaseOrderItem|null $poItem */
            $poItem = $po->items->firstWhere('id', $poItemId);
            if (!$poItem) {
                throw ValidationException::withMessages([
                    'items' => 'One or more lines are invalid for this purchase order.',
                ]);
            }

            $remaining = $poItem->quantityRemaining();
            if ($quantityReceived > $remaining + 0.001) {
                throw ValidationException::withMessages([
                    'items' => "Received quantity for {$poItem->ingredient->name} exceeds remaining amount ({$remaining}).",
                ]);
            }

            $unitCost = (float) $poItem->unit_cost;
            $lines[] = [
                'purchase_order_item_id' => $poItemId,
                'ingredient_id'          => (int) $poItem->ingredient_id,
                'quantity_received'      => round($quantityReceived, 3),
                'unit_cost'              => $unitCost,
                'line_total'             => round($quantityReceived * $unitCost, 2),
            ];
        }

        if (empty($lines)) {
            throw ValidationException::withMessages([
                'items' => 'Enter at least one quantity to receive.',
            ]);
        }

        return $lines;
    }

    private function nextPoNumber(Business $business): string
    {
        $max = 0;
        $numbers = IngredientPurchaseOrder::where('business_id', $business->id)
            ->whereNotNull('po_number')
            ->pluck('po_number');

        foreach ($numbers as $num) {
            if (preg_match('/^IPO-(\d+)$/', (string) $num, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return 'IPO-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    private function nextGrnNumber(Business $business): string
    {
        $max = 0;
        $numbers = IngredientGrn::where('business_id', $business->id)
            ->whereNotNull('grn_number')
            ->pluck('grn_number');

        foreach ($numbers as $num) {
            if (preg_match('/^IGRN-(\d+)$/', (string) $num, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return 'IGRN-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
