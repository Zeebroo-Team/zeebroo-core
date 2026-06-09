@php
    $completedSale = $completedSale ?? null;
    if (!$completedSale) {
        return;
    }

    $formatQty = static function (float $value): string {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    };

    $currencyLabel = filled($currency ?? null) ? ' '.$currency : '';
    $subtotal = (float) ($completedSale->subtotal ?? $completedSale->items->sum(fn ($i) => (float) $i->line_total));
    $discountAmount = (float) ($completedSale->discount_amount ?? 0);
    $discountPercent = (float) ($completedSale->discount_percent ?? 0);
    $discountPercentLabel = $discountPercent > 0
        ? ' ('.rtrim(rtrim(number_format($discountPercent, 2, '.', ''), '0'), '.').'%)'
        : '';

    $showAccountInfo = (bool) (($posSettings ?? [])['show_account_info'] ?? true);

    $saleCompletionData = [
        'saleId' => $completedSale->id,
        'businessName' => $business->name,
        'businessPhone' => $business->phone ?? '',
        'businessEmail' => $business->email ?? '',
        'businessAddress' => $business->address ?? '',
        'saleNumber' => $completedSale->sale_number,
        'soldAt' => $completedSale->sold_at?->format('M j, Y g:i A') ?? '',
        'soldAtIso' => $completedSale->sold_at?->toIso8601String() ?? '',
        'payment' => $completedSale->paymentMethodLabel(),
        'account' => $showAccountInfo ? ($completedSale->creditAccount?->deductOptionLabel() ?? '') : '',
        'channel' => $completedSale->channelLabel(),
        'currency' => trim($currencyLabel),
        'subtotal' => number_format($subtotal, 2, '.', ''),
        'discountPercent' => $discountPercent > 0 ? number_format($discountPercent, 2, '.', '') : '',
        'discountAmount' => $discountAmount > 0 ? number_format($discountAmount, 2, '.', '') : '',
        'total' => number_format((float) $completedSale->total, 2, '.', ''),
        'amountPaid' => number_format((float) $completedSale->amount_paid, 2, '.', ''),
        'amountTendered' => $completedSale->amount_tendered !== null
            ? number_format((float) $completedSale->amount_tendered, 2, '.', '')
            : '',
        'changeAmount' => $completedSale->change_amount !== null
            ? number_format((float) $completedSale->change_amount, 2, '.', '')
            : '',
        'notes' => $completedSale->notes ?? '',
        'items' => $completedSale->items->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->product_name,
            'sku' => $item->sku ?? '',
            'qty' => $formatQty((float) $item->quantity),
            'unit' => number_format((float) $item->unit_sell_price, 2, '.', ''),
            'line' => number_format((float) $item->line_total, 2, '.', ''),
            'discount' => (float) ($item->discount_amount ?? 0) > 0.001
                ? number_format((float) $item->discount_amount, 2, '.', '')
                : '',
            'original_unit' => (float) ($item->discount_amount ?? 0) > 0.001
                ? number_format((float) $item->unit_sell_price + (float) $item->discount_amount, 2, '.', '')
                : '',
            'discount_line' => (float) ($item->discount_amount ?? 0) > 0.001
                ? number_format((float) $item->quantity * (float) $item->discount_amount, 2, '.', '')
                : '',
        ])->values()->all(),
        'productDiscountTotal' => (function () use ($completedSale) {
            $total = $completedSale->items->sum(fn ($i) => round((float) $i->quantity * (float) ($i->discount_amount ?? 0), 2));
            return $total > 0.001 ? number_format($total, 2, '.', '') : '';
        })(),
    ];
@endphp

<div id="pos-sale-completed-modal" class="pos-sale-completed-modal is-open" role="dialog" aria-modal="true" aria-labelledby="pos-completed-title" aria-hidden="false"
     data-pos-completion="{{ e(json_encode($saleCompletionData)) }}">
    <div class="pos-sale-completed-modal__backdrop" data-pos-completed-close tabindex="-1" aria-label="Close"></div>

    <div class="pos-sale-completed-modal__panel">
        {{-- ── Header ─────────────────────────────────── --}}
        <div class="pos-sale-completed-modal__header">
            <div class="pos-sale-completed-modal__icon">
                <i class="fa fa-check-circle" aria-hidden="true"></i>
            </div>
            <div class="pos-sale-completed-modal__title-section">
                <h2 id="pos-completed-title">Sale Completed!</h2>
                <p class="pos-sale-completed-modal__subtitle">{{ $completedSale->sale_number }}</p>
            </div>
            <button type="button" class="pos-sale-completed-modal__close" data-pos-completed-close aria-label="Close">&times;</button>
        </div>

        {{-- ── Body with tabs ─────────────────────────────────── --}}
        <div class="pos-sale-completed-modal__body">
            {{-- Tab buttons --}}
            <div class="pos-sale-completed-modal__tabs">
                <button class="pos-sale-completed-modal__tab-btn is-active" data-pos-tab="receipt" aria-selected="true">
                    <i class="fa fa-receipt" aria-hidden="true"></i> Receipt
                </button>
                <button class="pos-sale-completed-modal__tab-btn" data-pos-tab="details" aria-selected="false">
                    <i class="fa fa-info-circle" aria-hidden="true"></i> Details
                </button>
            </div>

            {{-- Tab: Receipt Preview --}}
            <div class="pos-sale-completed-modal__tab-content is-active" data-pos-tab-content="receipt">
                <div class="pos-thermal-receipt-preview">
                    <div class="pos-thermal-receipt-header">
                        <div class="pos-thermal-receipt-business">{{ $business->name }}</div>
                        @if(filled($business->address))
                            <div class="pos-thermal-receipt-meta">{{ $business->address }}</div>
                        @endif
                        @if(filled($business->phone))
                            <div class="pos-thermal-receipt-meta">{{ $business->phone }}</div>
                        @endif
                    </div>

                    <div class="pos-thermal-receipt-divider"></div>

                    <div class="pos-thermal-receipt-info">
                        <div class="pos-thermal-receipt-row">
                            <span>Receipt #</span>
                            <strong>{{ $completedSale->sale_number }}</strong>
                        </div>
                        <div class="pos-thermal-receipt-row">
                            <span>Date & Time</span>
                            <strong>{{ $completedSale->sold_at?->format('M j, Y g:i A') ?? '-' }}</strong>
                        </div>
                        <div class="pos-thermal-receipt-row">
                            <span>Payment</span>
                            <strong>{{ $completedSale->paymentMethodLabel() }}</strong>
                        </div>
                        @if($showAccountInfo && $completedSale->creditAccount)
                            <div class="pos-thermal-receipt-row">
                                <span>Account</span>
                                <strong>{{ $completedSale->creditAccount->deductOptionLabel() }}</strong>
                            </div>
                        @endif
                    </div>

                    <div class="pos-thermal-receipt-divider"></div>

                    {{-- Items table --}}
                    <table class="pos-thermal-receipt-items">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($completedSale->items as $item)
                                @php $itemDisc = (float) ($item->discount_amount ?? 0); @endphp
                                <tr>
                                    <td>
                                        <div class="pos-thermal-item-name">{{ $item->product_name }}</div>
                                        @if(filled($item->sku))
                                            <div class="pos-thermal-item-sku">{{ $item->sku }}</div>
                                        @endif
                                        @if($item->selling_unit_label)
                                            <div class="pos-thermal-item-sku">per {{ $item->selling_unit_label }}</div>
                                        @endif
                                    </td>
                                    <td class="pos-thermal-col-center">{{ $formatQty((float) $item->quantity) }}</td>
                                    <td class="pos-thermal-col-right">
                                        @if($itemDisc > 0.001)
                                            <del style="font-size:9px;color:#999;">{{ number_format((float) $item->unit_sell_price + $itemDisc, 2) }}</del>
                                            <div>{{ number_format((float) $item->unit_sell_price, 2) }}{{ $currencyLabel }}</div>
                                        @else
                                            {{ number_format((float) $item->unit_sell_price, 2) }}{{ $currencyLabel }}
                                        @endif
                                    </td>
                                    <td class="pos-thermal-col-right"><strong>{{ number_format((float) $item->line_total, 2) }}{{ $currencyLabel }}</strong></td>
                                </tr>
                                @if($itemDisc > 0.001)
                                    <tr class="pos-thermal-discount-row">
                                        <td colspan="3" style="font-size:9px;color:#d97706;padding:0 2px 4px;line-height:1.3;">
                                            &nbsp;&nbsp;↳ Discount −{{ number_format($itemDisc, 2) }}{{ $currencyLabel }} / unit
                                        </td>
                                        <td class="pos-thermal-col-right" style="font-size:9px;color:#d97706;padding:0 2px 4px;">
                                            −{{ number_format((float) $item->quantity * $itemDisc, 2) }}{{ $currencyLabel }}
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>

                    <div class="pos-thermal-receipt-divider"></div>

                    {{-- Totals --}}
                    @php
                        $productDiscTotal = $completedSale->items->sum(fn ($i) => round((float) $i->quantity * (float) ($i->discount_amount ?? 0), 2));
                        $hasProductDisc   = $productDiscTotal > 0.001;
                    @endphp
                    <div class="pos-thermal-receipt-totals">
                        @if($hasProductDisc || $discountAmount > 0.001 || $subtotal > (float) $completedSale->total + 0.001)
                            <div class="pos-thermal-total-row">
                                <span>Subtotal</span>
                                <span>{{ number_format($subtotal, 2) }}{{ $currencyLabel }}</span>
                            </div>
                            @if($hasProductDisc)
                                <div class="pos-thermal-total-row" style="color:#d97706;">
                                    <span>Product discounts</span>
                                    <span>−{{ number_format($productDiscTotal, 2) }}{{ $currencyLabel }}</span>
                                </div>
                            @endif
                            @if($discountAmount > 0.001)
                                <div class="pos-thermal-total-row">
                                    <span>Discount{{ $discountPercentLabel }}</span>
                                    <span>−{{ number_format($discountAmount, 2) }}{{ $currencyLabel }}</span>
                                </div>
                            @endif
                        @endif

                        <div class="pos-thermal-total-final">
                            <span>Total</span>
                            <strong>{{ number_format((float) $completedSale->total, 2) }}{{ $currencyLabel }}</strong>
                        </div>

                        @if($completedSale->payment_method === \Modules\Pos\Models\Sale::PAYMENT_CASH && $completedSale->amount_tendered !== null)
                            <div class="pos-thermal-total-row">
                                <span>Cash Received</span>
                                <span>{{ number_format((float) $completedSale->amount_tendered, 2) }}{{ $currencyLabel }}</span>
                            </div>
                            <div class="pos-thermal-total-row">
                                <span>Change</span>
                                <span>{{ number_format((float) ($completedSale->change_amount ?? 0), 2) }}{{ $currencyLabel }}</span>
                            </div>
                        @endif

                        @php $totalSaved = $productDiscTotal + $discountAmount; @endphp
                        @if($totalSaved > 0.001)
                            <div class="pos-thermal-savings-banner">
                                <i class="fa fa-tag"></i>
                                You saved {{ number_format($totalSaved, 2) }}{{ $currencyLabel }} on this purchase!
                            </div>
                        @endif
                    </div>

                    @if(filled($completedSale->notes))
                        <div class="pos-thermal-receipt-divider"></div>
                        <div class="pos-thermal-receipt-notes">
                            <strong>Notes:</strong> {{ $completedSale->notes }}
                        </div>
                    @endif

                    <div class="pos-thermal-receipt-divider"></div>

                    <div class="pos-thermal-receipt-footer">
                        <div>Thank you for your purchase!</div>
                        <div class="pos-thermal-footer-meta">{{ $completedSale->channelLabel() }}</div>
                    </div>
                </div>
            </div>

            {{-- Tab: Details --}}
            <div class="pos-sale-completed-modal__tab-content" data-pos-tab-content="details">
                <div class="pos-sale-completed-modal__details">
                    <div class="pos-details-section">
                        <h3>Transaction Details</h3>
                        <div class="pos-details-row">
                            <label>Sale ID</label>
                            <span>{{ $completedSale->id }}</span>
                        </div>
                        <div class="pos-details-row">
                            <label>Sale Number</label>
                            <span>{{ $completedSale->sale_number }}</span>
                        </div>
                        <div class="pos-details-row">
                            <label>Date & Time</label>
                            <span>{{ $completedSale->sold_at?->format('M j, Y g:i A') ?? '-' }}</span>
                        </div>
                        <div class="pos-details-row">
                            <label>Channel</label>
                            <span>{{ $completedSale->channelLabel() }}</span>
                        </div>
                    </div>

                    <div class="pos-details-section">
                        <h3>Payment Information</h3>
                        <div class="pos-details-row">
                            <label>Payment Method</label>
                            <span>{{ $completedSale->paymentMethodLabel() }}</span>
                        </div>
                        @if($showAccountInfo && $completedSale->creditAccount)
                            <div class="pos-details-row">
                                <label>Deposit Account</label>
                                <span>{{ $completedSale->creditAccount->deductOptionLabel() }}</span>
                            </div>
                        @endif
                        <div class="pos-details-row">
                            <label>Amount Paid</label>
                            <span><strong>{{ number_format((float) $completedSale->amount_paid, 2) }}{{ $currencyLabel }}</strong></span>
                        </div>
                    </div>

                    @if($completedSale->payment_method === \Modules\Pos\Models\Sale::PAYMENT_CASH && $completedSale->amount_tendered !== null)
                        <div class="pos-details-section">
                            <h3>Cash Handling</h3>
                            <div class="pos-details-row">
                                <label>Cash Received</label>
                                <span>{{ number_format((float) $completedSale->amount_tendered, 2) }}{{ $currencyLabel }}</span>
                            </div>
                            <div class="pos-details-row">
                                <label>Change</label>
                                <span>{{ number_format((float) ($completedSale->change_amount ?? 0), 2) }}{{ $currencyLabel }}</span>
                            </div>
                        </div>
                    @endif

                    <div class="pos-details-section">
                        <h3>Amounts</h3>
                        @if($discountAmount > 0.001)
                            <div class="pos-details-row">
                                <label>Discount{{ $discountPercentLabel }}</label>
                                <span>−{{ number_format($discountAmount, 2) }}{{ $currencyLabel }}</span>
                            </div>
                        @endif
                        <div class="pos-details-row">
                            <label>Total</label>
                            <span><strong style="color:var(--primary);">{{ number_format((float) $completedSale->total, 2) }}{{ $currencyLabel }}</strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Footer with actions ─────────────────────────────────── --}}
        <div class="pos-sale-completed-modal__footer">
            <button type="button" class="pos-sale-completed-modal__btn pos-sale-completed-modal__btn--primary" id="pos-completed-btn-print-thermal">
                <i class="fa fa-print" aria-hidden="true"></i> Print Receipt
            </button>
            <a href="{{ route('pos.sales.show', $completedSale) }}" class="pos-sale-completed-modal__btn">
                <i class="fa fa-file-text" aria-hidden="true"></i> View Details
            </a>
            <button type="button" class="pos-sale-completed-modal__btn" id="pos-completed-btn-new-sale" data-pos-completed-close>
                <i class="fa fa-plus" aria-hidden="true"></i> New Sale
            </button>
        </div>
    </div>
</div>

{{-- ── Styles ─────────────────────────────────── --}}
<style>
.pos-sale-completed-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: grid;
    place-items: center;
    z-index: 10000;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
}

.pos-sale-completed-modal.is-open {
    opacity: 1;
    pointer-events: auto;
}

.pos-sale-completed-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(2px);
}

.pos-sale-completed-modal__panel {
    position: relative;
    width: 90%;
    max-width: 600px;
    height: 90vh;
    max-height: 720px;
    background: var(--card);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: modalSlideUp 0.3s ease;
}

@keyframes modalSlideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.pos-sale-completed-modal__header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 10%, transparent), color-mix(in srgb, var(--primary) 5%, transparent));
    position: relative;
}

.pos-sale-completed-modal__icon {
    font-size: 32px;
    color: #10b981;
    animation: iconPulse 0.6s ease;
}

@keyframes iconPulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}

.pos-sale-completed-modal__title-section {
    flex: 1;
    min-width: 0;
}

.pos-sale-completed-modal__title-section h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    color: var(--text);
    letter-spacing: -0.01em;
}

.pos-sale-completed-modal__subtitle {
    margin: 4px 0 0;
    font-size: 12px;
    color: var(--muted);
}

.pos-sale-completed-modal__close {
    width: 32px;
    height: 32px;
    padding: 0;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: transparent;
    color: var(--text);
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.pos-sale-completed-modal__close:hover {
    border-color: color-mix(in srgb, var(--primary) 40%, var(--border));
    background: color-mix(in srgb, var(--primary) 8%, transparent);
}

.pos-sale-completed-modal__body {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.pos-sale-completed-modal__tabs {
    display: flex;
    gap: 8px;
    padding: 12px 20px;
    border-bottom: 1px solid var(--border);
    background: color-mix(in srgb, var(--card) 94%, transparent);
    flex-shrink: 0;
}

.pos-sale-completed-modal__tab-btn {
    padding: 8px 14px;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid var(--border);
    border-bottom: 2px solid transparent;
    border-radius: 8px 8px 0 0;
    background: transparent;
    color: var(--muted);
    cursor: pointer;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.pos-sale-completed-modal__tab-btn:hover {
    border-color: color-mix(in srgb, var(--primary) 30%, var(--border));
    color: var(--text);
}

.pos-sale-completed-modal__tab-btn.is-active {
    border-color: var(--primary);
    border-bottom-color: var(--primary);
    background: color-mix(in srgb, var(--primary) 8%, transparent);
    color: var(--text);
}

.pos-sale-completed-modal__tab-content {
    display: none;
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 16px 20px;
}

.pos-sale-completed-modal__tab-content.is-active {
    display: block;
    overflow-y: auto;
}

.pos-sale-completed-modal__details {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.pos-details-section {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.pos-details-section h3 {
    margin: 0;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--muted);
}

.pos-details-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    padding: 8px 0;
    border-bottom: 1px solid color-mix(in srgb, var(--border) 50%, transparent);
    font-size: 13px;
}

.pos-details-row label {
    color: var(--muted);
    font-weight: 500;
}

.pos-details-row span {
    color: var(--text);
    font-weight: 600;
    text-align: right;
    max-width: 60%;
    word-break: break-word;
}

/* Thermal Receipt Styles */
.pos-thermal-receipt-preview {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.5;
    background: #fff;
    color: #000;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    max-width: 100%;
}

.pos-thermal-receipt-header {
    text-align: center;
    margin-bottom: 12px;
    border-bottom: 1px solid #000;
    padding-bottom: 8px;
}

.pos-thermal-receipt-business {
    font-weight: bold;
    font-size: 13px;
    margin-bottom: 2px;
}

.pos-thermal-receipt-meta {
    font-size: 10px;
    margin: 1px 0;
}

.pos-thermal-receipt-divider {
    height: 1px;
    background: #000;
    margin: 8px 0;
}

.pos-thermal-receipt-info {
    margin-bottom: 8px;
    font-size: 11px;
}

.pos-thermal-receipt-row {
    display: flex;
    justify-content: space-between;
    margin: 2px 0;
}

.pos-thermal-receipt-row span {
    flex: 1;
}

.pos-thermal-receipt-row strong {
    text-align: right;
    flex: 1;
    font-weight: bold;
}

.pos-thermal-receipt-items {
    width: 100%;
    margin: 8px 0;
    border-collapse: collapse;
    font-size: 10px;
}

.pos-thermal-receipt-items thead {
    border-bottom: 1px solid #000;
}

.pos-thermal-receipt-items th {
    padding: 2px 2px 4px;
    text-align: left;
    font-weight: bold;
    font-size: 9px;
}

.pos-thermal-receipt-items td {
    padding: 3px 2px;
    border-bottom: 1px dashed #ccc;
}

.pos-thermal-receipt-items tbody tr:last-child td {
    border-bottom: none;
}

.pos-thermal-col-center {
    text-align: center;
}

.pos-thermal-col-right {
    text-align: right;
}

.pos-thermal-item-name {
    font-weight: bold;
    font-size: 11px;
}

.pos-thermal-item-sku {
    font-size: 9px;
    color: #666;
    margin-top: 1px;
}

.pos-thermal-receipt-totals {
    margin: 8px 0;
    border-top: 1px solid #000;
    padding-top: 6px;
}

.pos-thermal-total-row {
    display: flex;
    justify-content: space-between;
    margin: 2px 0;
    font-size: 11px;
}

.pos-thermal-total-final {
    display: flex;
    justify-content: space-between;
    margin: 4px 0 2px;
    padding-top: 4px;
    border-top: 1px solid #000;
    font-weight: bold;
    font-size: 13px;
}

.pos-thermal-receipt-notes {
    font-size: 10px;
    text-align: center;
    margin: 4px 0;
}

.pos-thermal-receipt-footer {
    text-align: center;
    font-size: 10px;
    margin-top: 8px;
}

.pos-thermal-footer-meta {
    font-size: 9px;
    color: #666;
    margin-top: 2px;
}

.pos-thermal-savings-banner {
    margin-top: 6px;
    padding: 5px 8px;
    background: #fff7ed;
    border: 1px dashed #f59e0b;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    color: #b45309;
    text-align: center;
}

.pos-sale-completed-modal__footer {
    display: flex;
    gap: 8px;
    padding: 16px 20px;
    border-top: 1px solid var(--border);
    background: color-mix(in srgb, var(--card) 94%, transparent);
    flex-shrink: 0;
}

.pos-sale-completed-modal__btn {
    flex: 1;
    padding: 10px 12px;
    font-size: 13px;
    font-weight: 700;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: color-mix(in srgb, var(--card) 90%, transparent);
    color: var(--text);
    cursor: pointer;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none;
    white-space: nowrap;
}

.pos-sale-completed-modal__btn:hover {
    border-color: color-mix(in srgb, var(--primary) 40%, var(--border));
    background: color-mix(in srgb, var(--primary) 8%, transparent);
}

.pos-sale-completed-modal__btn--primary {
    background: color-mix(in srgb, var(--primary) 18%, transparent);
    border-color: color-mix(in srgb, var(--primary) 50%, var(--border));
    color: var(--primary);
    font-weight: 800;
}

.pos-sale-completed-modal__btn--primary:hover {
    background: color-mix(in srgb, var(--primary) 25%, transparent);
    border-color: var(--primary);
}

/* Print Styles */
@media print {
    .pos-sale-completed-modal {
        all: unset;
        display: block !important;
    }

    .pos-sale-completed-modal__backdrop,
    .pos-sale-completed-modal__header,
    .pos-sale-completed-modal__tabs,
    .pos-sale-completed-modal__footer,
    .pos-details-section,
    .pos-sale-completed-modal__tab-btn {
        display: none !important;
    }

    .pos-thermal-receipt-preview {
        max-width: 80mm;
        width: 80mm;
        margin: 0;
        padding: 0;
        background: white;
        border: none;
        box-shadow: none;
    }
}

/* Responsive */
@media (max-width: 640px) {
    .pos-sale-completed-modal__panel {
        width: 95%;
        max-height: 95vh;
        border-radius: 12px;
    }

    .pos-sale-completed-modal__header {
        padding: 16px;
    }

    .pos-sale-completed-modal__footer {
        flex-direction: column;
    }

    .pos-sale-completed-modal__btn {
        width: 100%;
    }

    .pos-thermal-receipt-preview {
        font-size: 11px;
    }
}
</style>

{{-- ── JavaScript ─────────────────────────────────── --}}
@once
<script>
(function () {
    const modal = document.getElementById('pos-sale-completed-modal');
    if (!modal) return;

    let completionData = {};
    try {
        completionData = JSON.parse(modal.getAttribute('data-pos-completion') || '{}');
    } catch (e) {
        completionData = {};
    }

    // Tab switching
    function setupTabs() {
        const tabBtns = modal.querySelectorAll('[data-pos-tab]');
        const tabContents = modal.querySelectorAll('[data-pos-tab-content]');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                const tabName = this.getAttribute('data-pos-tab');

                // Update active button
                tabBtns.forEach(b => {
                    b.classList.toggle('is-active', b === this);
                    b.setAttribute('aria-selected', b === this ? 'true' : 'false');
                });

                // Update active content
                tabContents.forEach(content => {
                    const isActive = content.getAttribute('data-pos-tab-content') === tabName;
                    content.classList.toggle('is-active', isActive);
                });
            });
        });
    }

    // Modal control
    function setOpen(open) {
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.documentElement.classList.toggle('pos-sale-completed-modal-open', open);
        if (!open) {
            // After the opacity transition ends, set display:none so the
            // fixed+backdrop-filter element is fully out of the render tree
            // and cannot intercept pointer events on any browser.
            setTimeout(function () {
                if (!modal.classList.contains('is-open')) {
                    modal.style.display = 'none';
                }
            }, 250);
            // Reset the cart so the POS is ready for a new sale
            document.dispatchEvent(new CustomEvent('pos-clear-cart-and-reset', {
                detail: { completedSaleId: completionData.saleId }
            }));
        } else {
            modal.style.display = '';
        }
    }

    modal.querySelectorAll('[data-pos-completed-close]').forEach(el => {
        el.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            setOpen(false);
        }
    });

    // Thermal printer layout
    function buildThermalPrintHtml() {
        const cur = completionData.currency ? ' ' + completionData.currency : '';
        const pageWidth = 80; // mm

        let itemRows = '';
        (completionData.items || []).forEach(item => {
            const name = escHtml(item.name);
            const sku = item.sku ? '<div style="font-size:8px;color:#666;margin:1px 0 0;">' + escHtml(item.sku) + '</div>' : '';
            // Show strikethrough original price if discounted
            const unitPriceHtml = item.original_unit
                ? '<del style="font-size:9px;color:#999;">' + escHtml(item.original_unit) + escHtml(cur) + '</del> ' + escHtml(item.unit) + escHtml(cur)
                : escHtml(item.unit) + escHtml(cur);
            itemRows += '<tr>'
                + '<td style="padding:3px 0;"><strong>' + name + '</strong>' + sku + '</td>'
                + '<td style="padding:3px 0;text-align:center;width:60px;">' + escHtml(item.qty) + '</td>'
                + '<td style="padding:3px 0;text-align:right;width:80px;">' + unitPriceHtml + '</td>'
                + '<td style="padding:3px 0;text-align:right;width:70px;font-weight:600;">' + escHtml(item.line) + escHtml(cur) + '</td>'
                + '</tr>';
            // Discount sub-row
            if (item.discount) {
                itemRows += '<tr>'
                    + '<td colspan="3" style="padding:0 0 4px;font-size:9px;color:#d97706;line-height:1.3;">'
                    + '&nbsp;&nbsp;&#x21b3; Discount &minus;' + escHtml(item.discount) + escHtml(cur) + ' / unit'
                    + '</td>'
                    + '<td style="padding:0 0 4px;text-align:right;font-size:9px;color:#d97706;">&minus;' + escHtml(item.discount_line) + escHtml(cur) + '</td>'
                    + '</tr>';
            }
        });

        const hasProductDisc = completionData.productDiscountTotal && parseFloat(completionData.productDiscountTotal) > 0;
        let totalsHtml = '';
        if (hasProductDisc || completionData.discountAmount) {
            totalsHtml += '<div style="display:flex;justify-content:space-between;margin:2px 0;font-size:11px;"><span>Subtotal</span><span>' + escHtml(completionData.subtotal) + escHtml(cur) + '</span></div>';
            if (hasProductDisc) {
                totalsHtml += '<div style="display:flex;justify-content:space-between;margin:2px 0;font-size:11px;color:#d97706;"><span>Product discounts</span><span>&minus;' + escHtml(completionData.productDiscountTotal) + escHtml(cur) + '</span></div>';
            }
            if (completionData.discountAmount) {
                const discLabel = completionData.discountPercent ? ' (' + escHtml(completionData.discountPercent) + '%)' : '';
                totalsHtml += '<div style="display:flex;justify-content:space-between;margin:2px 0;font-size:11px;"><span>Discount' + discLabel + '</span><span>&minus;' + escHtml(completionData.discountAmount) + escHtml(cur) + '</span></div>';
            }
        }
        totalsHtml += '<div style="display:flex;justify-content:space-between;margin:6px 0 0;padding-top:4px;border-top:2px solid #000;font-size:13px;font-weight:bold;"><span>Total</span><span>' + escHtml(completionData.total) + escHtml(cur) + '</span></div>';

        if (completionData.amountTendered) {
            totalsHtml += '<div style="display:flex;justify-content:space-between;margin:2px 0;font-size:11px;"><span>Cash Received</span><span>' + escHtml(completionData.amountTendered) + escHtml(cur) + '</span></div>';
            totalsHtml += '<div style="display:flex;justify-content:space-between;margin:2px 0;font-size:11px;"><span>Change</span><span>' + escHtml(completionData.changeAmount) + escHtml(cur) + '</span></div>';
        }

        // "You saved" banner
        const totalSaved = (parseFloat(completionData.productDiscountTotal || '0') + parseFloat(completionData.discountAmount || '0'));
        if (totalSaved > 0) {
            const savedFormatted = totalSaved.toFixed(2);
            totalsHtml += '<div style="margin-top:8px;padding:5px 8px;border:1px dashed #f59e0b;border-radius:4px;font-size:10px;font-weight:700;color:#b45309;text-align:center;">'
                + '&#127991; You saved ' + savedFormatted + escHtml(cur) + ' on this purchase!'
                + '</div>';
        }

        const businessInfo = (completionData.businessAddress ? completionData.businessAddress + '<br>' : '')
            + (completionData.businessPhone ? completionData.businessPhone + '<br>' : '')
            + (completionData.businessEmail ? completionData.businessEmail : '');

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' + escHtml(completionData.saleNumber || 'Receipt') + '</title>'
            + '<style>'
            + 'body { font-family: "Courier New", monospace; margin: 0; padding: 12mm; width: ' + pageWidth + 'mm; max-width: ' + pageWidth + 'mm; font-size: 12px; line-height: 1.4; color: #000; background: #fff; }'
            + 'h1 { margin: 0 0 2px; font-size: 13px; font-weight: bold; text-align: center; }'
            + '.meta { margin: 0 0 8px; font-size: 10px; text-align: center; line-height: 1.3; }'
            + 'hr { border: none; border-top: 1px solid #000; margin: 6px 0; }'
            + 'table { width: 100%; border-collapse: collapse; margin: 6px 0; font-size: 11px; }'
            + 'th { font-size: 9px; font-weight: bold; text-align: left; padding: 2px 0 4px; border-bottom: 1px solid #000; }'
            + 'td { padding: 3px 0; }'
            + '.totals { margin: 8px 0; }'
            + '.footer { text-align: center; font-size: 10px; margin-top: 8px; }'
            + '.timestamp { font-size: 9px; color: #666; }'
            + '@media print { body { margin: 0; padding: 0; width: 80mm; max-width: 80mm; } }'
            + '</style></head><body>'
            + '<h1>' + escHtml(completionData.businessName) + '</h1>'
            + '<div class="meta">'
            + (businessInfo ? '<div>' + businessInfo + '</div>' : '')
            + '<div style="margin-top:4px;">' + escHtml(completionData.saleNumber) + ' · ' + escHtml(completionData.soldAt) + '</div>'
            + '<div>' + escHtml(completionData.payment) + (completionData.account ? ' · ' + escHtml(completionData.account) : '') + '</div>'
            + '</div>'
            + '<hr>'
            + '<table>'
            + '<thead><tr><th>Item</th><th style="text-align:center;width:60px;">Qty</th><th style="text-align:right;width:70px;">Price</th><th style="text-align:right;width:70px;">Amount</th></tr></thead>'
            + '<tbody>' + itemRows + '</tbody>'
            + '</table>'
            + '<hr>'
            + '<div class="totals">' + totalsHtml + '</div>'
            + '<hr>'
            + (completionData.notes ? '<div style="font-size:10px;margin:6px 0;"><strong>Notes:</strong> ' + escHtml(completionData.notes) + '</div><hr>' : '')
            + '<div class="footer">'
            + '<div>Thank you for your purchase!</div>'
            + '<div style="margin-top:4px;">' + escHtml(completionData.channel) + '</div>'
            + '<div class="timestamp">Printed: ' + new Date().toLocaleString() + '</div>'
            + '</div>'
            + '</body></html>';
    }

    function escHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function openPrintWindow() {
        const w = window.open('', '_blank', 'width=400,height=600');
        if (!w) {
            alert('Please allow pop-ups to print the receipt.');
            return;
        }
        w.document.open();
        w.document.write(buildThermalPrintHtml());
        w.document.close();
        w.focus();

        const handlePrintComplete = () => {
            try { w.close(); } catch (e) {}
            
            // Hide modal and reset POS after print completes
            setOpen(false);
            document.documentElement.classList.remove('pos-sale-completed-modal-open');
            
            // Emit event for POS to clear cart and reset
            const event = new CustomEvent('pos-clear-cart-and-reset', {
                detail: { completedSaleId: completionData.saleId }
            });
            document.dispatchEvent(event);
        };

        if ('onafterprint' in w) {
            w.addEventListener('afterprint', handlePrintComplete);
        } else {
            setTimeout(handlePrintComplete, 1000);
        }

        setTimeout(() => {
            w.print();
        }, 150);
    }

    // Event listeners
    document.getElementById('pos-completed-btn-print-thermal')?.addEventListener('click', openPrintWindow);

    setupTabs();
    document.documentElement.classList.add('pos-sale-completed-modal-open');
})();
</script>
@endonce
