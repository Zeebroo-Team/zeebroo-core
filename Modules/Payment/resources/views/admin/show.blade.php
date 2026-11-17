@extends('theme::layouts.app', ['title' => 'Payment #'.$payment->id, 'heading' => 'Payment #'.$payment->id])

@section('content')
<style>
.apd-wrap{max-width:1100px;margin:0 auto;}
.apd-back{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);text-decoration:none;margin-bottom:10px;}
.apd-back:hover{color:var(--primary);}
.apd-hero{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:22px 24px;border:1px solid var(--border);border-radius:16px;background:var(--card);margin-bottom:20px;}
.apd-hero-left{display:flex;align-items:center;gap:16px;}
.apd-hero-icon{width:52px;height:52px;border-radius:14px;display:grid;place-items:center;font-size:20px;background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);flex-shrink:0;}
.apd-amount{margin:0;font-size:28px;font-weight:800;letter-spacing:-.03em;line-height:1.1;}
.apd-hero-sub{margin:4px 0 0;font-size:13px;color:var(--muted);}
.apd-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:999px;font-size:12px;font-weight:700;text-transform:capitalize;}
.apd-badge--succeeded{background:color-mix(in srgb,#22c55e 14%,transparent);color:#16a34a;}
.apd-badge--pending,.apd-badge--processing{background:color-mix(in srgb,#f59e0b 15%,transparent);color:#b45309;}
.apd-badge--failed,.apd-badge--canceled{background:color-mix(in srgb,#ef4444 14%,transparent);color:#dc2626;}
.apd-badge--refunded{background:color-mix(in srgb,#64748b 15%,transparent);color:#64748b;}
.apd-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
@media(max-width:820px){.apd-grid{grid-template-columns:1fr;}}
.apd-card{border:1px solid var(--border);border-radius:16px;background:var(--card);overflow:hidden;}
.apd-card-head{padding:14px 20px;border-bottom:1px solid var(--border);font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px;}
.apd-card-body{padding:6px 20px;}
.apd-row{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;padding:11px 0;border-bottom:1px solid color-mix(in srgb,var(--border) 55%,transparent);}
.apd-row:last-child{border-bottom:none;}
.apd-label{font-size:12px;font-weight:600;color:var(--muted);flex-shrink:0;}
.apd-value{font-size:13px;font-weight:600;text-align:right;min-width:0;overflow-wrap:anywhere;}
.apd-value.mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;font-weight:500;}
.apd-value a{color:var(--primary);text-decoration:none;}
.apd-value a:hover{text-decoration:underline;}
.apd-copy{border:none;background:transparent;color:var(--muted);cursor:pointer;padding:0 0 0 6px;font-size:11px;}
.apd-copy:hover{color:var(--primary);}
.apd-alert{display:flex;gap:10px;padding:13px 16px;border-radius:12px;margin-bottom:20px;font-size:13px;border:1px solid color-mix(in srgb,#ef4444 38%,var(--border));background:color-mix(in srgb,#ef4444 8%,var(--card));color:#b91c1c;}
.apd-note{display:flex;gap:10px;padding:12px 16px;border-radius:12px;margin-bottom:20px;font-size:12.5px;color:var(--muted);border:1px solid var(--border);background:color-mix(in srgb,var(--primary) 4%,var(--card));}
.apd-pre{margin:0;padding:14px 20px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;white-space:pre-wrap;overflow-wrap:anywhere;}
.apd-table{width:100%;border-collapse:collapse;}
.apd-table th{padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);background:color-mix(in srgb,var(--card) 88%,var(--border));text-align:left;border-bottom:1px solid var(--border);}
.apd-table td{padding:11px 16px;font-size:13px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);}
.apd-table tr:last-child td{border-bottom:none;}
.apd-table tr.apd-link-row{cursor:pointer;}
.apd-table tr.apd-link-row:hover td{background:color-mix(in srgb,var(--primary) 4%,transparent);}
</style>

@php
    $currency = strtoupper($payment->currency ?? 'USD');
    $fmt = fn ($date) => $date ? $date->format('d M Y, H:i') : '—';
    // Succeeded/refunded payments have no outstanding deadline, even if an
    // earlier canceled/failed attempt left failure_reason/due_at behind.
    $isSettled = in_array($payment->payment_status, ['succeeded', 'refunded'], true);
    $platformLabel = match ($payment->platform) {
        null, '' => '—',
        'pos_lite' => 'POS Lite',
        default => ucwords(str_replace('_', ' ', $payment->platform)),
    };
    $stripeIds = [
        'Customer ID' => $payment->stripe_customer_id,
        'Checkout session ID' => $payment->stripe_checkout_session_id,
        'Subscription ID' => $payment->stripe_subscription_id,
        'Payment intent ID' => $payment->stripe_payment_intent_id,
        'Invoice ID' => $payment->stripe_invoice_id,
    ];
@endphp

<div class="apd-wrap">
    <a href="{{ route('admin.payments.index') }}" class="apd-back"><i class="fa fa-arrow-left"></i> Payment History</a>

    <div class="apd-hero">
        <div class="apd-hero-left">
            <div class="apd-hero-icon"><i class="fa fa-receipt"></i></div>
            <div>
                <p class="apd-amount">{{ $currency }} {{ number_format((float) $payment->amount, 2) }}</p>
                <p class="apd-hero-sub">
                    Payment #{{ $payment->id }} · {{ $payment->package?->name ?? 'No package' }}
                    · {{ $payment->business?->name ?? 'Unknown business' }}
                </p>
            </div>
        </div>
        <span class="apd-badge apd-badge--{{ $payment->payment_status }}">
            <i class="fa fa-{{ match ($payment->payment_status) { 'succeeded' => 'circle-check', 'failed', 'canceled' => 'circle-xmark', 'refunded' => 'rotate-left', default => 'clock' } }}"></i>
            {{ $payment->payment_status }}
        </span>
    </div>

    @if($payment->failure_reason && $isSettled)
        <div class="apd-note">
            <i class="fa fa-circle-info" style="margin-top:2px;"></i>
            <div>An earlier attempt did not complete ({{ $payment->failure_reason }}) — it was later paid successfully on {{ $fmt($payment->paid_at) }}.</div>
        </div>
    @elseif($payment->failure_reason)
        <div class="apd-alert">
            <i class="fa fa-circle-exclamation" style="margin-top:2px;"></i>
            <div>
                <strong>Failure reason:</strong> {{ $payment->failure_reason }}
                @if($payment->due_at)
                    <div style="margin-top:3px;font-size:12px;">
                        Grace period {{ $payment->isOverdue() ? 'ended' : 'ends' }} {{ $fmt($payment->due_at) }} ({{ $payment->due_at->diffForHumans() }})
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="apd-grid">
        <div class="apd-card">
            <div class="apd-card-head"><i class="fa fa-file-invoice-dollar" style="color:var(--primary);"></i> Payment</div>
            <div class="apd-card-body">
                <div class="apd-row"><span class="apd-label">Amount</span><span class="apd-value">{{ $currency }} {{ number_format((float) $payment->amount, 2) }}</span></div>
                <div class="apd-row"><span class="apd-label">Status</span><span class="apd-value" style="text-transform:capitalize;">{{ $payment->payment_status }}</span></div>
                <div class="apd-row"><span class="apd-label">Type</span><span class="apd-value" style="text-transform:capitalize;">{{ $payment->payment_type ?: '—' }}</span></div>
                <div class="apd-row"><span class="apd-label">Package</span><span class="apd-value">{{ $payment->package?->name ?? '—' }}</span></div>
                <div class="apd-row"><span class="apd-label">Billing cycle</span><span class="apd-value" style="text-transform:capitalize;">{{ $payment->billing_cycle ?: '—' }}</span></div>
                <div class="apd-row"><span class="apd-label">Gateway</span><span class="apd-value" style="text-transform:capitalize;">{{ $payment->gateway ?: '—' }}</span></div>
                <div class="apd-row"><span class="apd-label">Platform</span><span class="apd-value">{{ $platformLabel }}</span></div>
                <div class="apd-row"><span class="apd-label">Created</span><span class="apd-value">{{ $fmt($payment->created_at) }}</span></div>
                <div class="apd-row"><span class="apd-label">Paid at</span><span class="apd-value">{{ $fmt($payment->paid_at) }}</span></div>
                @unless($isSettled)
                    <div class="apd-row"><span class="apd-label">Due at</span><span class="apd-value">{{ $fmt($payment->due_at) }}</span></div>
                @endunless
                <div class="apd-row"><span class="apd-label">Last updated</span><span class="apd-value">{{ $fmt($payment->updated_at) }}</span></div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:20px;">
            <div class="apd-card">
                <div class="apd-card-head"><i class="fa fa-user" style="color:var(--primary);"></i> Customer</div>
                <div class="apd-card-body">
                    <div class="apd-row"><span class="apd-label">Business</span><span class="apd-value">{{ $payment->business?->name ?? '—' }}</span></div>
                    <div class="apd-row">
                        <span class="apd-label">User</span>
                        <span class="apd-value">
                            @if($payment->user)
                                <a href="{{ route('admin.users.show', $payment->user) }}">{{ $payment->user->name }}</a>
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    <div class="apd-row"><span class="apd-label">Email</span><span class="apd-value">{{ $payment->user?->email ?? '—' }}</span></div>
                </div>
            </div>

            <div class="apd-card">
                <div class="apd-card-head"><i class="fa fa-arrows-rotate" style="color:var(--primary);"></i> Subscription</div>
                <div class="apd-card-body">
                    <div class="apd-row"><span class="apd-label">Stripe status</span><span class="apd-value" style="text-transform:capitalize;">{{ $payment->stripe_subscription_status ? str_replace('_', ' ', $payment->stripe_subscription_status) : '—' }}</span></div>
                    <div class="apd-row"><span class="apd-label">Current period ends</span><span class="apd-value">{{ $fmt($payment->current_period_end) }}</span></div>
                    <div class="apd-row"><span class="apd-label">Cancels at period end</span><span class="apd-value">{{ $payment->cancel_at_period_end ? 'Yes' : 'No' }}</span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="apd-card" style="margin-bottom:20px;">
        <div class="apd-card-head"><i class="fa fa-brands fa-stripe-s" style="color:#635bff;"></i> Gateway references</div>
        <div class="apd-card-body">
            @foreach($stripeIds as $label => $value)
                <div class="apd-row">
                    <span class="apd-label">{{ $label }}</span>
                    <span class="apd-value mono">
                        {{ $value ?: '—' }}
                        @if($value)<button type="button" class="apd-copy" data-copy="{{ $value }}" title="Copy"><i class="fa fa-copy"></i></button>@endif
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    @if(! empty($payment->metadata))
        <div class="apd-card" style="margin-bottom:20px;">
            <div class="apd-card-head"><i class="fa fa-code" style="color:var(--primary);"></i> Metadata</div>
            <pre class="apd-pre">{{ json_encode($payment->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    @endif

    <div class="apd-card" style="margin-bottom:28px;">
        <div class="apd-card-head"><i class="fa fa-clock-rotate-left" style="color:var(--primary);"></i> Other payments from {{ $payment->business?->name ?? 'this business' }}</div>
        @if($related->isEmpty())
            <div style="padding:24px 20px;font-size:13px;color:var(--muted);text-align:center;">No other payments for this business.</div>
        @else
            <div style="overflow-x:auto;">
                <table class="apd-table">
                    <thead>
                        <tr><th>#</th><th>Package</th><th>Amount</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        @foreach($related as $r)
                            <tr class="apd-link-row" data-href="{{ route('admin.payments.show', $r->id) }}">
                                <td>#{{ $r->id }}</td>
                                <td>{{ $r->package?->name ?? '—' }}</td>
                                <td>{{ strtoupper($r->currency ?? 'USD') }} {{ number_format((float) $r->amount, 2) }}</td>
                                <td><span class="apd-badge apd-badge--{{ $r->payment_status }}" style="padding:2px 9px;font-size:11px;">{{ $r->payment_status }}</span></td>
                                <td>{{ $fmt($r->paid_at ?? $r->created_at) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<script>
document.querySelectorAll('.apd-link-row').forEach(function (row) {
    row.addEventListener('click', function () { window.location.href = row.getAttribute('data-href'); });
});
document.querySelectorAll('.apd-copy').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var icon = btn.querySelector('i');
        navigator.clipboard && navigator.clipboard.writeText(btn.getAttribute('data-copy')).then(function () {
            icon.className = 'fa fa-check';
            setTimeout(function () { icon.className = 'fa fa-copy'; }, 1200);
        });
    });
});
</script>
@endsection
