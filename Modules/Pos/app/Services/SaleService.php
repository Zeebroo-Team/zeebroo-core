<?php

namespace Modules\Pos\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Sale;
use Modules\Pos\Models\SaleItem;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductStockLayer;

class SaleService
{
    private const MONEY_TOLERANCE = 0.005;

    public function __construct(
        private readonly SaleStockConsumptionService $stockConsumption,
        private readonly SalePaymentSettlementService $payments,
    ) {
    }

    /**
     * @return Collection<int, Sale>
     */
    public function listForBusiness(Business $business, ?string $search = null): Collection
    {
        $query = $business->sales()
            ->with(['user', 'creditAccount'])
            ->withCount('items');

        $term = trim((string) $search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('sale_number', 'like', $like)
                    ->orWhere('notes', 'like', $like);
            });
        }

        return $query
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->get();
    }

    public function businessHasSales(Business $business): bool
    {
        return $business->sales()->exists();
    }

    /**
     * @return array{count: int, total: float, online_count: int, online_total: float}
     */
    public function todaySummaryForBusiness(Business $business): array
    {
        $start = now()->startOfDay();

        $base = $business->sales()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('sold_at', '>=', $start);

        $online = (clone $base)->where('channel', Sale::CHANNEL_ONLINE);

        return [
            'count' => (int) (clone $base)->count(),
            'total' => round((float) (clone $base)->sum('total'), 2),
            'online_count' => (int) $online->count(),
            'online_total' => round((float) $online->sum('total'), 2),
        ];
    }

    /**
     * @param  list<array{product_id: int, quantity: float|string}>  $items
     */
    public function checkout(
        Business $business,
        User $user,
        array $items,
        string $paymentMethod,
        ?int $creditAccountId,
        ?float $amountPaid,
        ?string $notes,
        string $channel = Sale::CHANNEL_RETAIL,
        ?float $discountPercent = null,
        ?float $amountTendered = null,
        ?int $customerId = null,
        bool $deferSettlement = false,
    ): Sale {
        $lines = $this->normalizeCartItems($business, $items);
        $paymentMethod = $this->normalizePaymentMethod($paymentMethod);
        $channel = $this->normalizeChannel($channel);

        return DB::transaction(function () use ($business, $user, $lines, $paymentMethod, $creditAccountId, $amountPaid, $notes, $channel, $discountPercent, $amountTendered, $customerId, $deferSettlement) {
            $sale = $business->sales()->create([
                'user_id' => $user->id,
                'sale_number' => $this->nextSaleNumber($business),
                'status' => Sale::STATUS_COMPLETED,
                'payment_method' => $paymentMethod,
                'channel' => $channel,
                'credit_account_id' => in_array($paymentMethod, [Sale::PAYMENT_CASH, Sale::PAYMENT_CARD], true)
                    ? $creditAccountId
                    : null,
                'pos_customer_id' => $customerId,
                'subtotal' => 0,
                'total' => 0,
                'amount_paid' => 0,
                'notes' => filled($notes) ? trim((string) $notes) : null,
                'sold_at' => now(),
                'is_settled' => !$deferSettlement,
                'settled_at' => $deferSettlement ? null : now(),
            ]);

            $subtotal = 0.0;
            $sortOrder = 0;

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $layerId = $line['product_stock_layer_id'] ?? null;
                $allocations = $layerId !== null
                    ? $this->stockConsumption->consumeFromLayer($product, (int) $layerId, $line['quantity'])
                    : $this->stockConsumption->consumeFifo($product, $line['quantity']);

                foreach ($allocations as $allocation) {
                    $lineTotal = round($allocation['quantity'] * $allocation['unit_sell_price'], 2);
                    $subtotal = round($subtotal + $lineTotal, 2);

                    SaleItem::query()->create([
                        'pos_sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'product_stock_layer_id' => $allocation['product_stock_layer_id'],
                        'product_name' => $product->name,
                        'sku' => $product->sku,
                        'selling_unit_label'  => $line['selling_unit_label'] ?? null,
                        'selling_unit_factor' => isset($line['selling_unit_factor']) ? round((float) $line['selling_unit_factor'], 6) : null,
                        'quantity' => $allocation['quantity'],
                        'unit_cost' => $allocation['unit_cost'],
                        'unit_sell_price' => $allocation['unit_sell_price'],
                        'line_total' => $lineTotal,
                        'sort_order' => $sortOrder++,
                    ]);
                }
            }

            $discountPercentValue = $discountPercent !== null
                ? round(max(0, min(100, $discountPercent)), 2)
                : null;
            $discountAmount = $discountPercentValue !== null && $discountPercentValue > 0
                ? round($subtotal * ($discountPercentValue / 100), 2)
                : 0.0;
            $total = round(max(0, $subtotal - $discountAmount), 2);
            $tendered = null;
            $change = null;

            if ($paymentMethod === Sale::PAYMENT_CASH) {
                $tendered = $amountTendered !== null
                    ? round((float) $amountTendered, 2)
                    : ($amountPaid !== null ? round((float) $amountPaid, 2) : $total);

                if ($tendered + self::MONEY_TOLERANCE < $total) {
                    throw ValidationException::withMessages([
                        'amount_tendered' => 'Amount received must be at least the sale total.',
                    ]);
                }

                $change = round(max(0, $tendered - $total), 2);
            }

            $paid = match ($paymentMethod) {
                Sale::PAYMENT_CASH, Sale::PAYMENT_CARD => $total,
                default => 0.0,
            };

            $sale->update([
                'subtotal' => $subtotal,
                'discount_percent' => $discountPercentValue,
                'discount_amount' => $discountAmount,
                'total' => $total,
                'amount_paid' => $paid,
                'amount_tendered' => $tendered,
                'change_amount' => $change,
            ]);

            if (!$deferSettlement && in_array($paymentMethod, [Sale::PAYMENT_CASH, Sale::PAYMENT_CARD], true)) {
                $this->payments->settle($sale, $business, $user, (int) $creditAccountId, $total, $paymentMethod);
            }

            return $sale->refresh()->load(['items.product', 'creditAccount', 'user']);
        });
    }

    public function void(Sale $sale, Business $business): Sale
    {
        if ((int) $sale->business_id !== (int) $business->id) {
            abort(403);
        }

        if ($sale->isVoid()) {
            throw ValidationException::withMessages([
                'sale' => 'This sale is already void.',
            ]);
        }

        return DB::transaction(function () use ($sale) {
            $sale->load(['items.product']);

            foreach ($sale->items as $item) {
                $product = $item->product;
                if (!$product instanceof Product) {
                    continue;
                }

                $this->stockConsumption->restoreSaleItem(
                    $item->product_stock_layer_id !== null ? (int) $item->product_stock_layer_id : null,
                    (float) $item->quantity,
                    $product,
                );
            }

            $sale->update(['status' => Sale::STATUS_VOID]);

            return $sale->refresh();
        });
    }

    public function nextSaleNumber(Business $business): string
    {
        $maxSeq = 0;
        $numbers = $business->sales()->whereNotNull('sale_number')->pluck('sale_number');

        foreach ($numbers as $saleNumber) {
            if (preg_match('/^POS-(\d+)$/', (string) $saleNumber, $matches)) {
                $maxSeq = max($maxSeq, (int) $matches[1]);
            }
        }

        return 'POS-'.str_pad((string) ($maxSeq + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array{product_id: int, quantity: float|string, product_stock_layer_id?: int|null, selling_unit_label?: ?string, selling_unit_factor?: float|null}>  $items
     * @return list<array{product: Product, quantity: float, product_stock_layer_id: ?int, selling_unit_label: ?string, selling_unit_factor: ?float}>
     */
    private function normalizeCartItems(Business $business, array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one product to the cart.',
            ]);
        }

        /** @var array<string, array{product_id: int, quantity: float, product_stock_layer_id: ?int, selling_unit_label: ?string, selling_unit_factor: ?float}> $merged */
        $merged = [];
        foreach ($items as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $quantity = (float) ($row['quantity'] ?? 0);
            $layerId = isset($row['product_stock_layer_id']) && $row['product_stock_layer_id'] !== ''
                ? (int) $row['product_stock_layer_id']
                : null;
            $sellingUnitLabel  = isset($row['selling_unit_label']) && $row['selling_unit_label'] !== '' ? trim((string) $row['selling_unit_label']) : null;
            $sellingUnitFactor = isset($row['selling_unit_factor']) && (float) $row['selling_unit_factor'] > 0 ? (float) $row['selling_unit_factor'] : null;
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }
            $key = $productId.':'.($layerId ?? 'fifo');
            if (! isset($merged[$key])) {
                $merged[$key] = [
                    'product_id' => $productId,
                    'quantity' => 0.0,
                    'product_stock_layer_id' => $layerId,
                    'selling_unit_label' => $sellingUnitLabel,
                    'selling_unit_factor' => $sellingUnitFactor,
                ];
            }
            $merged[$key]['quantity'] = round($merged[$key]['quantity'] + $quantity, 3);
            // carry the last-seen selling unit metadata
            $merged[$key]['selling_unit_label'] = $sellingUnitLabel;
            $merged[$key]['selling_unit_factor'] = $sellingUnitFactor;
        }

        if ($merged === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one product with quantity greater than zero.',
            ]);
        }

        $normalized = [];
        foreach ($merged as $row) {
            $product = Product::query()
                ->whereKey($row['product_id'])
                ->where('business_id', $business->id)
                ->where('is_active', true)
                ->where('is_bundle', false)
                ->first();

            if ($product === null) {
                throw ValidationException::withMessages([
                    'items' => 'One or more products are invalid or unavailable for POS.',
                ]);
            }

            $layerId = $row['product_stock_layer_id'];
            if ($layerId !== null) {
                $layerValid = ProductStockLayer::query()
                    ->whereKey($layerId)
                    ->where('product_id', $product->id)
                    ->where('business_id', $business->id)
                    ->where('quantity_remaining', '>', 0)
                    ->exists();
                if (! $layerValid) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more stock batches are invalid or out of stock for '.$product->name.'.',
                    ]);
                }
            }

            $normalized[] = [
                'product' => $product,
                'quantity' => round((float) $row['quantity'], 3),
                'product_stock_layer_id' => $layerId,
                'selling_unit_label' => $row['selling_unit_label'] ?? null,
                'selling_unit_factor' => $row['selling_unit_factor'] ?? null,
            ];
        }

        return $normalized;
    }

    private function normalizePaymentMethod(string $method): string
    {
        $method = strtolower(trim($method));
        if (! in_array($method, [Sale::PAYMENT_CASH, Sale::PAYMENT_CARD, Sale::PAYMENT_CREDIT], true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Choose a valid payment method.',
            ]);
        }

        return $method;
    }

    private function normalizeChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, [Sale::CHANNEL_RETAIL, Sale::CHANNEL_ONLINE], true)) {
            return Sale::CHANNEL_RETAIL;
        }

        return $channel;
    }
}
