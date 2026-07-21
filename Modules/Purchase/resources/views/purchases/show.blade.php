@extends('theme::layouts.app', ['title' => 'Purchase order', 'heading' => 'Purchase order'])

@section('content')
@include('product::partials.catalog-hub-styles')
@include('purchase::goods-receive.partials.grn-payment-styles')
<style>
.purchase-status{display:inline-block;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);}
.purchase-status--draft{opacity:.85;}
.purchase-status--ordered{border-color:color-mix(in srgb,#3b82f6 45%,var(--border));background:color-mix(in srgb,#3b82f6 12%,transparent);}
.purchase-status--partially_received{border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 12%,transparent);}
.purchase-status--received{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);}
.purchase-status--cancelled{border-color:color-mix(in srgb,#94a3b8 45%,var(--border));opacity:.75;}
</style>

<div class="pcat-page-card card" style="max-width:900px;margin:0 auto;padding:14px;">
    @include('purchase::partials.purchase-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
    @endif

    <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;">
        <div>
            <p class="muted" style="margin:0 0 4px;font-size:12px;">
                {{ $purchase->purchase_date->format('M j, Y') }}
                @if($purchase->expected_delivery_date)
                    · Expected {{ $purchase->expected_delivery_date->format('M j, Y') }}
                @endif
            </p>
            <h2 style="margin:0;font-size:18px;font-weight:800;color:var(--text);">
                {{ $purchase->po_number ?? 'Purchase order' }}
                @if($purchase->supplier)
                    <span class="muted" style="font-weight:600;font-size:14px;">· {{ $purchase->supplier->name }}</span>
                @endif
            </h2>
            @if($purchase->reference)
                <p class="muted" style="margin:6px 0 0;font-size:12px;">Supplier ref: {{ $purchase->reference }}</p>
            @endif
            <span class="purchase-status purchase-status--{{ $purchase->status }}" style="margin-top:8px;display:inline-block;">{{ $purchase->statusLabel() }}</span>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <a href="{{ route('purchase.print', $purchase) }}" target="_blank" class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-print"></i> Print
            </a>
            @if($purchase->isEditable())
                <a href="{{ route('purchase.edit', $purchase) }}" class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Edit</a>
            @endif
            @if($purchase->isDraft())
                <form method="post" action="{{ route('purchase.place-order', $purchase) }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="linkbtn" style="padding:8px 14px;font-size:13px;">Place order</button>
                </form>
            @endif
            @if($purchase->canReceiveGoods())
                <a href="{{ route('purchase.grn.create', $purchase) }}" class="linkbtn" style="padding:8px 14px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;"><i class="fa fa-truck-ramp-box"></i> Record GRN</a>
                <form method="post" action="{{ route('purchase.receive', $purchase) }}" style="margin:0;" onsubmit="return confirm('Receive all remaining quantities now?');">
                    @csrf
                    <button type="submit" class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);">Receive all</button>
                </form>
            @endif
            @if($purchase->isDraft() || $purchase->isOrdered())
                <form method="post" action="{{ route('purchase.cancel', $purchase) }}" style="margin:0;" onsubmit="return confirm('Cancel this purchase order?');">
                    @csrf
                    <button type="submit" class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);">Cancel</button>
                </form>
            @endif
            @if($purchase->isDraft() || $purchase->isCancelled())
                <form method="post" action="{{ route('purchase.destroy', $purchase) }}" style="margin:0;" onsubmit="return confirm('Delete this purchase order?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="pcat-btn-del" style="padding:8px 12px;">Delete</button>
                </form>
            @endif
        </div>
    </div>

    @if($purchase->notes)
        <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">{{ $purchase->notes }}</p>
    @endif

    @php
        $fmtQty = static fn (float $q): string => rtrim(rtrim(number_format($q, 3, '.', ''), '0'), '.');
        $poTotalQty = 0.0; $poReceivedQty = 0.0;
        foreach ($purchase->items as $_pi) {
            $poTotalQty += (float) $_pi->quantity;
            $poReceivedQty += (float) $_pi->goodsReceiveNoteItems->sum('quantity_received');
        }
        $poRemainingQty = max(0.0, $poTotalQty - $poReceivedQty);
        $poReceivedPct = $poTotalQty > 0 ? min(100, (int) round($poReceivedQty / $poTotalQty * 100)) : 0;
    @endphp

    @if($purchase->isPartiallyReceived() || $purchase->isReceived())
    <div style="margin-bottom:14px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:var(--card);">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:7px;">
            <span style="font-size:12px;font-weight:700;color:var(--text);">
                <i class="fa fa-truck-ramp-box" style="color:{{ $purchase->isReceived() ? '#22c55e' : '#f59e0b' }};margin-right:4px;"></i>
                Receiving progress
            </span>
            <span style="font-size:12px;color:var(--muted);">{{ $fmtQty($poReceivedQty) }} / {{ $fmtQty($poTotalQty) }} units · <strong style="color:var(--text);">{{ $poReceivedPct }}%</strong></span>
        </div>
        <div style="height:7px;border-radius:999px;background:color-mix(in srgb,var(--border) 80%,transparent);overflow:hidden;">
            <div style="height:100%;width:{{ $poReceivedPct }}%;background:{{ $purchase->isReceived() ? 'color-mix(in srgb,#22c55e 80%,var(--text))' : 'color-mix(in srgb,#f59e0b 80%,var(--text))' }};border-radius:999px;"></div>
        </div>
        @if(!$purchase->isReceived() && $poRemainingQty > 0)
            <p style="margin:6px 0 0;font-size:11px;color:var(--muted);">{{ $fmtQty($poRemainingQty) }} units remaining — <a href="{{ route('purchase.grn.create', $purchase) }}" class="pcat-link">Record next GRN</a></p>
        @elseif($purchase->isReceived())
            <p style="margin:6px 0 0;font-size:11px;color:color-mix(in srgb,#22c55e 70%,var(--text));">All items fully received across {{ $purchase->goodsReceiveNotes->count() }} {{ $purchase->goodsReceiveNotes->count() === 1 ? 'delivery' : 'deliveries' }}.</p>
        @endif
    </div>
    @endif

    <div class="pcat-table-wrap" style="margin-bottom:16px;">
        <table class="pcat-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Ordered</th>
                    <th>Received</th>
                    <th>Remaining</th>
                    <th>Unit cost @if(filled($currency))({{ $currency }})@endif</th>
                    <th style="text-align:right;">Line total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchase->items as $item)
                    @php
                        $ordered   = (float) $item->quantity;
                        $received  = (float) $item->goodsReceiveNoteItems->sum('quantity_received');
                        $remaining = max(0.0, round($ordered - $received, 3));
                    @endphp
                    <tr>
                        <td>
                            <strong style="color:var(--text);">{{ $item->product?->name ?? 'Product #'.$item->product_id }}</strong>
                            @if($item->product?->sku)
                                <div class="muted" style="font-size:12px;margin-top:2px;">{{ $item->product->sku }}</div>
                            @endif
                        </td>
                        <td class="muted">{{ $fmtQty($ordered) }}</td>
                        <td class="muted" style="{{ $received > 0 ? 'color:color-mix(in srgb,#22c55e 70%,var(--text));font-weight:600;' : '' }}">{{ $fmtQty($received) }}</td>
                        <td>
                            @if($remaining > 0)
                                <strong style="color:var(--text);">{{ $fmtQty($remaining) }}</strong>
                            @else
                                <span class="muted" style="font-size:11px;"><i class="fa fa-check" style="color:color-mix(in srgb,#22c55e 80%,var(--text));"></i> Done</span>
                            @endif
                        </td>
                        <td class="muted">{{ number_format((float) $item->unit_cost, 2) }}</td>
                        <td style="text-align:right;"><strong style="color:var(--text);">{{ number_format((float) $item->line_total, 2) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align:right;font-weight:700;">PO total</td>
                    <td style="text-align:right;font-weight:800;font-size:15px;color:var(--text);">{{ number_format((float) $purchase->total, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>


    @if($purchase->goodsReceiveNotes->isNotEmpty())
        @php
            $grnTotalDelivered = $purchase->goodsReceiveNotes->sum(fn ($g) => (float) $g->total);
            $grnTotalPaid      = $purchase->goodsReceiveNotes->sum(fn ($g) => (float) ($g->ledger_paid_total ?? 0));
            $grnOutstanding    = max(0.0, round($grnTotalDelivered - $grnTotalPaid, 2));
            $payPct            = $grnTotalDelivered > 0 ? min(100, (int) round($grnTotalPaid / $grnTotalDelivered * 100)) : 0;
            $allPaid           = $grnOutstanding < 0.005;
        @endphp

        {{-- Payment aggregate summary --}}
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:14px;">
            <div style="padding:10px 13px;border:1px solid var(--border);border-radius:9px;background:var(--card);">
                <p style="margin:0 0 2px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">Delivered value</p>
                <p style="margin:0;font-size:18px;font-weight:800;color:var(--text);font-variant-numeric:tabular-nums;">{{ number_format($grnTotalDelivered, 2) }}</p>
                @if(filled($currency))<span style="font-size:11px;color:var(--muted);">{{ $currency }}</span>@endif
            </div>
            <div style="padding:10px 13px;border:1px solid color-mix(in srgb,#22c55e 30%,var(--border));border-radius:9px;background:color-mix(in srgb,#22c55e 6%,var(--card));">
                <p style="margin:0 0 2px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">Total paid</p>
                <p style="margin:0;font-size:18px;font-weight:800;color:color-mix(in srgb,#22c55e 70%,var(--text));font-variant-numeric:tabular-nums;">{{ number_format($grnTotalPaid, 2) }}</p>
                @if(filled($currency))<span style="font-size:11px;color:var(--muted);">{{ $currency }}</span>@endif
            </div>
            <div style="padding:10px 13px;border:1px solid {{ $allPaid ? 'var(--border)' : 'color-mix(in srgb,#f59e0b 35%,var(--border))' }};border-radius:9px;background:{{ $allPaid ? 'var(--card)' : 'color-mix(in srgb,#f59e0b 6%,var(--card))' }};">
                <p style="margin:0 0 2px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">Outstanding</p>
                <p style="margin:0;font-size:18px;font-weight:800;color:{{ $allPaid ? 'var(--muted)' : 'color-mix(in srgb,#f59e0b 80%,var(--text))' }};font-variant-numeric:tabular-nums;">{{ number_format($grnOutstanding, 2) }}</p>
                @if(filled($currency))<span style="font-size:11px;color:var(--muted);">{{ $currency }}</span>@endif
            </div>
        </div>

        @if($grnTotalDelivered > 0)
        <div style="margin-bottom:14px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;font-size:11px;color:var(--muted);">
                <span>Payment progress</span>
                <span><strong style="color:var(--text);">{{ $payPct }}%</strong> paid</span>
            </div>
            <div style="height:6px;border-radius:999px;background:color-mix(in srgb,var(--border) 80%,transparent);overflow:hidden;">
                <div style="height:100%;width:{{ $payPct }}%;background:{{ $allPaid ? 'color-mix(in srgb,#22c55e 80%,var(--text))' : 'color-mix(in srgb,#6366f1 75%,var(--text))' }};border-radius:999px;"></div>
            </div>
        </div>
        @endif

        <h3 style="margin:0 0 8px;font-size:14px;font-weight:700;">Goods receive notes</h3>
        <div class="pcat-table-wrap" style="margin-bottom:14px;">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Total @if(filled($currency))({{ $currency }})@endif</th>
                        <th>Paid @if(filled($currency))({{ $currency }})@endif</th>
                        <th>Outstanding</th>
                        <th>Status</th>
                        <th>Pay</th>
                        <th style="text-align:right;">View</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchase->goodsReceiveNotes as $grnRow)
                        @php
                            $grnPaid = (float) ($grnRow->ledger_paid_total ?? 0);
                            $grnDue  = max(0.0, round((float) $grnRow->total - $grnPaid, 2));
                        @endphp
                        <tr>
                            <td><strong style="color:var(--text);font-size:12px;">{{ $grnRow->grn_number }}</strong></td>
                            <td class="muted" style="font-size:12px;">{{ $grnRow->received_date->format('M j, Y') }}</td>
                            <td style="font-size:12px;font-variant-numeric:tabular-nums;">{{ number_format((float) $grnRow->total, 2) }}</td>
                            <td style="font-size:12px;font-variant-numeric:tabular-nums;color:color-mix(in srgb,#22c55e 65%,var(--text));font-weight:600;">{{ number_format($grnPaid, 2) }}</td>
                            <td style="font-size:12px;font-variant-numeric:tabular-nums;">
                                @if($grnDue > 0.005)
                                    <strong style="color:color-mix(in srgb,#f59e0b 80%,var(--text));">{{ number_format($grnDue, 2) }}</strong>
                                @else
                                    <span class="muted" style="font-size:11px;"><i class="fa fa-check" style="color:color-mix(in srgb,#22c55e 75%,var(--text));"></i> Settled</span>
                                @endif
                            </td>
                            <td class="grn-pay-status-cell">
                                @include('purchase::goods-receive.partials.payment-status', [
                                    'grn' => $grnRow,
                                    'currency' => $currency,
                                    'compact' => true,
                                    'dense' => true,
                                ])
                            </td>
                            <td>
                                @include('purchase::goods-receive.partials.pay-action', [
                                    'grn' => $grnRow,
                                    'currency' => $currency,
                                    'hasPaymentAccounts' => $hasPaymentAccounts ?? false,
                                    'returnTo' => 'purchase',
                                ])
                            </td>
                            <td style="text-align:right;"><a href="{{ route('purchase.grn.show', $grnRow) }}" class="pcat-link" style="font-size:12px;">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" style="text-align:right;font-weight:700;font-size:12px;">Totals</td>
                        <td style="font-size:12px;font-weight:800;font-variant-numeric:tabular-nums;">{{ number_format($grnTotalDelivered, 2) }}</td>
                        <td style="font-size:12px;font-weight:800;font-variant-numeric:tabular-nums;color:color-mix(in srgb,#22c55e 65%,var(--text));">{{ number_format($grnTotalPaid, 2) }}</td>
                        <td colspan="4" style="font-size:12px;font-weight:800;font-variant-numeric:tabular-nums;color:{{ $allPaid ? 'var(--muted)' : 'color-mix(in srgb,#f59e0b 80%,var(--text))' }};">{{ number_format($grnOutstanding, 2) }} {{ $allPaid ? '(settled)' : 'due' }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @elseif($purchase->canReceiveGoods())
        <div style="margin-bottom:14px;padding:12px 14px;border:1px dashed var(--border);border-radius:10px;text-align:center;">
            <p class="muted" style="margin:0 0 8px;font-size:13px;">No deliveries recorded yet.</p>
            <a href="{{ route('purchase.grn.create', $purchase) }}" class="linkbtn" style="padding:7px 14px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-truck-ramp-box"></i> Record first delivery
            </a>
        </div>
    @endif

    <div style="margin-top:14px;">
        <a href="{{ route('purchase.index') }}" class="linkbtn" style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-arrow-left"></i> All purchase orders
        </a>
    </div>
</div>

@include('purchase::goods-receive.partials.pay-modal', [
    'accounts' => $accounts,
    'hasPaymentAccounts' => $hasPaymentAccounts ?? false,
    'canPayByCheque' => $canPayByCheque ?? false,
    'openPayGrnId' => 0,
])
@endsection
