@extends('theme::layouts.app', ['title' => 'Billing & Payments', 'heading' => 'Billing & Payments'])

@section('content')
@php
    $statusLabels = [
        'active' => ['label' => 'Active', 'color' => '#16a34a'],
        'trialing' => ['label' => 'Trial', 'color' => '#2563eb'],
        'past_due' => ['label' => 'Past due', 'color' => '#f59e0b'],
        'canceled' => ['label' => 'Canceled', 'color' => '#6b7280'],
        'pending_payment' => ['label' => 'Payment pending', 'color' => '#ef4444'],
    ];
    $subStatus = $summary['subscription_status'] ?? null;
    $statusInfo = $statusLabels[$subStatus] ?? ['label' => $subStatus ? ucfirst(str_replace('_', ' ', $subStatus)) : 'No subscription', 'color' => '#6b7280'];
    $overdue = $summary['overdue'] ?? null;
    $paymentDue = $summary['payment_due'] ?? null;
    $items = collect($summary['items'] ?? []);
    $paidItems = $items->where('payment_status', 'succeeded')->values();
    $dueItems = $items->whereIn('payment_status', ['pending', 'failed', 'canceled'])->values();
@endphp
<style>
    .bill-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:20px 22px;margin-bottom:16px;}
    .bill-summary-row{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:14px;}
    .bill-status-pill{display:inline-flex;align-items:center;gap:7px;padding:5px 14px;border-radius:999px;font-size:12.5px;font-weight:700;}
    .bill-renewal{font-size:13px;color:var(--muted);margin-top:6px;}
    .bill-alert{display:flex;gap:12px;align-items:flex-start;padding:14px 16px;border-radius:14px;background:linear-gradient(135deg,#fef2f2,#fee2e2);border:1px solid #fca5a5;margin-bottom:16px;}
    .bill-alert--warn{background:linear-gradient(135deg,#fffbeb,#fef3c7);border-color:#fcd34d;}
    .bill-alert--warn .bill-alert-icon{background:#f59e0b;}
    .bill-btn--warn{background:#f59e0b;border-color:#f59e0b;color:#fff;}
    .bill-btn--warn:hover{background:#d97706;border-color:#d97706;color:#fff;}
    .bill-alert-icon{width:28px;height:28px;border-radius:999px;background:#ef4444;color:#fff;display:grid;place-items:center;font-weight:700;flex-shrink:0;}
    .bill-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;font-size:13px;font-weight:700;border-radius:9px;border:1.5px solid var(--border);background:transparent;color:var(--text);cursor:pointer;font-family:inherit;text-decoration:none;}
    .bill-btn:hover{border-color:var(--primary);color:var(--primary);}
    .bill-btn--danger{border-color:#ef4444;color:#ef4444;}
    .bill-btn--danger:hover{background:color-mix(in srgb,#ef4444 8%,transparent);}
    .bill-btn--primary{background:#ef4444;border-color:#ef4444;color:#fff;}
    .bill-btn--primary:hover{opacity:.9;color:#fff;}
    .bill-tabs{display:flex;gap:6px;border-bottom:1px solid var(--border);margin-bottom:14px;}
    .bill-tab{-webkit-appearance:none;appearance:none;padding:9px 4px;margin-right:18px;font-size:13.5px;font-weight:700;color:var(--muted);border:none;border-bottom:2px solid transparent;border-radius:0;cursor:pointer;background:none;outline:none;box-shadow:none;font-family:inherit;}
    .bill-tab.is-active{color:var(--primary);border-bottom-color:var(--primary);}
    .bill-tab:hover{background:none;color:var(--primary);transform:none;}
    .bill-tab.is-active:hover{color:var(--primary);}
    .bill-tab-count{display:inline-block;margin-left:6px;padding:1px 7px;border-radius:999px;background:color-mix(in srgb,var(--muted) 16%,transparent);font-size:11px;}
    .bill-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 4px;border-bottom:1px solid var(--border);}
    .bill-row:last-child{border-bottom:none;}
    .bill-row-plan{font-weight:700;font-size:13.5px;}
    .bill-row-date{font-size:12px;color:var(--muted);margin-top:2px;}
    .bill-badge{padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;}
    .bill-badge--succeeded{background:color-mix(in srgb,#16a34a 16%,transparent);color:#16a34a;}
    .bill-badge--pending{background:color-mix(in srgb,#f59e0b 16%,transparent);color:#b45309;}
    .bill-badge--failed{background:color-mix(in srgb,#ef4444 16%,transparent);color:#ef4444;}
    .bill-badge--canceled{background:color-mix(in srgb,#6b7280 16%,transparent);color:#6b7280;}
    .bill-empty{padding:28px 4px;text-align:center;color:var(--muted);font-size:13px;}
</style>

@if(session('status'))
    <div class="bill-card" style="border-color:color-mix(in srgb,#16a34a 38%,var(--border));background:color-mix(in srgb,#16a34a 9%,var(--card));color:#15803d;font-weight:600;">
        <i class="fa fa-circle-check" aria-hidden="true"></i> {{ session('status') }}
    </div>
@endif

@if($errors->has('payment'))
    <div class="bill-alert">
        <div class="bill-alert-icon">!</div>
        <div>{{ $errors->first('payment') }}</div>
    </div>
@endif

@if(! $business)
    <div class="bill-card bill-empty">No business selected yet — choose or create a business first.</div>
@else
    @if($overdue)
        <div class="bill-alert">
            <div class="bill-alert-icon">!</div>
            <div style="flex:1;min-width:0;">
                <div style="color:#991b1b;font-weight:700;">Your subscription payment is overdue</div>
                <div style="color:#b91c1c;font-size:13px;margin-top:2px;">
                    Access is locked until this is settled @if($overdue['due_at']) — it was due {{ \Illuminate\Support\Carbon::parse($overdue['due_at'])->format('d M Y') }} @endif.
                </div>
                @if($overdue['can_pay'])
                    <form method="post" action="{{ route('payment.checkout.resume', ['payment' => $overdue['payment_id']]) }}" style="margin-top:10px;">
                        @csrf
                        <button type="submit" class="bill-btn bill-btn--primary">
                            <i class="fa fa-credit-card" aria-hidden="true"></i> Complete payment
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @elseif($paymentDue)
        <div class="bill-alert bill-alert--warn">
            <div class="bill-alert-icon"><i class="fa fa-clock" aria-hidden="true" style="font-size:12px;"></i></div>
            <div style="flex:1;min-width:0;">
                <div style="color:#92400e;font-weight:700;">Your subscription payment is due</div>
                <div style="color:#b45309;font-size:13px;margin-top:2px;">
                    Pay {{ strtoupper($paymentDue['currency'] ?? '') }} {{ number_format((float) $paymentDue['amount'], 2) }}
                    by {{ \Illuminate\Support\Carbon::parse($paymentDue['due_at'])->format('d M Y, g:i A') }}
                    ({{ \Illuminate\Support\Carbon::parse($paymentDue['due_at'])->diffForHumans(['parts' => 2]) }}).
                    After that, access to your workspace is locked until it's settled.
                </div>
                @if($paymentDue['can_pay'])
                    <form method="post" action="{{ route('payment.checkout.resume', ['payment' => $paymentDue['payment_id']]) }}" style="margin-top:10px;">
                        @csrf
                        <button type="submit" class="bill-btn bill-btn--warn">
                            <i class="fa fa-credit-card" aria-hidden="true"></i> Pay now
                        </button>
                    </form>
                @else
                    <div style="color:#92400e;font-size:13px;margin-top:6px;">Contact your business owner to settle this payment.</div>
                @endif
            </div>
        </div>
    @elseif($summary['subscription_ended'] ?? false)
        <div class="bill-alert">
            <div class="bill-alert-icon">!</div>
            <div>
                <div style="color:#991b1b;font-weight:700;">Your subscription has ended</div>
                <div style="color:#b91c1c;font-size:13px;margin-top:2px;">Renew to regain access to your workspace.</div>
            </div>
        </div>
    @endif

    <div class="bill-card">
        <div class="bill-summary-row">
            <div>
                <span class="bill-status-pill" style="background:color-mix(in srgb, {{ $statusInfo['color'] }} 16%, transparent);color:{{ $statusInfo['color'] }};">
                    <i class="fa fa-circle" style="font-size:7px;" aria-hidden="true"></i> Subscription: {{ $statusInfo['label'] }}
                </span>
                @if($summary['next_renewal_at'] ?? null)
                    <div class="bill-renewal">
                        @if($summary['cancel_at_period_end'])
                            Access until {{ \Illuminate\Support\Carbon::parse($summary['next_renewal_at'])->format('F j, Y') }} — cancellation scheduled
                        @else
                            Next renewal on {{ \Illuminate\Support\Carbon::parse($summary['next_renewal_at'])->format('F j, Y') }}
                        @endif
                    </div>
                @endif
            </div>
            @if($summary['can_manage_subscription'] ?? false)
                <div>
                    @if($summary['cancel_at_period_end'])
                        <form method="post" action="{{ route('payment.billing.resume') }}">
                            @csrf
                            <button type="submit" class="bill-btn">
                                <i class="fa fa-rotate-left" aria-hidden="true"></i> Resume subscription
                            </button>
                        </form>
                    @else
                        <form method="post" action="{{ route('payment.billing.cancel') }}" onsubmit="return confirm('Cancel your subscription at the end of the current billing period?');">
                            @csrf
                            <button type="submit" class="bill-btn bill-btn--danger">
                                <i class="fa fa-ban" aria-hidden="true"></i> Cancel subscription
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="bill-card">
        <div class="bill-tabs">
            <button type="button" class="bill-tab is-active" data-bill-tab="paid">Paid <span class="bill-tab-count">{{ $paidItems->count() }}</span></button>
            <button type="button" class="bill-tab" data-bill-tab="due">Due &amp; Upcoming <span class="bill-tab-count">{{ $dueItems->count() }}</span></button>
        </div>

        <div data-bill-panel="paid">
            @forelse($paidItems as $item)
                <div class="bill-row">
                    <div>
                        <div class="bill-row-plan">{{ $item['plan'] ?? 'Subscription' }}</div>
                        <div class="bill-row-date">{{ $item['paid_at'] ? \Illuminate\Support\Carbon::parse($item['paid_at'])->format('F j, Y') : '—' }} · {{ $item['billing_cycle'] ?? 'one-time' }}</div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span class="bill-badge bill-badge--succeeded">Paid</span>
                        <span style="font-weight:700;font-size:13px;">{{ strtoupper($item['currency'] ?? 'USD') }} {{ number_format((float) ($item['amount'] ?? 0), 2) }}</span>
                        <a href="{{ route('payment.billing.receipt', $item['id']) }}" class="bill-btn" style="padding:6px 12px;">
                            <i class="fa fa-download" aria-hidden="true"></i> Receipt
                        </a>
                    </div>
                </div>
            @empty
                <div class="bill-empty">No paid invoices yet.</div>
            @endforelse
        </div>

        <div data-bill-panel="due" style="display:none;">
            @forelse($dueItems as $item)
                <div class="bill-row">
                    <div>
                        <div class="bill-row-plan">{{ $item['plan'] ?? 'Subscription' }}</div>
                        <div class="bill-row-date">
                            {{ $item['due_at'] ? 'Due ' . \Illuminate\Support\Carbon::parse($item['due_at'])->format('F j, Y') : \Illuminate\Support\Carbon::parse($item['created_at'])->format('F j, Y') }}
                            @if($item['failure_reason']) · {{ $item['failure_reason'] }} @endif
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span class="bill-badge bill-badge--{{ $item['payment_status'] }}">{{ ucfirst($item['payment_status']) }}</span>
                        <span style="font-weight:700;font-size:13px;">{{ strtoupper($item['currency'] ?? 'USD') }} {{ number_format((float) ($item['amount'] ?? 0), 2) }}</span>
                        <form method="post" action="{{ route('payment.checkout.resume', ['payment' => $item['id']]) }}">
                            @csrf
                            <button type="submit" class="bill-btn bill-btn--primary" style="padding:6px 12px;">Pay now</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="bill-empty">Nothing due — you're all caught up.</div>
            @endforelse
        </div>
    </div>
@endif

<script>
(function () {
    var tabs = document.querySelectorAll('[data-bill-tab]');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = tab.getAttribute('data-bill-tab');
            tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); });
            document.querySelectorAll('[data-bill-panel]').forEach(function (panel) {
                panel.style.display = panel.getAttribute('data-bill-panel') === target ? '' : 'none';
            });
        });
    });
})();
</script>
@endsection
