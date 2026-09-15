<?php

namespace Modules\Pos\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;
use Modules\Pos\Models\ProductRental;
use Modules\Product\Models\Product;

class ProductRentalService
{
    public function __construct(
        private readonly SaleStockConsumptionService $stockConsumption,
    ) {
    }

    public function createForSaleLine(
        Business $business,
        Product $product,
        int $saleId,
        ?int $saleItemId,
        ?int $customerId,
        ?int $branchId,
        float $dailyRate,
        float $quantity,
        string $returnDate,
    ): ProductRental {
        return ProductRental::query()->create([
            'business_id'          => $business->id,
            'branch_id'            => $branchId,
            'product_id'           => $product->id,
            'pos_customer_id'      => $customerId,
            'pos_sale_id'          => $saleId,
            'pos_sale_item_id'     => $saleItemId,
            'daily_rate'           => round($dailyRate, 2),
            'quantity'             => round($quantity, 3),
            'rented_at'            => now()->toDateString(),
            'due_at'               => $returnDate,
            'late_fee_multiplier'  => (float) ($product->rental_late_fee_multiplier ?? 0),
            'status'               => ProductRental::STATUS_ACTIVE,
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, ProductRental>
     */
    public function list(Business $business, ?string $status, ?string $search, int $perPage = 25): LengthAwarePaginator
    {
        return ProductRental::query()
            ->where('business_id', $business->id)
            ->with(['customer:id,name,phone,email', 'product:id,name,sku', 'sale:id,sale_number'])
            ->when($status && $status !== 'all', function ($q) use ($status) {
                if ($status === 'overdue') {
                    $q->where('status', ProductRental::STATUS_ACTIVE)->whereDate('due_at', '<', now()->toDateString());
                } elseif ($status === 'active') {
                    $q->where('status', ProductRental::STATUS_ACTIVE)->whereDate('due_at', '>=', now()->toDateString());
                } else {
                    $q->where('status', $status);
                }
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function markReturned(ProductRental $rental): ProductRental
    {
        abort_unless(
            in_array($rental->status, [ProductRental::STATUS_ACTIVE, ProductRental::STATUS_OVERDUE], true),
            422,
            'This rental has already been closed out.',
        );

        return DB::transaction(function () use ($rental) {
            $rental->load('saleItem.product', 'product');

            $returnedAt = now()->startOfDay();
            $lateFee = 0.0;
            if ($rental->due_at !== null && $returnedAt->gt($rental->due_at)) {
                $daysLate = (int) $rental->due_at->diffInDays($returnedAt);
                $lateFee = round($daysLate * (float) $rental->daily_rate * (float) $rental->late_fee_multiplier, 2);
            }

            $product = $rental->saleItem?->product ?? $rental->product;
            if ($product !== null) {
                $this->stockConsumption->restoreSaleItem(
                    $rental->saleItem?->product_stock_layer_id !== null ? (int) $rental->saleItem->product_stock_layer_id : null,
                    (float) $rental->quantity,
                    $product,
                );
            }

            $rental->update([
                'returned_at' => $returnedAt->toDateString(),
                'late_fee'    => $lateFee,
                'status'      => ProductRental::STATUS_RETURNED,
            ]);

            return $rental->fresh();
        });
    }

    /**
     * Cancels the tracking record for a voided sale's rental line without touching
     * stock — SaleService::void() already restores stock unconditionally for every
     * product sale item, so restoring it again here would double-count it.
     */
    public function cancelForSaleItem(int $saleItemId): void
    {
        ProductRental::query()
            ->where('pos_sale_item_id', $saleItemId)
            ->whereIn('status', [ProductRental::STATUS_ACTIVE, ProductRental::STATUS_OVERDUE])
            ->update(['status' => ProductRental::STATUS_CANCELLED]);
    }
}
