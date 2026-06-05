<?php

declare(strict_types=1);

namespace Modules\Pos\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Sale;
use Modules\Pos\Models\SaleReturn;
use Modules\Pos\Models\SaleReturnItem;
use Modules\Product\Models\Product;

class SaleReturnService
{
    public function __construct(
        private readonly SaleStockConsumptionService $stockConsumption,
    ) {}

    /**
     * @param  list<array{sale_item_id: int, quantity: float}>  $items
     */
    public function processReturn(
        Sale $sale,
        Business $business,
        User $user,
        array $items,
        string $refundMethod,
        ?int $creditAccountId,
        ?string $notes,
        ?string $refundReason = null,
    ): SaleReturn {
        if ((int) $sale->business_id !== (int) $business->id) {
            abort(403);
        }

        if ($sale->isVoid()) {
            throw ValidationException::withMessages([
                'sale' => 'Cannot return items from a voided sale.',
            ]);
        }

        $sale->load(['items.product', 'returns.items']);

        // Build a map of already-returned quantities per sale item
        $alreadyReturned = [];
        foreach ($sale->returns as $existingReturn) {
            foreach ($existingReturn->items as $ri) {
                $alreadyReturned[$ri->pos_sale_item_id] =
                    round(($alreadyReturned[$ri->pos_sale_item_id] ?? 0.0) + (float) $ri->quantity, 3);
            }
        }

        // Index sale items by id for quick lookup
        $saleItemsById = $sale->items->keyBy('id');

        $normalised = [];
        foreach ($items as $row) {
            $saleItemId = (int) ($row['sale_item_id'] ?? 0);
            $qty        = round((float) ($row['quantity'] ?? 0), 3);

            if ($saleItemId <= 0 || $qty <= 0) {
                continue;
            }

            $saleItem = $saleItemsById->get($saleItemId);
            if ($saleItem === null) {
                throw ValidationException::withMessages([
                    'items' => "Item #{$saleItemId} does not belong to this sale.",
                ]);
            }

            $originalQty  = round((float) $saleItem->quantity, 3);
            $returnedSoFar = round($alreadyReturned[$saleItemId] ?? 0.0, 3);
            $returnable    = round($originalQty - $returnedSoFar, 3);

            if ($qty > $returnable + 0.0005) {
                throw ValidationException::withMessages([
                    'items' => "Cannot return more than {$returnable} unit(s) of \"{$saleItem->product_name}\".",
                ]);
            }

            $normalised[] = [
                'sale_item'  => $saleItem,
                'quantity'   => min($qty, $returnable),
            ];
        }

        if ($normalised === []) {
            throw ValidationException::withMessages([
                'items' => 'Select at least one item to return.',
            ]);
        }

        return DB::transaction(function () use ($sale, $business, $user, $normalised, $refundMethod, $creditAccountId, $notes, $refundReason): SaleReturn {
            $total = 0.0;
            foreach ($normalised as $row) {
                $total += round((float) $row['sale_item']->unit_sell_price * $row['quantity'], 2);
            }
            $total = round($total, 2);

            $saleReturn = SaleReturn::query()->create([
                'business_id'       => $business->id,
                'pos_sale_id'       => $sale->id,
                'user_id'           => $user->id,
                'credit_account_id' => $creditAccountId,
                'return_number'     => $this->nextReturnNumber($business),
                'refund_method'     => $refundMethod,
                'refund_reason'     => $refundReason,
                'total'             => $total,
                'notes'             => $notes,
                'returned_at'       => now(),
            ]);

            foreach ($normalised as $row) {
                $saleItem = $row['sale_item'];
                $qty      = $row['quantity'];

                SaleReturnItem::query()->create([
                    'pos_sale_return_id'     => $saleReturn->id,
                    'pos_sale_item_id'       => $saleItem->id,
                    'product_id'             => $saleItem->product_id,
                    'product_stock_layer_id' => $saleItem->product_stock_layer_id,
                    'product_name'           => $saleItem->product_name,
                    'sku'                    => $saleItem->sku,
                    'quantity'               => $qty,
                    'unit_sell_price'        => $saleItem->unit_sell_price,
                    'line_total'             => round((float) $saleItem->unit_sell_price * $qty, 2),
                ]);

                // Restore stock
                $product = $saleItem->product;
                if ($product instanceof Product) {
                    $this->stockConsumption->restoreSaleItem(
                        $saleItem->product_stock_layer_id !== null ? (int) $saleItem->product_stock_layer_id : null,
                        $qty,
                        $product,
                    );
                }
            }

            return $saleReturn->load('items');
        });
    }

    /**
     * Process a return without a sale reference (walk-in / no-receipt return).
     *
     * @param  list<array{product_id: int, quantity: float|string, unit_price: float|string}>  $items
     */
    public function processOpenReturn(
        Business $business,
        User $user,
        array $items,
        string $refundMethod,
        ?int $creditAccountId,
        ?string $notes,
        ?string $refundReason = null,
    ): SaleReturn {
        $normalised = [];

        foreach ($items as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $qty       = round((float) ($row['quantity'] ?? 0), 3);
            $price     = round((float) ($row['unit_price'] ?? 0), 2);

            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            $product = Product::query()
                ->where('id', $productId)
                ->where('business_id', $business->id)
                ->first();

            if ($product === null) {
                throw ValidationException::withMessages([
                    'items' => "Product #{$productId} does not belong to this business.",
                ]);
            }

            $normalised[] = compact('product', 'qty', 'price');
        }

        if ($normalised === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one item to process a return.',
            ]);
        }

        return DB::transaction(function () use ($business, $user, $normalised, $refundMethod, $creditAccountId, $notes, $refundReason): SaleReturn {
            $total = 0.0;
            foreach ($normalised as $row) {
                $total += round($row['price'] * $row['qty'], 2);
            }
            $total = round($total, 2);

            $saleReturn = SaleReturn::query()->create([
                'business_id'       => $business->id,
                'pos_sale_id'       => null,
                'user_id'           => $user->id,
                'credit_account_id' => $creditAccountId,
                'return_number'     => $this->nextReturnNumber($business),
                'refund_method'     => $refundMethod,
                'refund_reason'     => $refundReason,
                'total'             => $total,
                'notes'             => $notes,
                'returned_at'       => now(),
            ]);

            foreach ($normalised as $row) {
                /** @var Product $product */
                $product = $row['product'];

                SaleReturnItem::query()->create([
                    'pos_sale_return_id'     => $saleReturn->id,
                    'pos_sale_item_id'       => null,
                    'product_id'             => $product->id,
                    'product_stock_layer_id' => null,
                    'product_name'           => $product->name,
                    'sku'                    => $product->sku,
                    'quantity'               => $row['qty'],
                    'unit_sell_price'        => $row['price'],
                    'line_total'             => round($row['price'] * $row['qty'], 2),
                ]);

                $this->stockConsumption->restoreSaleItem(null, $row['qty'], $product);
            }

            return $saleReturn->load('items');
        });
    }

    /** Returns quantities already returned per sale_item_id for the given sale. */
    public function returnedQuantitiesForSale(Sale $sale): array
    {
        $map = [];
        foreach ($sale->returns()->with('items')->get() as $ret) {
            foreach ($ret->items as $ri) {
                $map[$ri->pos_sale_item_id] =
                    round(($map[$ri->pos_sale_item_id] ?? 0.0) + (float) $ri->quantity, 3);
            }
        }

        return $map;
    }

    private function nextReturnNumber(Business $business): string
    {
        $maxSeq = 0;
        $numbers = SaleReturn::query()
            ->where('business_id', $business->id)
            ->whereNotNull('return_number')
            ->pluck('return_number');

        foreach ($numbers as $num) {
            if (preg_match('/^RET-(\d+)$/', (string) $num, $m)) {
                $maxSeq = max($maxSeq, (int) $m[1]);
            }
        }

        return 'RET-'.str_pad((string) ($maxSeq + 1), 4, '0', STR_PAD_LEFT);
    }
}
