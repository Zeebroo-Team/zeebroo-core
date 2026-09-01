<?php

namespace Modules\Pos\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Branch;
use Modules\Business\Models\Business;
use Modules\Pos\Models\StockTransfer;
use Modules\Pos\Models\StockTransferLine;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductStockLayer;
use Modules\Product\Services\ProductStockLayerService;

class StockTransferService
{
    private const PER_PAGE = 25;
    private const QTY_TOLERANCE = 0.0001;

    public function __construct(
        private readonly ProductStockLayerService $stockLayers,
    ) {
    }

    // ── Queries ────────────────────────────────────────────────────

    public function listForBusiness(Business $business, ?string $search = null): LengthAwarePaginator
    {
        return StockTransfer::query()
            ->where('business_id', $business->id)
            ->when($search, fn ($q) => $q->where('transfer_number', 'like', '%'.$search.'%'))
            ->with(['fromBranch', 'toBranch', 'transferredBy', 'receivedBy'])
            ->withCount('lines')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }

    public function transferForBusiness(Business $business, StockTransfer $transfer): StockTransfer
    {
        abort_unless((int) $transfer->business_id === (int) $business->id, 404);

        return $transfer;
    }

    // ── Mutations ──────────────────────────────────────────────────

    public function create(Business $business, array $data, User $user): StockTransfer
    {
        $fromBranchId = (int) $data['from_branch_id'];
        $toBranchId   = (int) $data['to_branch_id'];

        if ($fromBranchId === $toBranchId) {
            throw ValidationException::withMessages([
                'to_branch_id' => 'The destination branch must be different from the source branch.',
            ]);
        }

        $branches = Branch::query()
            ->where('business_id', $business->id)
            ->whereIn('id', [$fromBranchId, $toBranchId])
            ->get(['id', 'name'])
            ->keyBy('id');

        if (! $branches->has($fromBranchId) || ! $branches->has($toBranchId)) {
            throw ValidationException::withMessages([
                'from_branch_id' => 'Select branches that belong to this business.',
            ]);
        }

        $lines = $data['lines'] ?? [];
        if (empty($lines)) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least one product to transfer.',
            ]);
        }

        return DB::transaction(function () use ($business, $data, $user, $fromBranchId, $toBranchId, $branches, $lines): StockTransfer {
            $transfer = StockTransfer::create([
                'business_id'     => $business->id,
                'from_branch_id'  => $fromBranchId,
                'to_branch_id'    => $toBranchId,
                'transfer_number' => $this->nextTransferNumber($business),
                'status'          => StockTransfer::STATUS_IN_TRANSIT,
                'notes'           => $data['notes'] ?? null,
                'transferred_by'  => $user->id,
                'transferred_at'  => now(),
            ]);

            foreach ($lines as $lineData) {
                $product = Product::query()
                    ->where('business_id', $business->id)
                    ->whereKey((int) ($lineData['product_id'] ?? 0))
                    ->lockForUpdate()
                    ->first();

                if ($product === null) {
                    throw ValidationException::withMessages([
                        'lines' => 'One of the selected products was not found.',
                    ]);
                }

                $quantity = round((float) ($lineData['quantity'] ?? 0), 3);
                if ($quantity <= self::QTY_TOLERANCE) {
                    throw ValidationException::withMessages([
                        'lines' => "Quantity for {$product->name} must be greater than zero.",
                    ]);
                }

                $consumed = $this->consumeFromBranch($product, $fromBranchId, $branches[$fromBranchId]->name, $quantity);

                $totalCost = array_sum(array_map(
                    fn (array $c) => $c['qty'] * $c['unit_cost'],
                    $consumed,
                ));
                $weightedUnitCost = round($totalCost / $quantity, 2);

                StockTransferLine::create([
                    'stock_transfer_id'  => $transfer->id,
                    'product_id'         => $product->id,
                    'product_name'       => $product->name,
                    'sku'                => $product->sku,
                    'unit'               => $product->unit,
                    'quantity'           => $quantity,
                    'unit_cost'          => $weightedUnitCost,
                    'consumed_breakdown' => $consumed,
                ]);
            }

            return $transfer;
        });
    }

    public function receive(StockTransfer $transfer, User $user): void
    {
        abort_unless($transfer->isInTransit(), 422, 'Only an in-transit transfer can be marked as received.');

        DB::transaction(function () use ($transfer, $user): void {
            $transfer->load(['lines.product.business']);

            foreach ($transfer->lines as $line) {
                $product = $line->product;
                if ($product === null) {
                    continue;
                }

                $unitCost = (float) $line->unit_cost;
                $sellingPrice = $this->stockLayers->defaultSellingUnitPrice($product->business, $product, $unitCost);

                $layer = ProductStockLayer::create([
                    'business_id'                => $transfer->business_id,
                    'branch_id'                  => $transfer->to_branch_id,
                    'product_id'                 => $product->id,
                    'goods_receive_note_item_id' => null,
                    'stock_transfer_line_id'     => $line->id,
                    'quantity_received'          => $line->quantity,
                    'quantity_remaining'         => $line->quantity,
                    'unit_cost'                  => $unitCost,
                    'selling_unit_price'         => $sellingPrice,
                    'received_at'                => now()->toDateString(),
                ]);

                $this->stockLayers->assignBatchSku($layer, $product);

                $line->update(['destination_layer_id' => $layer->id]);
            }

            $transfer->update([
                'status'      => StockTransfer::STATUS_COMPLETED,
                'received_by' => $user->id,
                'received_at' => now(),
            ]);
        });
    }

    public function cancel(StockTransfer $transfer, User $user): void
    {
        abort_unless($transfer->isInTransit(), 422, 'Only an in-transit transfer can be cancelled.');

        DB::transaction(function () use ($transfer, $user): void {
            $transfer->load('lines');

            foreach ($transfer->lines as $line) {
                foreach ((array) $line->consumed_breakdown as $entry) {
                    $layer = ProductStockLayer::query()
                        ->whereKey($entry['layer_id'] ?? null)
                        ->lockForUpdate()
                        ->first();

                    if ($layer === null) {
                        continue;
                    }

                    $layer->quantity_remaining = min(
                        (float) $layer->quantity_received,
                        round((float) $layer->quantity_remaining + (float) $entry['qty'], 3),
                    );
                    $layer->save();
                }
            }

            $transfer->update([
                'status'       => StockTransfer::STATUS_CANCELLED,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
            ]);
        });
    }

    // ── Private helpers ────────────────────────────────────────────

    /**
     * FIFO-consume $quantity from the given product's stock layers at $branchId.
     *
     * @return list<array{layer_id: int, qty: float, unit_cost: float}>
     */
    private function consumeFromBranch(Product $product, int $branchId, string $branchName, float $quantity): array
    {
        $layers = ProductStockLayer::query()
            ->where('product_id', $product->id)
            ->where('business_id', $product->business_id)
            ->where('branch_id', $branchId)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining   = $quantity;
        $allocations = [];

        foreach ($layers as $layer) {
            if ($remaining <= self::QTY_TOLERANCE) {
                break;
            }

            $available = (float) $layer->quantity_remaining;
            if ($available <= self::QTY_TOLERANCE) {
                continue;
            }

            $take = min($remaining, $available);
            $allocations[] = [
                'layer_id'  => (int) $layer->id,
                'qty'       => round($take, 3),
                'unit_cost' => round((float) $layer->unit_cost, 2),
            ];

            $layer->quantity_remaining = round($available - $take, 3);
            $layer->save();
            $remaining = round($remaining - $take, 3);
        }

        if ($remaining > self::QTY_TOLERANCE) {
            $availableTotal = round($quantity - $remaining, 3);
            throw ValidationException::withMessages([
                'lines' => "Insufficient stock for {$product->name} at {$branchName}. Available: {$availableTotal}.",
            ]);
        }

        return $allocations;
    }

    private function nextTransferNumber(Business $business): string
    {
        $last = StockTransfer::query()
            ->where('business_id', $business->id)
            ->orderByDesc('id')
            ->value('transfer_number');

        if ($last && preg_match('/TRF-(\d+)$/i', $last, $m)) {
            return 'TRF-' . str_pad((int) $m[1] + 1, 4, '0', STR_PAD_LEFT);
        }

        return 'TRF-0001';
    }
}
