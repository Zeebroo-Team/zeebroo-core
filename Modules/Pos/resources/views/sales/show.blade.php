@extends('theme::layouts.app', ['title' => $sale->sale_number, 'heading' => 'Sale '.$sale->sale_number])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.pos-receipt-status{display:inline-block;font-size:11px;font-weight:700;padding:4px 10px;border-radius:999px;border:1px solid var(--border);}
.pos-receipt-status--completed{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);}
.pos-receipt-status--void{border-color:color-mix(in srgb,#94a3b8 45%,var(--border));opacity:.85;}
.pos-receipt-meta{display:grid;gap:10px;margin-bottom:14px;}
@media (min-width:640px){.pos-receipt-meta{grid-template-columns:repeat(2,minmax(0,1fr));}}
.pos-receipt-meta__card{border:1px solid var(--border);border-radius:10px;padding:10px 12px;background:color-mix(in srgb,var(--card) 96%,transparent);}
.pos-receipt-meta__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin:0 0 4px;}
.pos-receipt-meta__value{margin:0;font-size:14px;font-weight:700;color:var(--text);}
.pos-receipt-actions{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;}
.pos-btn-void{padding:8px 12px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid color-mix(in srgb,#ef4444 42%,var(--border));background:transparent;color:#f87171;cursor:pointer;}
.pos-btn-return{padding:8px 12px;font-size:12px;font-weight:700;border-radius:8px;border:1px solid color-mix(in srgb,#f59e0b 42%,var(--border));background:transparent;color:#f59e0b;cursor:pointer;}

/* ── Return modal ────────────────────────────────────────────────── */
.srm-overlay{position:fixed;inset:0;z-index:400;display:none;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;}
.srm-overlay.is-open{display:flex;}
.srm-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);}
.srm-dialog{position:relative;z-index:1;width:min(100%,640px);max-height:min(92vh,700px);display:flex;flex-direction:column;border-radius:14px;border:1px solid var(--border);background:var(--card);box-shadow:0 24px 56px rgba(0,0,0,.3);}
.srm-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 16px;border-bottom:1px solid var(--border);flex-shrink:0;}
.srm-head h2{margin:0;font-size:15px;font-weight:800;}
.srm-close{width:32px;height:32px;border:1px solid var(--border);border-radius:8px;background:transparent;color:var(--text);cursor:pointer;font-size:16px;}
.srm-body{flex:1;min-height:0;overflow-y:auto;padding:14px 16px;display:flex;flex-direction:column;gap:14px;}
.srm-foot{flex-shrink:0;padding:12px 16px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;}
.srm-items-table{width:100%;border-collapse:collapse;font-size:13px;}
.srm-items-table th{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);padding:0 8px 6px;text-align:left;border-bottom:1px solid var(--border);}
.srm-items-table th:last-child{text-align:center;width:100px;}
.srm-items-table td{padding:8px 8px;border-bottom:1px solid color-mix(in srgb,var(--border) 50%,transparent);vertical-align:middle;}
.srm-items-table tr:last-child td{border-bottom:none;}
.srm-qty-input{width:80px;padding:6px 8px;font-size:13px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);text-align:center;box-sizing:border-box;}
.srm-qty-input:disabled{opacity:.4;cursor:not-allowed;}
.srm-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:5px;}
.srm-field select,.srm-field textarea,.srm-field input[type="text"]{width:100%;box-sizing:border-box;padding:8px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);}
.srm-field select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:30px;}
.srm-field textarea{min-height:52px;resize:vertical;font-family:inherit;}
.srm-btn{padding:9px 14px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;}
.srm-btn--primary{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 16%,transparent);}
.srm-btn:disabled{opacity:.5;cursor:not-allowed;}
.srm-credit-field{display:none;}

/* ── Returns history section ─────────────────────────────────────── */
.pos-returns-section{margin-top:18px;}
.pos-returns-section__title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 10px;}
.pos-return-card{border:1px solid color-mix(in srgb,#f59e0b 40%,var(--border));border-radius:10px;padding:12px 14px;background:color-mix(in srgb,#f59e0b 5%,var(--card));margin-bottom:10px;}
.pos-return-card:last-child{margin-bottom:0;}
.pos-return-card__head{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;margin-bottom:8px;}
.pos-return-card__num{font-size:13px;font-weight:800;color:var(--text);}
.pos-return-card__meta{font-size:11px;color:var(--muted);}
.pos-return-card__total{font-size:14px;font-weight:800;color:var(--text);}
.pos-returned-badge{display:inline-block;font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;border:1px solid color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 12%,transparent);color:color-mix(in srgb,#f59e0b 80%,var(--text));white-space:nowrap;}
.pos-qty-returned{font-size:10px;color:var(--muted);display:block;margin-top:2px;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('sale'))
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first('sale') }}</div>
    @endif
    @if($errors->has('items'))
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first('items') }}</div>
    @endif

    <div class="pos-receipt-actions">
        <a href="{{ route('pos.sales.index') }}" class="pcat-link" style="font-weight:700;"><i class="fa fa-arrow-left"></i> Sales history</a>
        <a href="{{ route('pos.online') }}" class="pcat-link" style="font-weight:700;"><i class="fa fa-store"></i> Online POS</a>
        @if($sale->isCompleted())
            <button type="button" class="pos-btn-return" id="openReturnModalBtn">
                <i class="fa fa-rotate-left"></i> Return items
            </button>
            <form method="post" action="{{ route('pos.sales.void', $sale) }}" onsubmit="return confirm('Void this sale and restore stock?');" style="margin:0;">
                @csrf
                <button type="submit" class="pos-btn-void">Void sale</button>
            </form>
        @endif
    </div>

    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:14px;">
        <h2 style="margin:0;font-size:18px;font-weight:800;">{{ $sale->sale_number }}</h2>
        @if($sale->isVoid())
            <span class="pos-receipt-status pos-receipt-status--void">Void</span>
        @else
            <span class="pos-receipt-status pos-receipt-status--completed">Completed</span>
            @if($sale->returns->isNotEmpty())
                <span class="pos-returned-badge"><i class="fa fa-rotate-left"></i> {{ $sale->returns->count() }} {{ Str::plural('return', $sale->returns->count()) }}</span>
            @endif
        @endif
    </div>

    <div class="pos-receipt-meta">
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label">Sold at</p>
            <p class="pos-receipt-meta__value">{{ $sale->sold_at?->format('M j, Y g:i A') ?? '—' }}</p>
        </div>
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label">Payment</p>
            <p class="pos-receipt-meta__value">{{ $sale->paymentMethodLabel() }}</p>
            @if($sale->creditAccount)
                <p class="muted" style="margin:4px 0 0;font-size:12px;">{{ $sale->creditAccount->deductOptionLabel() }}</p>
            @endif
        </div>
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label">Total @if(filled($currency))({{ $currency }})@endif</p>
            <p class="pos-receipt-meta__value">{{ number_format((float) $sale->total, 2) }}</p>
        </div>
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label">Channel</p>
            <p class="pos-receipt-meta__value">{{ $sale->channelLabel() }}</p>
            @if($sale->branch)
                <p class="muted" style="margin:4px 0 0;font-size:12px;"><i class="fa fa-code-branch" style="font-size:10px;"></i> {{ $sale->branch->name }}</p>
            @endif
        </div>
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label">Amount paid</p>
            <p class="pos-receipt-meta__value">{{ number_format((float) $sale->amount_paid, 2) }}@if(filled($currency)) {{ $currency }}@endif</p>
        </div>
        @if($sale->payment_method === \Modules\Pos\Models\Sale::PAYMENT_CASH && $sale->amount_tendered !== null)
            <div class="pos-receipt-meta__card">
                <p class="pos-receipt-meta__label">Cash received</p>
                <p class="pos-receipt-meta__value">{{ number_format((float) $sale->amount_tendered, 2) }}@if(filled($currency)) {{ $currency }}@endif</p>
            </div>
            <div class="pos-receipt-meta__card">
                <p class="pos-receipt-meta__label">Change given</p>
                <p class="pos-receipt-meta__value">{{ number_format((float) ($sale->change_amount ?? 0), 2) }}@if(filled($currency)) {{ $currency }}@endif</p>
            </div>
        @endif
    </div>

    @if(filled($sale->notes))
        <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;"><strong style="color:var(--text);">Notes:</strong> {{ $sale->notes }}</p>
    @endif

    <div class="pcat-table-wrap">
        <table class="pcat-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty sold</th>
                    <th>Returned</th>
                    <th>Unit price @if(filled($currency))({{ $currency }})@endif</th>
                    <th>Discount / unit</th>
                    <th>Unit cost</th>
                    <th style="text-align:right;">Line total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $item)
                    @php
                        $retQty = round((float) ($returnedQtys[$item->id] ?? 0), 3);
                        $itemDiscount = (float) ($item->discount_amount ?? 0);
                        $originalUnitPrice = $itemDiscount > 0 ? round((float) $item->unit_sell_price + $itemDiscount, 2) : null;
                    @endphp
                    <tr>
                        <td>
                            <strong style="color:var(--text);">{{ $item->product_name }}</strong>
                            @if(filled($item->sku))
                                <div class="muted" style="font-size:11px;margin-top:2px;">{{ $item->sku }}</div>
                            @endif
                            @if($item->selling_unit_label)
                                <div class="muted" style="font-size:11px;margin-top:2px;">per {{ $item->selling_unit_label }}</div>
                            @elseif($item->product_stock_layer_id)
                                <div class="muted" style="font-size:11px;margin-top:2px;">Batch #{{ $item->product_stock_layer_id }}</div>
                            @endif
                        </td>
                        <td class="muted">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}</td>
                        <td>
                            @if($retQty > 0)
                                <span class="pos-returned-badge">{{ rtrim(rtrim(number_format($retQty, 3, '.', ''), '0'), '.') }}</span>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td class="muted">
                            @if($originalUnitPrice !== null)
                                <span style="text-decoration:line-through;color:var(--muted);font-size:11px;">{{ number_format($originalUnitPrice, 2) }}</span>
                                <strong style="color:var(--text);">{{ number_format((float) $item->unit_sell_price, 2) }}</strong>
                            @else
                                {{ number_format((float) $item->unit_sell_price, 2) }}
                            @endif
                        </td>
                        <td class="muted">
                            @if($itemDiscount > 0)
                                <span style="font-size:11px;background:color-mix(in srgb,#f59e0b 18%,transparent);color:#b45309;border:1px solid color-mix(in srgb,#f59e0b 40%,transparent);border-radius:4px;padding:2px 6px;font-weight:700;">
                                    −{{ number_format($itemDiscount, 2) }}
                                </span>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td class="muted">{{ $item->unit_cost !== null ? number_format((float) $item->unit_cost, 2) : '—' }}</td>
                        <td style="text-align:right;"><strong style="color:var(--text);">{{ number_format((float) $item->line_total, 2) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" style="text-align:right;font-weight:700;">Total</td>
                    <td style="text-align:right;font-weight:800;font-size:15px;color:var(--text);">{{ number_format((float) $sale->total, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Returns history --}}
    @if($sale->returns->isNotEmpty())
        <div class="pos-returns-section">
            <p class="pos-returns-section__title"><i class="fa fa-rotate-left"></i> Returns</p>
            @foreach($sale->returns as $ret)
                <div class="pos-return-card">
                    <div class="pos-return-card__head">
                        <div>
                            <span class="pos-return-card__num">{{ $ret->return_number }}</span>
                            <span class="pos-return-card__meta" style="margin-left:8px;">{{ $ret->returned_at?->format('M j, Y g:i A') }}</span>
                            @if($ret->user)
                                <span class="pos-return-card__meta"> · {{ $ret->user->name }}</span>
                            @endif
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span class="muted" style="font-size:12px;">{{ $ret->refundMethodLabel() }}</span>
                            @if(filled($ret->refund_reason))
                                <span style="font-size:11px;font-weight:600;padding:2px 7px;border-radius:999px;border:1px solid var(--border);color:var(--muted);">{{ $ret->reasonLabel() }}</span>
                            @endif
                            <span class="pos-return-card__total">{{ number_format((float) $ret->total, 2) }}@if(filled($currency)) {{ $currency }}@endif</span>
                        </div>
                    </div>
                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                        <thead>
                            <tr>
                                <th style="text-align:left;font-weight:600;color:var(--muted);padding-bottom:4px;border-bottom:1px solid var(--border);">Product</th>
                                <th style="text-align:right;font-weight:600;color:var(--muted);padding-bottom:4px;border-bottom:1px solid var(--border);">Qty returned</th>
                                <th style="text-align:right;font-weight:600;color:var(--muted);padding-bottom:4px;border-bottom:1px solid var(--border);">Line total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ret->items as $ri)
                                <tr>
                                    <td style="padding:5px 0;color:var(--text);">{{ $ri->product_name }}@if(filled($ri->sku)) <span class="muted">({{ $ri->sku }})</span>@endif</td>
                                    <td style="padding:5px 0;text-align:right;color:var(--muted);">{{ rtrim(rtrim(number_format((float) $ri->quantity, 3, '.', ''), '0'), '.') }}</td>
                                    <td style="padding:5px 0;text-align:right;font-weight:700;color:var(--text);">{{ number_format((float) $ri->line_total, 2) }}@if(filled($currency)) {{ $currency }}@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if(filled($ret->notes))
                        <p class="muted" style="margin:8px 0 0;font-size:11px;"><strong style="color:var(--text);">Notes:</strong> {{ $ret->notes }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Return items modal --}}
@if($sale->isCompleted())
@php
    $returnedQtys = $returnedQtys ?? [];
@endphp
<div id="saleReturnModal" class="srm-overlay" role="dialog" aria-modal="true" aria-labelledby="srm-title" aria-hidden="true">
    <div class="srm-backdrop" id="srmBackdrop"></div>
    <div class="srm-dialog">
        <div class="srm-head">
            <h2 id="srm-title"><i class="fa fa-rotate-left"></i> Return items — {{ $sale->sale_number }}</h2>
            <button type="button" class="srm-close" id="srmCloseBtn" aria-label="Close">&times;</button>
        </div>
        <form method="post" action="{{ route('pos.sales.returns.store', $sale) }}" id="srmForm">
            @csrf
            <div class="srm-body">

                {{-- Item selection table --}}
                <div>
                    <p style="margin:0 0 8px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);">Select items &amp; quantities to return</p>
                    <table class="srm-items-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Sold</th>
                                <th>Returnable</th>
                                <th>Return qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sale->items as $index => $item)
                                @php
                                    $retQty = round((float) ($returnedQtys[$item->id] ?? 0), 3);
                                    $returnable = round((float) $item->quantity - $retQty, 3);
                                    $soldFmt = rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.');
                                    $retFmt  = rtrim(rtrim(number_format($returnable, 3, '.', ''), '0'), '.');
                                @endphp
                                <tr>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][sale_item_id]" value="{{ $item->id }}">
                                        <strong style="color:var(--text);font-size:13px;">{{ $item->product_name }}</strong>
                                        @if(filled($item->sku))
                                            <span class="muted" style="font-size:11px;"> · {{ $item->sku }}</span>
                                        @endif
                                    </td>
                                    <td class="muted" style="white-space:nowrap;">{{ $soldFmt }}</td>
                                    <td style="white-space:nowrap;">
                                        @if($returnable > 0)
                                            <span style="color:var(--text);font-weight:600;">{{ $retFmt }}</span>
                                        @else
                                            <span class="muted">Fully returned</span>
                                        @endif
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            name="items[{{ $index }}][quantity]"
                                            class="srm-qty-input"
                                            min="0"
                                            max="{{ $returnable }}"
                                            step="0.001"
                                            value="0"
                                            @if($returnable <= 0) disabled @endif
                                            data-max="{{ $returnable }}"
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Refund method --}}
                <div class="srm-field">
                    <label for="srm-refund-method">Refund method</label>
                    <select name="refund_method" id="srm-refund-method">
                        <option value="cash">Cash refund</option>
                        <option value="credit">Credit account</option>
                        <option value="none">No refund (exchange / store credit)</option>
                    </select>
                </div>

                {{-- Return reason --}}
                <div class="srm-field">
                    <label for="srm-refund-reason">Return reason</label>
                    <select name="refund_reason" id="srm-refund-reason">
                        <option value="">— Select reason —</option>
                        @foreach(\Modules\Pos\Models\SaleReturn::REASONS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Credit account (shown only when credit is selected) --}}
                <div class="srm-field srm-credit-field" id="srmCreditField">
                    <label for="srm-credit-account">Credit account</label>
                    <select name="credit_account_id" id="srm-credit-account">
                        <option value="">— Select account —</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->deductOptionLabel() }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Notes --}}
                <div class="srm-field">
                    <label for="srm-notes">Notes (optional)</label>
                    <textarea name="notes" id="srm-notes" placeholder="Reason for return…"></textarea>
                </div>

            </div>
            <div class="srm-foot">
                <button type="button" class="srm-btn" id="srmCancelBtn">Cancel</button>
                <button type="submit" class="srm-btn srm-btn--primary" id="srmSubmitBtn">
                    <i class="fa fa-rotate-left"></i> Process return
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal     = document.getElementById('saleReturnModal');
    var openBtn   = document.getElementById('openReturnModalBtn');
    var closeBtn  = document.getElementById('srmCloseBtn');
    var cancelBtn = document.getElementById('srmCancelBtn');
    var backdrop  = document.getElementById('srmBackdrop');
    var form      = document.getElementById('srmForm');
    var submitBtn = document.getElementById('srmSubmitBtn');
    var refundSel = document.getElementById('srm-refund-method');
    var creditField = document.getElementById('srmCreditField');

    function setOpen(open) {
        if (!modal) return;
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    if (openBtn) openBtn.addEventListener('click', function () { setOpen(true); });
    if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });
    if (cancelBtn) cancelBtn.addEventListener('click', function () { setOpen(false); });
    if (backdrop) backdrop.addEventListener('click', function () { setOpen(false); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) setOpen(false);
    });

    // Show/hide credit account field
    if (refundSel) {
        refundSel.addEventListener('change', function () {
            if (creditField) creditField.style.display = refundSel.value === 'credit' ? 'block' : 'none';
        });
    }

    // Validate: at least one qty > 0 before submitting
    if (form) {
        form.addEventListener('submit', function (e) {
            var qtyInputs = form.querySelectorAll('.srm-qty-input:not([disabled])');
            var anyPositive = false;
            qtyInputs.forEach(function (inp) {
                var v = parseFloat(inp.value) || 0;
                var max = parseFloat(inp.getAttribute('data-max')) || 0;
                if (v > 0 && v <= max + 0.0005) anyPositive = true;
                if (v > max + 0.0005) inp.value = String(max);
            });
            if (!anyPositive) {
                e.preventDefault();
                alert('Enter a return quantity greater than 0 for at least one item.');
                return;
            }
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Processing…'; }
        });
    }
})();
</script>
@endif
@endsection
