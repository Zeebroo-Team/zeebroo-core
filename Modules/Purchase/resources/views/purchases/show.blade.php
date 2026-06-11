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
                        $ordered = (float) $item->quantity;
                        $received = $item->quantityReceived();
                        $remaining = $item->quantityRemaining();
                    @endphp
                    <tr>
                        <td>
                            <strong style="color:var(--text);">{{ $item->product?->name ?? 'Product #'.$item->product_id }}</strong>
                            @if($item->product?->sku)
                                <div class="muted" style="font-size:12px;margin-top:2px;">{{ $item->product->sku }}</div>
                            @endif
                        </td>
                        <td class="muted">{{ rtrim(rtrim(number_format($ordered, 3, '.', ''), '0'), '.') }}</td>
                        <td class="muted">{{ rtrim(rtrim(number_format($received, 3, '.', ''), '0'), '.') }}</td>
                        <td class="muted"><strong style="color:var(--text);">{{ rtrim(rtrim(number_format($remaining, 3, '.', ''), '0'), '.') }}</strong></td>
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
        <h3 style="margin:0 0 8px;font-size:14px;font-weight:700;">Goods receive notes</h3>
        <div class="pcat-table-wrap" style="margin-bottom:14px;">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>GRN #</th>
                        <th>Date</th>
                        <th>Total @if(filled($currency))({{ $currency }})@endif</th>
                        <th>Payment</th>
                        <th>Pay</th>
                        <th style="text-align:right;">View</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchase->goodsReceiveNotes as $grnRow)
                        <tr>
                            <td><strong style="color:var(--text);">{{ $grnRow->grn_number }}</strong></td>
                            <td class="muted">{{ $grnRow->received_date->format('M j, Y') }}</td>
                            <td>{{ number_format((float) $grnRow->total, 2) }}</td>
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
                            <td style="text-align:right;"><a href="{{ route('purchase.grn.show', $grnRow) }}" class="pcat-link">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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
