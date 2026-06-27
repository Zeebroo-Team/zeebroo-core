<?php

namespace Modules\Restaurant\Services;

use Illuminate\Support\Facades\DB;
use Modules\Restaurant\Models\Ingredient;
use Modules\Restaurant\Models\IngredientGrn;
use Modules\Restaurant\Models\Order;
use Modules\Restaurant\Models\StockTransaction;

class IngredientStockService
{
    public function addStock(Ingredient $ingredient, float $qty, string $notes = ''): void
    {
        DB::transaction(function () use ($ingredient, $qty, $notes) {
            $ingredient->increment('quantity', $qty);
            $ingredient->refresh();

            StockTransaction::create([
                'ingredient_id'  => $ingredient->id,
                'business_id'    => $ingredient->business_id,
                'type'           => 'purchase',
                'quantity_change' => $qty,
                'quantity_after'  => (float) $ingredient->quantity,
                'notes'          => $notes ?: 'Stock added',
            ]);
        });
    }

    public function addStockFromGrn(Ingredient $ingredient, float $qty, IngredientGrn $grn): void
    {
        DB::transaction(function () use ($ingredient, $qty, $grn) {
            $ingredient->increment('quantity', $qty);
            $ingredient->refresh();

            StockTransaction::create([
                'ingredient_id'   => $ingredient->id,
                'business_id'     => $ingredient->business_id,
                'type'            => 'purchase',
                'quantity_change' => $qty,
                'quantity_after'  => (float) $ingredient->quantity,
                'notes'           => "GRN #{$grn->grn_number}",
                'reference_type'  => IngredientGrn::class,
                'reference_id'    => $grn->id,
            ]);
        });
    }

    public function adjust(Ingredient $ingredient, float $newQuantity, string $notes = ''): void
    {
        DB::transaction(function () use ($ingredient, $newQuantity, $notes) {
            $diff = $newQuantity - (float) $ingredient->quantity;
            $ingredient->update(['quantity' => $newQuantity]);

            StockTransaction::create([
                'ingredient_id'   => $ingredient->id,
                'business_id'     => $ingredient->business_id,
                'type'            => 'adjustment',
                'quantity_change' => $diff,
                'quantity_after'  => $newQuantity,
                'notes'           => $notes ?: 'Manual adjustment',
            ]);
        });
    }

    public function recordWaste(Ingredient $ingredient, float $qty, string $notes = ''): void
    {
        DB::transaction(function () use ($ingredient, $qty, $notes) {
            $newQty = max(0, (float) $ingredient->quantity - $qty);
            $ingredient->update(['quantity' => $newQty]);

            StockTransaction::create([
                'ingredient_id'   => $ingredient->id,
                'business_id'     => $ingredient->business_id,
                'type'            => 'waste',
                'quantity_change' => -$qty,
                'quantity_after'  => $newQty,
                'notes'           => $notes ?: 'Waste recorded',
            ]);
        });
    }

    public function deductForOrder(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if (! $item->menu_item_id) {
                continue;
            }

            $menuItem = $item->menuItem ?? $item->load('menuItem')->menuItem;
            if (! $menuItem) {
                continue;
            }

            $menuItem->load('ingredients');

            foreach ($menuItem->ingredients as $ingredient) {
                $needed = (float) $ingredient->pivot->quantity_required * $item->quantity;

                DB::transaction(function () use ($ingredient, $needed, $order) {
                    $newQty = max(0, (float) $ingredient->quantity - $needed);
                    $ingredient->update(['quantity' => $newQty]);

                    StockTransaction::create([
                        'ingredient_id'   => $ingredient->id,
                        'business_id'     => $ingredient->business_id,
                        'type'            => 'deduction',
                        'quantity_change' => -$needed,
                        'quantity_after'  => $newQty,
                        'notes'           => "Order #{$order->order_number}",
                        'reference_type'  => Order::class,
                        'reference_id'    => $order->id,
                    ]);
                });
            }
        }
    }

    public function restoreForOrder(Order $order): void
    {
        $transactions = StockTransaction::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', 'deduction')
            ->get();

        foreach ($transactions as $tx) {
            $ingredient = Ingredient::find($tx->ingredient_id);
            if (! $ingredient) {
                continue;
            }

            DB::transaction(function () use ($ingredient, $tx, $order) {
                $restored = abs((float) $tx->quantity_change);
                $newQty   = (float) $ingredient->quantity + $restored;
                $ingredient->update(['quantity' => $newQty]);

                StockTransaction::create([
                    'ingredient_id'   => $ingredient->id,
                    'business_id'     => $ingredient->business_id,
                    'type'            => 'adjustment',
                    'quantity_change' => $restored,
                    'quantity_after'  => $newQty,
                    'notes'           => "Restored — order #{$order->order_number} cancelled",
                    'reference_type'  => Order::class,
                    'reference_id'    => $order->id,
                ]);
            });
        }
    }
}
