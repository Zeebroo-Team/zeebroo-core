<?php

namespace Modules\Pos\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Sale;
use Modules\Pos\Models\SaleItem;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductStockLayer;
use Modules\Product\Services\ProductDiscountService;

class SaleService
{
    private const MONEY_TOLERANCE = 0.005;

    public function __construct(
        private readonly SaleStockConsumptionService $stockConsumption,
        private readonly SalePaymentSettlementService $payments,
        private readonly ProductDiscountService $discountService,
    ) {
    }

    /**
     * @return Collection<int, Sale>
     */
    public function listForBusiness(Business $business, ?string $search = null): Collection
    {
        $query = $business->sales()
            ->with(['user', 'creditAccount', 'branch'])
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
     * Paginated + filtered list for the web index page.
     *
     * @param  array{q?: string, status?: string, date_from?: string, date_to?: string, channel?: string}  $filters
     * @return LengthAwarePaginator<Sale>
     */
    public function indexForBusiness(Business $business, array $filters = []): LengthAwarePaginator
    {
        $query = $business->sales()
            ->with(['user', 'branch', 'customer'])
            ->withCount('items');

        $this->applyIndexFilters($query, $filters);

        return $query->orderByDesc('sold_at')->orderByDesc('id')->paginate(25);
    }

    /**
     * Aggregate stats for the same filter set shown on the index page.
     *
     * @param  array{q?: string, status?: string, date_from?: string, date_to?: string, channel?: string}  $filters
     * @return array{count: int, completed_count: int, completed_total: float, void_count: int}
     */
    public function indexSummary(Business $business, array $filters = []): array
    {
        $query = $business->sales();
        $this->applyIndexFilters($query, $filters);

        $rows = (clone $query)->selectRaw(
            "COUNT(*) as total_count,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_count,
             SUM(CASE WHEN status = ? THEN total ELSE 0 END) as completed_total,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as void_count",
            [Sale::STATUS_COMPLETED, Sale::STATUS_COMPLETED, Sale::STATUS_VOID]
        )->first();

        return [
            'count'           => (int)   ($rows->total_count     ?? 0),
            'completed_count' => (int)   ($rows->completed_count ?? 0),
            'completed_total' => (float) ($rows->completed_total ?? 0),
            'void_count'      => (int)   ($rows->void_count      ?? 0),
        ];
    }

    /** @param  array{q?: string, status?: string, date_from?: string, date_to?: string, channel?: string}  $filters */
    private function applyIndexFilters(\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation $query, array $filters): void
    {
        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($b) use ($like) {
                $b->where('sale_number', 'like', $like)
                  ->orWhere('notes', 'like', $like)
                  ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', $like));
            });
        }

        $status = $filters['status'] ?? 'all';
        if ($status === Sale::STATUS_COMPLETED) {
            $query->where('status', Sale::STATUS_COMPLETED);
        } elseif ($status === Sale::STATUS_VOID) {
            $query->where('status', Sale::STATUS_VOID);
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('sold_at', '>=', $filters['date_from']);
        }
        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('sold_at', '<=', $filters['date_to']);
        }

        $channel = $filters['channel'] ?? 'all';
        if ($channel === Sale::CHANNEL_RETAIL) {
            $query->where('channel', Sale::CHANNEL_RETAIL);
        } elseif ($channel === Sale::CHANNEL_ONLINE) {
            $query->where('channel', Sale::CHANNEL_ONLINE);
        }
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
        ?int $branchId = null,
    ): Sale {
        $lines = $this->normalizeCartItems($business, $items);
        $paymentMethod = $this->normalizePaymentMethod($paymentMethod);
        $channel = $this->normalizeChannel($channel);

        // Load active discounts for all products in the cart in one query
        $cartProductIds = array_map(fn ($l) => (int) $l['product']->id, $lines);
        $activeDiscounts = $this->discountService->activeForProducts($business, $cartProductIds);

        return DB::transaction(function () use ($business, $user, $lines, $paymentMethod, $creditAccountId, $amountPaid, $notes, $channel, $discountPercent, $amountTendered, $customerId, $deferSettlement, $branchId, $activeDiscounts) {
            $sale = $business->sales()->create([
                'branch_id' => $branchId,
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

                // Resolve the applicable discount for this line
                $productDiscounts = $activeDiscounts->where('product_id', $product->id);
                $suId = $line['product_selling_unit_id'] ?? null;
                $discount = $suId !== null
                    ? ($productDiscounts->firstWhere('product_selling_unit_id', $suId)
                        ?? $productDiscounts->firstWhere('product_selling_unit_id', null))
                    : $productDiscounts->firstWhere('product_selling_unit_id', null);

                foreach ($allocations as $allocation) {
                    $rawPrice = (float) $allocation['unit_sell_price'];

                    // Apply product discount server-side
                    $discountPerUnit = 0.0;
                    if ($discount !== null) {
                        $discountPerUnit = $discount->discount_type === 'percentage'
                            ? round($rawPrice * ((float) $discount->discount_value / 100), 2)
                            : min((float) $discount->discount_value, $rawPrice);
                    }
                    $finalSellPrice = round(max(0.0, $rawPrice - $discountPerUnit), 2);

                    $lineTotal = round($allocation['quantity'] * $finalSellPrice, 2);
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
                        'discount_amount' => $discountPerUnit,
                        'unit_sell_price' => $finalSellPrice,
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
     * @param  list<array{product_id: int, quantity: float|string, product_stock_layer_id?: int|null, product_selling_unit_id?: int|null, selling_unit_label?: ?string, selling_unit_factor?: float|null}>  $items
     * @return list<array{product: Product, quantity: float, product_stock_layer_id: ?int, product_selling_unit_id: ?int, selling_unit_label: ?string, selling_unit_factor: ?float}>
     */
    private function normalizeCartItems(Business $business, array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one product to the cart.',
            ]);
        }

        /** @var array<string, array{product_id: int, quantity: float, product_stock_layer_id: ?int, product_selling_unit_id: ?int, selling_unit_label: ?string, selling_unit_factor: ?float}> $merged */
        $merged = [];
        foreach ($items as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $quantity = (float) ($row['quantity'] ?? 0);
            $layerId = isset($row['product_stock_layer_id']) && $row['product_stock_layer_id'] !== ''
                ? (int) $row['product_stock_layer_id']
                : null;
            $suId = isset($row['product_selling_unit_id']) && (int) $row['product_selling_unit_id'] > 0
                ? (int) $row['product_selling_unit_id']
                : null;
            $sellingUnitLabel  = isset($row['selling_unit_label']) && $row['selling_unit_label'] !== '' ? trim((string) $row['selling_unit_label']) : null;
            $sellingUnitFactor = isset($row['selling_unit_factor']) && (float) $row['selling_unit_factor'] > 0 ? (float) $row['selling_unit_factor'] : null;
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }
            $key = $productId.':'.($layerId ?? 'fifo').':'.($suId ?? '0');
            if (! isset($merged[$key])) {
                $merged[$key] = [
                    'product_id' => $productId,
                    'quantity' => 0.0,
                    'product_stock_layer_id' => $layerId,
                    'product_selling_unit_id' => $suId,
                    'selling_unit_label' => $sellingUnitLabel,
                    'selling_unit_factor' => $sellingUnitFactor,
                ];
            }
            $merged[$key]['quantity'] = round($merged[$key]['quantity'] + $quantity, 3);
            // carry the last-seen selling unit metadata
            $merged[$key]['product_selling_unit_id'] = $suId;
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
                'product_selling_unit_id' => $row['product_selling_unit_id'] ?? null,
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
