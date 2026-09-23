@extends('theme::layouts.app', ['title' => 'Sales Order', 'heading' => 'Sales Order'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.so-status{display:inline-block;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;border:1px solid var(--border);}
.so-status--pending    {border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 12%,transparent);}
.so-status--confirmed  {border-color:color-mix(in srgb,#3b82f6 45%,var(--border));background:color-mix(in srgb,#3b82f6 12%,transparent);}
.so-status--processing {border-color:color-mix(in srgb,#8b5cf6 45%,var(--border));background:color-mix(in srgb,#8b5cf6 12%,transparent);}
.so-status--completed  {border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 12%,transparent);}
.so-status--cancelled  {border-color:color-mix(in srgb,#6b7280 45%,var(--border));background:color-mix(in srgb,#6b7280 12%,transparent);opacity:.8;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('sales::partials.sales-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
    @endif

    {{-- Header --}}
    <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:20px;">
        <div>
            <p class="muted" style="margin:0 0 4px;font-size:12px;">
                Ordered {{ $order->order_date->format('M j, Y') }}
                @if($order->expected_delivery_date)
                    &middot; Expected {{ $order->expected_delivery_date->format('M j, Y') }}
                @endif
            </p>
            <h2 style="margin:0;font-size:19px;font-weight:800;color:var(--text);">
                {{ $order->order_number ?? 'Order' }}
                @if($order->customer)
                    <span class="muted" style="font-weight:600;font-size:14px;">&middot; {{ $order->customer->name }}</span>
                @endif
            </h2>
            @if($order->reference)
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Ref: {{ $order->reference }}</p>
            @endif
            @if($order->invoice)
                <p class="muted" style="margin:4px 0 0;font-size:12px;">
                    <i class="fa fa-file-invoice" style="margin-right:4px;"></i>
                    Converted to invoice
                    <a href="{{ route('sales.invoices.show', $order->invoice) }}" class="pcat-link">{{ $order->invoice->invoice_number }}</a>
                </p>
            @endif
            <span class="so-status so-status--{{ $order->status }}" style="margin-top:8px;display:inline-block;">
                {{ $order->statusLabel() }}
            </span>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-start;">
            @if($order->isEditable())
                <a href="{{ route('sales.orders.edit', $order) }}"
                   class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                    Edit
                </a>
            @endif

            @if($order->status === \Modules\Sales\Models\SalesOrder::STATUS_PENDING)
                <form method="POST" action="{{ route('sales.orders.confirm', $order) }}" style="display:inline;">
                    @csrf
                    <button type="submit" onclick="return confirm('Confirm this order? It will be converted into an invoice and stock will be deducted.')"
                            class="linkbtn" style="padding:8px 14px;font-size:13px;background:color-mix(in srgb,#3b82f6 12%,transparent);border:1px solid color-mix(in srgb,#3b82f6 45%,var(--border));color:var(--text);">
                        <i class="fa fa-circle-check"></i> Confirm
                    </button>
                </form>
            @endif

            @if($order->status === \Modules\Sales\Models\SalesOrder::STATUS_CONFIRMED)
                <form method="POST" action="{{ route('sales.orders.process', $order) }}" style="display:inline;">
                    @csrf
                    <button type="submit"
                            class="linkbtn" style="padding:8px 14px;font-size:13px;background:color-mix(in srgb,#8b5cf6 12%,transparent);border:1px solid color-mix(in srgb,#8b5cf6 45%,var(--border));color:var(--text);">
                        <i class="fa fa-truck"></i> Mark processing
                    </button>
                </form>
            @endif

            @if(in_array($order->status, [\Modules\Sales\Models\SalesOrder::STATUS_CONFIRMED, \Modules\Sales\Models\SalesOrder::STATUS_PROCESSING]))
                <form method="POST" action="{{ route('sales.orders.complete', $order) }}" style="display:inline;">
                    @csrf
                    <button type="submit"
                            class="linkbtn" style="padding:8px 14px;font-size:13px;background:color-mix(in srgb,#22c55e 12%,transparent);border:1px solid color-mix(in srgb,#22c55e 45%,var(--border));color:var(--text);">
                        <i class="fa fa-flag-checkered"></i> Mark completed
                    </button>
                </form>
            @endif

            @if(!in_array($order->status, [\Modules\Sales\Models\SalesOrder::STATUS_COMPLETED, \Modules\Sales\Models\SalesOrder::STATUS_CANCELLED]))
                <form method="POST" action="{{ route('sales.orders.cancel', $order) }}" style="display:inline;">
                    @csrf
                    <button type="submit" onclick="return confirm('Cancel this order?')"
                            class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid color-mix(in srgb,#94a3b8 45%,var(--border));color:var(--muted);">
                        Cancel
                    </button>
                </form>
            @endif

            @if(in_array($order->status, [\Modules\Sales\Models\SalesOrder::STATUS_PENDING, \Modules\Sales\Models\SalesOrder::STATUS_CANCELLED]))
                <form method="POST" action="{{ route('sales.orders.destroy', $order) }}" style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" onclick="return confirm('Delete this order? This cannot be undone.')"
                            class="pcat-btn-del" style="padding:8px 12px;">
                        <i class="fa fa-trash"></i>
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Customer info --}}
    @if($order->customer)
    <div style="border:1px solid var(--border);border-radius:12px;padding:12px 14px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:20px;background:color-mix(in srgb,var(--card) 97%,transparent);">
        <div>
            <p class="muted" style="margin:0 0 2px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Customer</p>
            <p style="margin:0;font-weight:700;font-size:13px;color:var(--text);">{{ $order->customer->name }}</p>
        </div>
        @if($order->customer->email)
        <div>
            <p class="muted" style="margin:0 0 2px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Email</p>
            <p style="margin:0;font-size:13px;color:var(--text);">{{ $order->customer->email }}</p>
        </div>
        @endif
        @if($order->customer->phone)
        <div>
            <p class="muted" style="margin:0 0 2px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Phone</p>
            <p style="margin:0;font-size:13px;color:var(--text);">{{ $order->customer->phone }}</p>
        </div>
        @endif
    </div>
    @endif

    {{-- Line items --}}
    <div class="pcat-table-wrap" style="margin-bottom:16px;">
        <table class="pcat-table">
            <thead>
                <tr>
                    <th style="width:4%;">#</th>
                    <th>Item</th>
                    <th style="width:10%;text-align:right;">Qty</th>
                    <th style="width:16%;text-align:right;">Unit price{{ $currency ? ' ('.$currency.')' : '' }}</th>
                    <th style="width:16%;text-align:right;">Total{{ $currency ? ' ('.$currency.')' : '' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    @php
                        $label    = $item->product?->name ?? ($item->description ?: '—');
                        $sublabel = ($item->product && $item->description && $item->description !== $item->product->name)
                                  ? $item->description : null;
                    @endphp
                    <tr>
                        <td class="muted">{{ $loop->iteration }}</td>
                        <td>
                            <strong style="color:var(--text);">{{ $label }}</strong>
                            @if($sublabel)
                                <div class="muted" style="font-size:11px;">{{ $sublabel }}</div>
                            @endif
                        </td>
                        <td style="text-align:right;">{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }}</td>
                        <td style="text-align:right;">{{ number_format($item->unit_price, 2) }}</td>
                        <td style="text-align:right;font-weight:700;color:var(--text);">{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totals --}}
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
        <div style="min-width:260px;font-size:13px;">
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
                <span class="muted">Subtotal</span>
                <span>{{ number_format($order->subtotal, 2) }}</span>
            </div>
            @if($order->discount_amount > 0)
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
                <span class="muted">Discount</span>
                <span style="color:#ef4444;">− {{ number_format($order->discount_amount, 2) }}</span>
            </div>
            @endif
            @if($order->tax_amount > 0)
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
                <span class="muted">Tax</span>
                <span>+ {{ number_format($order->tax_amount, 2) }}</span>
            </div>
            @endif
            <div style="display:flex;justify-content:space-between;padding:10px 0;font-size:15px;font-weight:800;">
                <span style="color:var(--text);">Total{{ $currency ? ' ('.$currency.')' : '' }}</span>
                <span style="color:var(--text);">{{ number_format($order->total, 2) }}</span>
            </div>
        </div>
    </div>

    @if($order->notes)
    <div style="border-left:3px solid var(--border);padding:10px 14px;border-radius:0 8px 8px 0;background:color-mix(in srgb,var(--card) 96%,transparent);margin-bottom:12px;">
        <p class="muted" style="margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Notes</p>
        <p style="margin:0;font-size:13px;color:var(--text);white-space:pre-line;">{{ $order->notes }}</p>
    </div>
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('sales.orders.index') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> All orders
    </a>
</div>
@endsection
