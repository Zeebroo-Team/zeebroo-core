<?php

namespace Modules\Pos\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Account\Models\Property;
use Modules\Account\Services\BillService;
use Modules\Account\Services\LoanService;
use Modules\Account\Services\RentalService;
use Modules\Business\Models\Business;
use Modules\Pos\Models\PosNotification;
use Modules\Pos\Models\Sale;
use Modules\Product\Models\Product;
use Modules\Purchase\Models\Purchase;

class PosNotificationService
{
    private const LOW_STOCK_THRESHOLD = 5;

    private const SYNC_THROTTLE_SECONDS = 300;

    private const STOCK_SUMMARY_MAX_ITEMS = 300;

    private const STOCK_OUT_SUMMARY_REF_ID = 1;

    private const STOCK_LOW_SUMMARY_REF_ID = 2;

    public function __construct(
        private readonly BillService $bills,
        private readonly LoanService $loans,
        private readonly RentalService $rentals,
    ) {
    }

    public function syncForBusiness(Business $business): void
    {
        Cache::remember("pos_notif_synced_{$business->id}", self::SYNC_THROTTLE_SECONDS, function () use ($business) {
            $this->syncStockNotifications($business);
            $this->syncFinanceOverdueNotifications($business);
            $this->syncPurchaseOrderOverdueNotifications($business);

            return true;
        });
    }

    /** @return array{data: array<int, array<string, mixed>>, unread_count: int} */
    public function list(Business $business, ?string $status = null, int $limit = 50): array
    {
        $query = PosNotification::query()
            ->where('business_id', $business->id)
            ->notDismissed()
            ->orderByDesc('created_at');

        if ($status === 'unread') {
            $query->unread();
        } elseif ($status === 'read') {
            $query->readOnly();
        }

        $notifications = $query->limit($limit)->get();
        $unreadCount = PosNotification::query()->where('business_id', $business->id)->notDismissed()->unread()->count();

        return [
            'data' => $notifications->map(fn (PosNotification $n) => $this->format($n))->all(),
            'unread_count' => $unreadCount,
        ];
    }

    public function markRead(Business $business, int $id): bool
    {
        return (bool) PosNotification::query()
            ->where('business_id', $business->id)
            ->whereKey($id)
            ->update(['read_at' => now()]);
    }

    public function markUnread(Business $business, int $id): bool
    {
        return (bool) PosNotification::query()
            ->where('business_id', $business->id)
            ->whereKey($id)
            ->update(['read_at' => null]);
    }

    public function markAllRead(Business $business): int
    {
        return PosNotification::query()
            ->where('business_id', $business->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function delete(Business $business, int $id): bool
    {
        // Soft-dismiss rather than hard-delete: condition-based notifications
        // (stock, overdue bills, etc.) are re-synced periodically, and without
        // this the sync would just recreate the same alert a few minutes later.
        // upsert() checks dismissed_at and only resurfaces the notification if
        // its underlying condition has materially changed.
        return (bool) PosNotification::query()
            ->where('business_id', $business->id)
            ->whereKey($id)
            ->update(['dismissed_at' => now()]);
    }

    public function clearAll(Business $business): int
    {
        return PosNotification::query()
            ->where('business_id', $business->id)
            ->delete();
    }

    /** @return array<string, mixed> */
    public function getSettings(Business $business): array
    {
        return [
            'large_sale_threshold' => $business->getSetting('pos_notifications.large_sale_threshold'),
        ];
    }

    /** @param array<string, mixed> $data */
    public function updateSettings(Business $business, array $data): void
    {
        if (array_key_exists('large_sale_threshold', $data)) {
            $value = $data['large_sale_threshold'];
            $business->setSetting(
                'pos_notifications.large_sale_threshold',
                $value === null || $value === '' ? null : (float) $value,
            );
        }
    }

    // ---- Event-based notifications (fired once, at the point of action) ----

    public function notifyPurchaseOrderReceived(Purchase $purchase): void
    {
        $this->upsert(
            business: (int) $purchase->business_id,
            branchId: $purchase->branch_id !== null ? (int) $purchase->branch_id : null,
            type: PosNotification::TYPE_PURCHASE_ORDER_RECEIVED,
            referenceType: 'purchase',
            referenceId: (int) $purchase->id,
            title: 'Purchase order received',
            message: "Purchase order {$purchase->po_number} has been received.",
            payload: ['purchase_id' => $purchase->id, 'po_number' => $purchase->po_number],
        );
    }

    public function notifyLargeSale(Business $business, Sale $sale): void
    {
        $threshold = $business->getSetting('pos_notifications.large_sale_threshold');
        if ($threshold === null || $threshold === '' || (float) $sale->total < (float) $threshold) {
            return;
        }

        $this->upsert(
            business: (int) $business->id,
            branchId: $sale->branch_id !== null ? (int) $sale->branch_id : null,
            type: PosNotification::TYPE_SALE_LARGE,
            referenceType: 'sale',
            referenceId: (int) $sale->id,
            title: 'Large sale',
            message: "Sale {$sale->sale_number} totalled ".number_format((float) $sale->total, 2).'.',
            payload: ['sale_id' => $sale->id, 'sale_number' => $sale->sale_number, 'total' => (float) $sale->total],
        );
    }

    // ---- Sync (condition-based notifications, auto-clear when the condition resolves) ----

    private function syncStockNotifications(Business $business): void
    {
        // One-time cleanup of the old per-product notification rows this used to create.
        PosNotification::query()
            ->where('business_id', $business->id)
            ->whereIn('type', [PosNotification::TYPE_STOCK_OUT, PosNotification::TYPE_STOCK_LOW])
            ->where('reference_type', 'product')
            ->delete();

        $products = Product::query()
            ->where('business_id', $business->id)
            ->where('stock_quantity', '<=', self::LOW_STOCK_THRESHOLD)
            ->orderBy('stock_quantity')
            ->orderBy('name')
            ->get(['id', 'name', 'stock_quantity']);

        $out = $products->filter(fn (Product $p) => (float) $p->stock_quantity <= 0)->values();
        $low = $products->filter(fn (Product $p) => (float) $p->stock_quantity > 0)->values();

        $this->upsertStockSummary(
            $business,
            PosNotification::TYPE_STOCK_OUT,
            self::STOCK_OUT_SUMMARY_REF_ID,
            $out,
            'out of stock',
        );
        $this->upsertStockSummary(
            $business,
            PosNotification::TYPE_STOCK_LOW,
            self::STOCK_LOW_SUMMARY_REF_ID,
            $low,
            'low on stock',
        );
    }

    /** @param \Illuminate\Support\Collection<int, Product> $items */
    private function upsertStockSummary(Business $business, string $type, int $sentinelRefId, $items, string $label): void
    {
        if ($items->isEmpty()) {
            PosNotification::query()
                ->where('business_id', $business->id)
                ->where('type', $type)
                ->where('reference_type', 'stock_summary')
                ->delete();

            return;
        }

        $count = $items->count();
        $names = $items->take(3)->pluck('name')->implode(', ');
        $extra = $count > 3 ? ' and '.($count - 3).' more' : '';

        $this->upsert(
            business: (int) $business->id,
            branchId: null,
            type: $type,
            referenceType: 'stock_summary',
            referenceId: $sentinelRefId,
            title: $count === 1 ? "1 product {$label}" : "{$count} products {$label}",
            message: "{$names}{$extra}.",
            payload: [
                'total' => $count,
                'products' => $items->take(self::STOCK_SUMMARY_MAX_ITEMS)->map(fn (Product $p) => [
                    'product_id' => $p->id,
                    'product_name' => $p->name,
                    'stock_quantity' => (float) $p->stock_quantity,
                ])->values()->all(),
            ],
        );
    }

    private function syncFinanceOverdueNotifications(Business $business): void
    {
        $overdueBillIds = [];
        foreach ($business->bills()->with('ledgerTransactions')->get() as $bill) {
            if (! $this->bills->billHasOverduePayments($bill)) {
                continue;
            }
            $overdueBillIds[] = $bill->id;
            $this->upsert(
                business: (int) $business->id,
                branchId: $bill->branch_id !== null ? (int) $bill->branch_id : null,
                type: PosNotification::TYPE_BILL_OVERDUE,
                referenceType: 'bill',
                referenceId: (int) $bill->id,
                title: 'Bill overdue',
                message: "Bill \"{$bill->name}\" has an overdue payment.",
                payload: ['bill_id' => $bill->id, 'name' => $bill->name],
            );
        }
        $this->prune((int) $business->id, PosNotification::TYPE_BILL_OVERDUE, 'bill', $overdueBillIds);

        $overdueLoanIds = [];
        foreach ($business->loans()->with('ledgerTransactions')->get() as $loan) {
            if (! $this->loans->loanHasOverduePayments($loan)) {
                continue;
            }
            $overdueLoanIds[] = $loan->id;
            $this->upsert(
                business: (int) $business->id,
                branchId: null,
                type: PosNotification::TYPE_LOAN_OVERDUE,
                referenceType: 'loan',
                referenceId: (int) $loan->id,
                title: 'Loan overdue',
                message: "Loan \"{$loan->name}\" has an overdue installment.",
                payload: ['loan_id' => $loan->id, 'name' => $loan->name],
            );
        }
        $this->prune((int) $business->id, PosNotification::TYPE_LOAN_OVERDUE, 'loan', $overdueLoanIds);

        $overdueRentalIds = [];
        foreach ($business->rentals()->with('ledgerTransactions')->get() as $rental) {
            if (! $this->rentals->rentalHasOverduePayments($rental)) {
                continue;
            }
            $overdueRentalIds[] = $rental->id;
            $label = $rental->purpose ?: ($rental->property_type ?: 'Rental');
            $this->upsert(
                business: (int) $business->id,
                branchId: $rental->branch_id !== null ? (int) $rental->branch_id : null,
                type: PosNotification::TYPE_RENTAL_OVERDUE,
                referenceType: 'rental',
                referenceId: (int) $rental->id,
                title: 'Rental overdue',
                message: "Rental \"{$label}\" has an overdue payment.",
                payload: ['rental_id' => $rental->id, 'name' => $label],
            );
        }
        $this->prune((int) $business->id, PosNotification::TYPE_RENTAL_OVERDUE, 'rental', $overdueRentalIds);

        $expiredProperties = Property::query()
            ->where('business_id', $business->id)
            ->where('has_expiry', true)
            ->whereNotNull('expire_date')
            ->where('expire_date', '<', now()->startOfDay())
            ->get(['id', 'property_name']);

        foreach ($expiredProperties as $property) {
            $this->upsert(
                business: (int) $business->id,
                branchId: null,
                type: PosNotification::TYPE_PROPERTY_EXPIRED,
                referenceType: 'property',
                referenceId: (int) $property->id,
                title: 'Property expired',
                message: "Property \"{$property->property_name}\" has expired.",
                payload: ['property_id' => $property->id, 'name' => $property->property_name],
            );
        }
        $this->prune(
            (int) $business->id,
            PosNotification::TYPE_PROPERTY_EXPIRED,
            'property',
            $expiredProperties->pluck('id')->all(),
        );
    }

    private function syncPurchaseOrderOverdueNotifications(Business $business): void
    {
        $overdue = Purchase::query()
            ->where('business_id', $business->id)
            ->whereIn('status', [Purchase::STATUS_ORDERED, Purchase::STATUS_PARTIALLY_RECEIVED])
            ->whereNotNull('expected_delivery_date')
            ->where('expected_delivery_date', '<', now()->startOfDay())
            ->get(['id', 'branch_id', 'po_number']);

        foreach ($overdue as $purchase) {
            $this->upsert(
                business: (int) $business->id,
                branchId: $purchase->branch_id !== null ? (int) $purchase->branch_id : null,
                type: PosNotification::TYPE_PURCHASE_ORDER_OVERDUE,
                referenceType: 'purchase',
                referenceId: (int) $purchase->id,
                title: 'Purchase order overdue',
                message: "Purchase order {$purchase->po_number} is past its expected delivery date.",
                payload: ['purchase_id' => $purchase->id, 'po_number' => $purchase->po_number],
            );
        }
        $this->prune(
            (int) $business->id,
            PosNotification::TYPE_PURCHASE_ORDER_OVERDUE,
            'purchase',
            $overdue->pluck('id')->all(),
        );
    }

    // ---- Shared helpers ----

    /** @param array<string, mixed> $payload */
    private function upsert(
        int $business,
        ?int $branchId,
        string $type,
        string $referenceType,
        int $referenceId,
        string $title,
        string $message,
        array $payload,
    ): void {
        $existing = PosNotification::query()
            ->where('business_id', $business)
            ->where('type', $type)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->first();

        if ($existing && $existing->isDismissed()) {
            $unchanged = $existing->title === $title
                && $existing->message === $message
                && $existing->payload == $payload;

            if ($unchanged) {
                // User dismissed this and nothing about the underlying
                // condition has changed since — keep it hidden.
                return;
            }
        }

        PosNotification::query()->updateOrCreate(
            [
                'business_id' => $business,
                'type' => $type,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ],
            [
                'branch_id' => $branchId,
                'title' => $title,
                'message' => $message,
                'payload' => $payload,
                'dismissed_at' => null,
                'read_at' => $existing && $existing->isDismissed() ? null : $existing?->read_at,
            ],
        );
    }

    /** @param array<int, int> $currentIds */
    private function prune(int $businessId, string $type, string $referenceType, array $currentIds): void
    {
        PosNotification::query()
            ->where('business_id', $businessId)
            ->where('type', $type)
            ->where('reference_type', $referenceType)
            ->when(
                $currentIds !== [],
                fn ($q) => $q->whereNotIn('reference_id', $currentIds),
            )
            ->delete();
    }

    /** @return array<string, mixed> */
    private function format(PosNotification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'message' => $n->message,
            'reference_type' => $n->reference_type,
            'reference_id' => $n->reference_id,
            'payload' => $n->payload ?? [],
            'read' => $n->isRead(),
            'created_at' => $n->created_at?->toDateTimeString(),
        ];
    }
}
