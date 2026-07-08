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
use Modules\Service\Models\ServiceItem;
use Modules\Service\Services\ServiceRequestService;

class SaleService
{
    private const MONEY_TOLERANCE = 0.005;

    public function __construct(
        private readonly SaleStockConsumptionService $stockConsumption,
        private readonly SalePaymentSettlementService $payments,
        private readonly ProductDiscountService $discountService,
        private readonly PosSettingsService $posSettings,
        private readonly ServiceRequestService $serviceRequests,
    ) {
    }

    /**
     * @return Collection<int, Sale>
     */
    public function listForBusiness(Business $business, ?string $search = null, ?int $limit = null): Collection
    {
        $query = $business->sales()
            ->with(['user', 'creditAccount', 'branch', 'customer'])
            ->withCount('items');

        $term = trim((string) $search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('sale_number', 'like', $like)
                    ->orWhere('notes', 'like', $like);
            });
        }

        $query->orderByDesc('sold_at')->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function businessHasSales(Business $business): bool
    {
        return $business->sales()->exists();
    }

    /**
     * Daily revenue/count buckets for a chart, always based on completed sales.
     *
     * @param  array{channel?: string, date_from?: string, date_to?: string}  $filters
     * @return list<array{date: string, count: int, total: float}>
     */
    public function dailyChartForBusiness(Business $business, array $filters = []): array
    {
        $query = $business->sales()->where('status', Sale::STATUS_COMPLETED);

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

        return $query
            ->selectRaw('DATE(sold_at) as date, COUNT(*) as count, SUM(total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date'  => (string) $r->date,
                'count' => (int)    $r->count,
                'total' => round((float) $r->total, 2),
            ])
            ->all();
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
     * @param  list<array{product_id?: int, service_item_id?: int, item_type?: string, quantity: float|string}>  $items
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
        ?string $scheduledAt = null,
    ): Sale {
        $rawProductItems = array_values(array_filter(
            $items,
            fn ($i) => ($i['item_type'] ?? 'product') !== 'service' && !empty($i['product_id']),
        ));
        $rawServiceItems = array_values(array_filter(
            $items,
            fn ($i) => ($i['item_type'] ?? '') === 'service' && !empty($i['service_item_id']),
        ));

        $productLines = $rawProductItems !== [] ? $this->normalizeCartItems($business, $rawProductItems) : [];
        $serviceLines = $this->normalizeServiceCartItems($business, $rawServiceItems);

        if ($productLines === [] && $serviceLines === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one item to the cart.']);
        }

        $paymentMethod = $this->normalizePaymentMethod($paymentMethod);
        $channel       = $this->normalizeChannel($channel);

        // Auto-resolve deposit account from POS settings when none provided
        if ($creditAccountId === null && in_array($paymentMethod, [Sale::PAYMENT_CASH, Sale::PAYMENT_CARD], true)) {
            $creditAccountId = $this->posSettings->forBusiness($business)['default_deposit_account_id'];
        }

        // Load active discounts for all products in the cart in one query
        $cartProductIds = array_map(fn ($l) => (int) $l['product']->id, $productLines);
        $activeDiscounts = $this->discountService->activeForProducts($business, $cartProductIds);

        return DB::transaction(function () use ($business, $user, $productLines, $serviceLines, $paymentMethod, $creditAccountId, $amountPaid, $notes, $channel, $discountPercent, $amountTendered, $customerId, $deferSettlement, $branchId, $activeDiscounts, $scheduledAt) {
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

            foreach ($productLines as $line) {
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

            foreach ($serviceLines as $svcLine) {
                /** @var ServiceItem $service */
                $service  = $svcLine['service'];
                $qty      = (float) $svcLine['quantity'];
                $price    = round((float) $service->price, 2);
                $lineTotal = round($qty * $price, 2);
                $subtotal  = round($subtotal + $lineTotal, 2);

                SaleItem::query()->create([
                    'pos_sale_id'     => $sale->id,
                    'service_item_id' => $service->id,
                    'product_id'      => null,
                    'product_name'    => $service->name,
                    'sku'             => null,
                    'quantity'        => $qty,
                    'unit_cost'       => 0,
                    'discount_amount' => 0,
                    'unit_sell_price' => $price,
                    'line_total'      => $lineTotal,
                    'sort_order'      => $sortOrder++,
                ]);

                // One ServiceRequest per unit so each appointment is tracked individually
                $units = max(1, (int) round($qty));
                for ($u = 0; $u < $units; $u++) {
                    $this->serviceRequests->create($business, [
                        'service_item_id' => $service->id,
                        'customer_id'     => $customerId,
                        'title'           => $service->name,
                        'notes'           => $notes,
                        'scheduled_at'    => $scheduledAt,
                        'total_price'     => $price,
                    ]);
                }

                // Deduct bound products from stock (FIFO) scaled by service quantity
                $service->loadMissing('products');
                foreach ($service->products as $boundProduct) {
                    $deductQty = round((float) $boundProduct->pivot->qty * $qty, 3);
                    if ($deductQty <= 0) {
                        continue;
                    }
                    $this->stockConsumption->consumeFifo($boundProduct, $deductQty);
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

            return $sale->refresh()->load(['items.product', 'items.serviceItem.products', 'creditAccount', 'user']);
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
            $sale->load(['items.product', 'items.serviceItem.products']);

            foreach ($sale->items as $item) {
                // Product line — restore directly
                if ($item->product instanceof Product) {
                    $this->stockConsumption->restoreSaleItem(
                        $item->product_stock_layer_id !== null ? (int) $item->product_stock_layer_id : null,
                        (float) $item->quantity,
                        $item->product,
                    );
                    continue;
                }

                // Service line — restore bound products
                if ($item->serviceItem !== null) {
                    foreach ($item->serviceItem->products as $boundProduct) {
                        $restoreQty = round((float) $boundProduct->pivot->qty * (float) $item->quantity, 3);
                        if ($restoreQty <= 0) {
                            continue;
                        }
                        $this->stockConsumption->restoreSaleItem(null, $restoreQty, $boundProduct);
                    }
                }
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
                $layer = ProductStockLayer::query()
                    ->whereKey($layerId)
                    ->where('product_id', $product->id)
                    ->where('business_id', $business->id)
                    ->first(['id', 'quantity_remaining']);

                if ($layer === null) {
                    // Layer doesn't belong to this product/business — hard error
                    throw ValidationException::withMessages([
                        'items' => 'One or more stock batches are invalid for '.$product->name.'.',
                    ]);
                }

                if ((float) $layer->quantity_remaining <= 0) {
                    // Layer is depleted — fall back to FIFO so a stale client layer ID
                    // doesn't block checkout when other batches still have stock
                    $layerId = null;
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

    /**
     * @param  list<array{service_item_id: int, quantity: float|string}>  $items
     * @return list<array{service: ServiceItem, quantity: float}>
     */
    private function normalizeServiceCartItems(Business $business, array $items): array
    {
        $merged = [];
        foreach ($items as $row) {
            $svcId   = (int) ($row['service_item_id'] ?? 0);
            $qty     = (float) ($row['quantity'] ?? 0);
            if ($svcId <= 0 || $qty <= 0) {
                continue;
            }
            if (!isset($merged[$svcId])) {
                $merged[$svcId] = ['service_item_id' => $svcId, 'quantity' => 0.0];
            }
            $merged[$svcId]['quantity'] = round($merged[$svcId]['quantity'] + $qty, 3);
        }

        $normalized = [];
        foreach ($merged as $row) {
            $service = ServiceItem::query()
                ->whereKey($row['service_item_id'])
                ->where('business_id', $business->id)
                ->where('is_active', true)
                ->first();

            if ($service === null) {
                throw ValidationException::withMessages([
                    'items' => 'One or more services are invalid or unavailable for POS.',
                ]);
            }

            $normalized[] = ['service' => $service, 'quantity' => $row['quantity']];
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
