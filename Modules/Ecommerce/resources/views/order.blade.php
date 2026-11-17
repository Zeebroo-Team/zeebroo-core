@php
    $cur   = $store->currency ?: '';
    $money = fn ($n) => trim($cur . ' ' . number_format((float) $n, 2));
    $isOnline = in_array($order->payment_method, \Modules\Ecommerce\Models\Order::ONLINE_METHODS, true);
    $awaiting = $isOnline && $order->payment_status !== 'paid' && ! $cancelled && $order->placed_at?->gt(now()->subMinutes(10));
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Order {{ $order->order_no }} — {{ $store->name }}</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
@include('ecommerce::partials.styles')
@if($awaiting)
    {{-- Gateway confirmations can arrive a few seconds after the customer returns. --}}
    <meta http-equiv="refresh" content="6">
@endif
</head>
<body class="tpl-{{ $template }}">
<header class="sf-head">
    <div class="sf-head-in">
        <a class="sf-brand" href="{{ $store->publicUrl() }}">
            @if($store->logo_url)<img src="{{ $store->logo_url }}" alt="">@endif
            <span>{{ $store->name }}</span>
        </a>
    </div>
</header>

<main class="sf-main" style="max-width:720px">
    <div class="sf-about" style="margin-top:10px">
        <div class="sf-done" style="padding:10px 0 18px">
            @if($order->payment_status === 'paid')
                <i class="fa fa-circle-check"></i>
                <h2 style="margin:8px 0 4px">Payment received — thank you!</h2>
            @elseif($cancelled)
                <i class="fa fa-circle-xmark" style="color:var(--danger)"></i>
                <h2 style="margin:8px 0 4px">Payment was not completed</h2>
                <p style="color:var(--muted)">Your order is saved. Please contact us to complete payment, or place a new order.</p>
            @elseif($awaiting)
                <i class="fa fa-spinner fa-spin" style="color:var(--accent)"></i>
                <h2 style="margin:8px 0 4px">Confirming your payment…</h2>
                <p style="color:var(--muted)">This page refreshes automatically.</p>
            @elseif($isOnline)
                <i class="fa fa-clock" style="color:var(--muted)"></i>
                <h2 style="margin:8px 0 4px">Payment pending</h2>
                <p style="color:var(--muted)">We haven't received payment confirmation yet. We'll contact you if anything is needed.</p>
            @else
                <i class="fa fa-circle-check"></i>
                <h2 style="margin:8px 0 4px">Order received — thank you!</h2>
            @endif
            <p style="margin:0">Order <strong>{{ $order->order_no }}</strong> · {{ $methodLabel }}</p>
        </div>

        @foreach($order->items as $item)
            <div class="sf-line">
                <div class="sf-line-name">{{ $item->name }}<div style="color:var(--muted);font-size:12px">{{ $money($item->unit_price) }} × {{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}</div></div>
                <strong style="font-size:13px">{{ $money($item->line_total) }}</strong>
            </div>
        @endforeach
        <div style="margin-top:12px">
            <div class="sf-sum"><span>Subtotal</span><span>{{ $money($order->subtotal) }}</span></div>
            <div class="sf-sum"><span>Shipping</span><span>{{ (float) $order->shipping_fee ? $money($order->shipping_fee) : 'Free' }}</span></div>
            <div class="sf-sum total"><span>Total</span><span>{{ $money($order->total) }}</span></div>
        </div>

        @if($order->payment_method === 'bank_transfer' && $order->payment_status !== 'paid' && $store->bank_transfer_details)
            <p style="font-size:13px;color:var(--muted);margin-top:18px">Please transfer the total to:</p>
            <div class="sf-bank">{{ $store->bank_transfer_details }}</div>
            <p style="font-size:13px;color:var(--muted)">Use <strong>{{ $order->order_no }}</strong> as the payment reference.</p>
        @endif

        <p style="font-size:13px;color:var(--muted);margin-top:18px">
            Delivering to {{ $order->customer_name }}, {{ $order->shipping_address }}@if($order->shipping_city), {{ $order->shipping_city }}@endif.
            Status: <strong>{{ ucfirst($order->status) }}</strong>@if($order->tracking_no) · Tracking: <strong>{{ $order->tracking_no }}</strong>@endif
        </p>
        <a class="sf-primary" href="{{ $store->publicUrl() }}" style="margin-top:10px">Continue shopping</a>
    </div>
</main>
</body>
</html>
