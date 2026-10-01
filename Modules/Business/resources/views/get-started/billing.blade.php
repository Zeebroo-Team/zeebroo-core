@extends('theme::layouts.app', [
    'title' => 'Billing',
    'heading' => 'Billing',
    'minimalAppShell' => true,
    'hideNavbar' => true,
])

@section('content')
@include('business::get-started.partials.topnav', ['gsActive' => 'billing'])
@php
    $blMoney = function ($amount, ?string $currency): string {
        $code = strtoupper($currency ?: 'USD');
        $symbol = match ($code) { 'USD' => '$', 'LKR' => 'Rs. ', default => $code.' ' };

        return $symbol.number_format((float) $amount, 2);
    };
    $blStatusLabels = [
        'active' => ['label' => 'Active', 'tone' => 'ok'],
        'trialing' => ['label' => 'Trial', 'tone' => 'ok'],
        'past_due' => ['label' => 'Past due', 'tone' => 'warn'],
        'canceled' => ['label' => 'Canceled', 'tone' => 'off'],
        'pending_payment' => ['label' => 'Payment pending', 'tone' => 'bad'],
    ];
    $blSubStatus = $summary['subscription_status'] ?? null;
    $blStatus = $blStatusLabels[$blSubStatus] ?? [
        'label' => $blSubStatus ? ucfirst(str_replace('_', ' ', $blSubStatus)) : ($package?->is_free ? 'Free trial' : 'No subscription'),
        'tone' => 'off',
    ];
    if (($summary['cancel_at_period_end'] ?? false) && $blSubStatus === 'active') {
        $blStatus = ['label' => 'Cancels at period end', 'tone' => 'warn'];
    }
    $blOverdue = $summary['overdue'] ?? null;
    $blDue = $summary['payment_due'] ?? null;
    $blItems = collect($summary['items'] ?? []);
    $blPaid = $blItems->where('payment_status', 'succeeded')->values();
    $blOpen = $blItems->whereIn('payment_status', ['pending', 'failed', 'canceled'])->values();
    $blLatest = $blItems->first();
    $blCurrency = $blLatest['currency'] ?? $package?->currency ?? 'USD';
    $blPrice = $package ? ((float) ($package->discounted_price ?: $package->price)) : null;
    $blCycle = $blLatest['billing_cycle'] ?? 'monthly';
    $blRenewal = $summary['next_renewal_at'] ?? null;
    $blGateway = $blLatest['gateway'] ?? null;
    $blTotalPaid = $blPaid->sum('amount');
@endphp
<style>
    .bl-wrap{max-width:1000px;margin:0 auto;}
    .bl-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:clamp(8px,3vh,28px) 0 22px;}
    .bl-eyebrow{
        display:inline-flex;align-items:center;gap:7px;padding:5px 13px;border-radius:999px;margin-bottom:12px;
        background:color-mix(in srgb,var(--gs-gold) 10%,#fff);color:#8a6510;border:1px solid color-mix(in srgb,var(--gs-gold) 30%,transparent);
        font-size:11.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;
    }
    .bl-title{margin:0;font-size:clamp(26px,3.4vw,34px);font-weight:800;color:var(--text);letter-spacing:-.03em;}
    .bl-sub{margin:6px 0 0;font-size:14px;color:var(--muted);}
    .bl-help{font-size:13px;color:var(--muted);text-decoration:none;font-weight:600;}
    .bl-help:hover{color:var(--text);}

    .bl-flash{
        display:flex;align-items:center;gap:12px;padding:11px 12px 11px 16px;border-radius:12px;margin-bottom:14px;
        border:1px solid var(--border);background:#fff;font-size:13px;line-height:1.45;color:var(--muted);
    }
    .bl-flash-dot{width:8px;height:8px;border-radius:999px;flex-shrink:0;background:var(--d,#16a34a);box-shadow:0 0 0 4px color-mix(in srgb,var(--d,#16a34a) 14%,transparent);}
    .bl-flash--bad{--d:#e11d48;}
    .bl-flash--warn{--d:#d97706;}
    .bl-flash-body{flex:1;min-width:0;}
    .bl-flash-body strong{color:var(--text);font-weight:700;}
    .bl-flash form{margin:0;flex-shrink:0;}

    .bl-btn{
        display:inline-flex;align-items:center;justify-content:center;gap:7px;white-space:nowrap;
        padding:9px 16px;border-radius:10px;border:1px solid transparent;cursor:pointer;font-family:inherit;
        font-size:12.5px;font-weight:700;text-decoration:none;transition:opacity .16s,background .16s,border-color .16s,color .16s;
    }
    .bl-btn--dark{background:#0a0a0a;color:#fff;}
    .bl-btn--dark:hover{opacity:.85;color:#fff;}
    .bl-btn--line{background:#fff;color:var(--text);border-color:var(--border);}
    .bl-btn--line:hover{border-color:#0a0a0a;}
    .bl-btn--sm{padding:6px 11px;font-size:12px;border-radius:9px;}

    .bl-top{display:grid;grid-template-columns:1.35fr 1fr;gap:16px;margin-bottom:16px;}
    @media(max-width:820px){.bl-top{grid-template-columns:1fr;}}

    .bl-plan{
        position:relative;overflow:hidden;border-radius:20px;padding:24px;color:var(--text);background:rgba(255,255,255,.45);
        -webkit-backdrop-filter:blur(12px) saturate(160%);backdrop-filter:blur(12px) saturate(160%);
        border:1.5px solid var(--gs-gold);
        box-shadow:0 14px 34px -18px color-mix(in srgb,var(--gs-gold) 55%,transparent);
        display:flex;flex-direction:column;gap:18px;min-height:200px;
    }
    .bl-plan::after{
        content:'';position:absolute;right:-70px;top:-70px;width:220px;height:220px;border-radius:999px;pointer-events:none;
        background:radial-gradient(circle,color-mix(in srgb,var(--gs-gold) 14%,transparent),transparent 70%);
    }
    .bl-plan > *{position:relative;z-index:1;}
    .bl-plan-head{display:flex;align-items:center;justify-content:space-between;gap:12px;}
    .bl-plan-label{font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#8a6510;display:flex;align-items:center;gap:7px;}
    .bl-plan-label i{color:var(--gs-gold);}
    .bl-plan-name{font-size:clamp(24px,3vw,30px);font-weight:800;letter-spacing:-.02em;margin-top:6px;}
    .bl-plan-price{font-size:15px;color:var(--muted);margin-top:4px;}
    .bl-plan-price strong{color:var(--text);font-size:20px;font-weight:800;}
    .bl-plan-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:auto;padding-top:16px;border-top:1px solid var(--border);font-size:13px;color:var(--muted);}
    .bl-plan-foot i{color:var(--gs-gold);}
    .bl-plan-foot form{margin:0;}
    .bl-plan .bl-btn--line.is-danger:hover{border-color:#ef4444;color:#ef4444;}

    .bl-pill{display:inline-flex;align-items:center;gap:6px;padding:4px 11px;border-radius:999px;font-size:11.5px;font-weight:700;white-space:nowrap;}
    .bl-pill::before{content:'';width:6px;height:6px;border-radius:999px;background:currentColor;}
    .bl-pill--ok{background:#f0fdf4;color:#15803d;}
    .bl-pill--warn{background:#fffbeb;color:#b45309;}
    .bl-pill--bad{background:#fff1f2;color:#e11d48;}
    .bl-pill--off{background:var(--gs-soft);color:var(--muted);}

    .bl-card{border:1px solid var(--border);border-radius:20px;background:rgba(255,255,255,.45);padding:22px;
        -webkit-backdrop-filter:blur(12px) saturate(160%);backdrop-filter:blur(12px) saturate(160%);}
    .bl-card-title{margin:0 0 14px;font-size:14px;font-weight:800;color:var(--text);}
    .bl-facts{display:flex;flex-direction:column;}
    .bl-fact{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:11px 0;font-size:13px;}
    .bl-fact + .bl-fact{border-top:1px solid var(--border);}
    .bl-fact span{color:var(--muted);}
    .bl-fact strong{color:var(--text);font-weight:700;text-align:right;}

    .bl-tabs{display:inline-flex;gap:4px;padding:4px;border-radius:12px;background:var(--gs-soft);margin-bottom:8px;}
    .bl-tab{
        -webkit-appearance:none;appearance:none;border:none;background:none;cursor:pointer;font-family:inherit;
        padding:7px 14px;border-radius:9px;font-size:12.5px;font-weight:700;color:var(--muted);
    }
    .bl-tab:hover{color:var(--text);background:none;transform:none;}
    .bl-tab.is-active{background:#fff;color:var(--text);box-shadow:0 1px 3px rgba(0,0,0,.08);}
    .bl-tab-count{margin-left:6px;font-size:11px;color:var(--muted);}
    .bl-hist-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:6px;}
    .bl-row{display:grid;grid-template-columns:1fr auto auto auto;align-items:center;gap:14px;padding:14px 2px;border-top:1px solid var(--border);}
    .bl-row-plan{font-size:13.5px;font-weight:700;color:var(--text);}
    .bl-row-meta{font-size:12px;color:var(--muted);margin-top:2px;}
    .bl-row-amount{font-size:13.5px;font-weight:800;color:var(--text);text-align:right;}
    .bl-row form{margin:0;}
    .bl-badge{padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;}
    .bl-badge--succeeded{background:#f0fdf4;color:#15803d;}
    .bl-badge--pending{background:#fffbeb;color:#b45309;}
    .bl-badge--failed{background:#fff1f2;color:#e11d48;}
    .bl-badge--canceled{background:var(--gs-soft);color:var(--muted);}
    .bl-empty{padding:30px 0 12px;text-align:center;color:var(--muted);font-size:13px;border-top:1px solid var(--border);}
    .bl-empty i{display:block;font-size:20px;margin-bottom:8px;color:#d4d4d4;}
    @media(max-width:600px){
        .bl-row{grid-template-columns:1fr auto;}
        .bl-row .bl-badge{grid-column:1;justify-self:start;}
    }
</style>
<div class="gs-shell">
    <div class="bl-wrap">
        <div class="bl-hero">
            <div>
                <span class="bl-eyebrow"><i class="fa fa-credit-card" aria-hidden="true"></i> Billing</span>
                <h1 class="bl-title">Plan &amp; billing</h1>
                <p class="bl-sub">Manage your subscription, payments and receipts.</p>
            </div>
            <a href="{{ route('business.get-started.support') }}" class="bl-help"><i class="fa fa-headset" aria-hidden="true"></i> Billing question? Contact support</a>
        </div>

        @if(session('status'))
            <div class="bl-flash"><span class="bl-flash-dot"></span><div class="bl-flash-body"><strong>{{ session('status') }}</strong></div></div>
        @endif
        @if($errors->has('payment'))
            <div class="bl-flash bl-flash--bad"><span class="bl-flash-dot"></span><div class="bl-flash-body"><strong>{{ $errors->first('payment') }}</strong></div></div>
        @endif

        @if(! $business)
            <div class="bl-card bl-empty" style="border-top:1px solid var(--border);">
                <i class="fa fa-briefcase" aria-hidden="true"></i> No business selected yet — choose or create a business first.
            </div>
        @else
            @if($blOverdue)
                <div class="bl-flash bl-flash--bad" role="alert">
                    <span class="bl-flash-dot"></span>
                    <div class="bl-flash-body">
                        <strong>Your subscription payment is overdue.</strong>
                        Access is locked until it's settled
                        @if($blOverdue['due_at']) — it was due {{ \Illuminate\Support\Carbon::parse($blOverdue['due_at'])->format('d M Y') }} @endif
                    </div>
                    @if($blOverdue['can_pay'])
                        <form method="post" action="{{ route('payment.checkout.resume', ['payment' => $blOverdue['payment_id']]) }}">
                            @csrf
                            <button type="submit" class="bl-btn bl-btn--dark">Complete payment</button>
                        </form>
                    @endif
                </div>
            @elseif($blDue)
                <div class="bl-flash bl-flash--warn" role="alert">
                    <span class="bl-flash-dot"></span>
                    <div class="bl-flash-body">
                        <strong>Payment of {{ $blMoney($blDue['amount'], $blDue['currency']) }} is due
                            {{ \Illuminate\Support\Carbon::parse($blDue['due_at'])->diffForHumans(['parts' => 2]) }}.</strong>
                        @if($blDue['can_pay'])
                            Pay by {{ \Illuminate\Support\Carbon::parse($blDue['due_at'])->format('d M Y, g:i A') }} to keep your workspace unlocked.
                        @else
                            Ask your business owner to settle it.
                        @endif
                    </div>
                    @if($blDue['can_pay'])
                        <form method="post" action="{{ route('payment.checkout.resume', ['payment' => $blDue['payment_id']]) }}">
                            @csrf
                            <button type="submit" class="bl-btn bl-btn--dark">Pay now</button>
                        </form>
                    @endif
                </div>
            @elseif($summary['subscription_ended'] ?? false)
                <div class="bl-flash bl-flash--bad" role="alert">
                    <span class="bl-flash-dot"></span>
                    <div class="bl-flash-body"><strong>Your subscription has ended.</strong> Renew to regain access to your workspace.</div>
                </div>
            @endif

            <div class="bl-top">
                <div class="bl-plan">
                    <div class="bl-plan-head">
                        <span class="bl-plan-label"><i class="fa fa-crown" aria-hidden="true"></i> Current plan</span>
                        <span class="bl-pill bl-pill--{{ $blStatus['tone'] }}">{{ $blStatus['label'] }}</span>
                    </div>
                    <div>
                        <div class="bl-plan-name">{{ $package?->name ?? 'No plan' }}</div>
                        @if($package?->is_free)
                            <div class="bl-plan-price"><strong>Free</strong> trial</div>
                        @elseif($blPrice !== null)
                            <div class="bl-plan-price"><strong>{{ $blMoney($blPrice, $package->currency ?: $blCurrency) }}</strong> / {{ $blCycle === 'yearly' ? 'year' : 'month' }}</div>
                        @endif
                    </div>
                    <div class="bl-plan-foot">
                        <span>
                            @if($blRenewal)
                                @if($summary['cancel_at_period_end'])
                                    <i class="fa fa-calendar-xmark" aria-hidden="true"></i> Access until {{ \Illuminate\Support\Carbon::parse($blRenewal)->format('F j, Y') }}
                                @else
                                    <i class="fa fa-calendar" aria-hidden="true"></i> Renews {{ \Illuminate\Support\Carbon::parse($blRenewal)->format('F j, Y') }}
                                @endif
                            @else
                                <i class="fa fa-calendar" aria-hidden="true"></i> No upcoming renewal
                            @endif
                        </span>
                        @if($summary['can_manage_subscription'] ?? false)
                            @if($summary['cancel_at_period_end'])
                                <form method="post" action="{{ route('payment.billing.resume') }}">
                                    @csrf
                                    <input type="hidden" name="return_to" value="get-started">
                                    <button type="submit" class="bl-btn bl-btn--line"><i class="fa fa-rotate-left" aria-hidden="true"></i> Resume subscription</button>
                                </form>
                            @else
                                <form method="post" action="{{ route('payment.billing.cancel') }}" onsubmit="return confirm('Cancel your subscription at the end of the current billing period?');">
                                    @csrf
                                    <input type="hidden" name="return_to" value="get-started">
                                    <button type="submit" class="bl-btn bl-btn--line is-danger">Cancel subscription</button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="bl-card">
                    <h2 class="bl-card-title">At a glance</h2>
                    <div class="bl-facts">
                        <div class="bl-fact"><span>Business</span><strong>{{ $business->name }}</strong></div>
                        <div class="bl-fact"><span>Billing cycle</span><strong>{{ ucfirst($blCycle ?: 'monthly') }}</strong></div>
                        <div class="bl-fact"><span>Payment method</span><strong>{{ $blGateway ? 'Card · '.ucfirst($blGateway) : '—' }}</strong></div>
                        <div class="bl-fact"><span>Total paid</span><strong>{{ $blMoney($blTotalPaid, $blCurrency) }}</strong></div>
                        <div class="bl-fact"><span>Invoices</span><strong>{{ $blPaid->count() }} paid · {{ $blOpen->count() }} open</strong></div>
                    </div>
                </div>
            </div>

            <div class="bl-card">
                <div class="bl-hist-head">
                    <h2 class="bl-card-title" style="margin:0;">Payment history</h2>
                    <div class="bl-tabs" role="tablist">
                        <button type="button" class="bl-tab is-active" data-bl-tab="paid" role="tab">Paid<span class="bl-tab-count">{{ $blPaid->count() }}</span></button>
                        <button type="button" class="bl-tab" data-bl-tab="open" role="tab">Due &amp; open<span class="bl-tab-count">{{ $blOpen->count() }}</span></button>
                    </div>
                </div>

                <div data-bl-panel="paid">
                    @forelse($blPaid as $item)
                        <div class="bl-row">
                            <div>
                                <div class="bl-row-plan">{{ $item['plan'] ?? 'Subscription' }}</div>
                                <div class="bl-row-meta">{{ $item['paid_at'] ? \Illuminate\Support\Carbon::parse($item['paid_at'])->format('M j, Y') : '—' }} · {{ ucfirst($item['billing_cycle'] ?? 'one-time') }}</div>
                            </div>
                            <span class="bl-badge bl-badge--succeeded">Paid</span>
                            <span class="bl-row-amount">{{ $blMoney($item['amount'] ?? 0, $item['currency'] ?? null) }}</span>
                            <a href="{{ route('payment.billing.receipt', $item['id']) }}" class="bl-btn bl-btn--line bl-btn--sm">
                                <i class="fa fa-download" aria-hidden="true"></i> Receipt
                            </a>
                        </div>
                    @empty
                        <div class="bl-empty"><i class="fa fa-receipt" aria-hidden="true"></i> No paid invoices yet.</div>
                    @endforelse
                </div>

                <div data-bl-panel="open" hidden>
                    @forelse($blOpen as $item)
                        <div class="bl-row">
                            <div>
                                <div class="bl-row-plan">{{ $item['plan'] ?? 'Subscription' }}</div>
                                <div class="bl-row-meta">
                                    {{ $item['due_at'] ? 'Due '.\Illuminate\Support\Carbon::parse($item['due_at'])->format('M j, Y') : \Illuminate\Support\Carbon::parse($item['created_at'])->format('M j, Y') }}
                                    @if($item['failure_reason']) · {{ $item['failure_reason'] }} @endif
                                </div>
                            </div>
                            <span class="bl-badge bl-badge--{{ $item['payment_status'] }}">{{ ucfirst($item['payment_status']) }}</span>
                            <span class="bl-row-amount">{{ $blMoney($item['amount'] ?? 0, $item['currency'] ?? null) }}</span>
                            <form method="post" action="{{ route('payment.checkout.resume', ['payment' => $item['id']]) }}">
                                @csrf
                                <button type="submit" class="bl-btn bl-btn--dark bl-btn--sm">Pay now</button>
                            </form>
                        </div>
                    @empty
                        <div class="bl-empty"><i class="fa fa-circle-check" aria-hidden="true"></i> Nothing due — you're all caught up.</div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
</div>
<script>
(function () {
    var tabs = document.querySelectorAll('[data-bl-tab]');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = tab.getAttribute('data-bl-tab');
            tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); });
            document.querySelectorAll('[data-bl-panel]').forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-bl-panel') !== target;
            });
        });
    });
})();
</script>
@endsection
