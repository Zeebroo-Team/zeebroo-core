@extends('theme::layouts.app', ['title' => 'Payment Checks', 'heading' => 'Payment Checks'])

@section('content')
<style>
.apc-wrap{max-width:1280px;margin:0 auto;}
.apc-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px;}
.apc-title{margin:0;font-size:22px;font-weight:800;letter-spacing:-.025em;}
.apc-sub{margin:4px 0 0;font-size:13px;color:var(--muted);max-width:720px;}
.apc-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 14px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;text-decoration:none;}
.apc-btn:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);}
.apc-btn--primary{background:var(--primary);border-color:var(--primary);color:#fff;}
.apc-btn--primary:hover{background:color-mix(in srgb,var(--primary) 88%,#000);color:#fff;}
.apc-btn[disabled]{opacity:.6;cursor:wait;}
.apc-msg{display:flex;align-items:center;gap:8px;padding:11px 14px;border-radius:12px;margin-bottom:16px;font-size:13px;border:1px solid var(--border);background:var(--card);}
.apc-msg--error{border-color:color-mix(in srgb,#ef4444 40%,var(--border));background:color-mix(in srgb,#ef4444 7%,var(--card));}
.apc-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:20px;}
@media(max-width:960px){.apc-grid{grid-template-columns:1fr;}}
.apc-card{border:1px solid var(--border);border-radius:16px;background:var(--card);padding:16px 18px;}
.apc-card h3{margin:0 0 10px;font-size:13px;font-weight:750;display:flex;align-items:center;gap:8px;}
.apc-kv{display:flex;justify-content:space-between;gap:10px;font-size:12.5px;padding:4px 0;color:var(--muted);}
.apc-kv b{color:var(--text);font-weight:650;text-align:right;}
.apc-form-row{display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:13px;flex-wrap:wrap;}
.apc-form-row input[type=number]{width:90px;padding:8px 10px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-family:inherit;font-size:13px;}
.apc-presets{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;}
.apc-preset{padding:3px 10px;border-radius:999px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:11.5px;cursor:pointer;font-family:inherit;}
.apc-preset:hover{color:var(--text);border-color:var(--primary);}
.apc-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11.5px;word-break:break-all;padding:6px 8px;border-radius:8px;background:color-mix(in srgb,var(--card) 85%,var(--border));display:block;margin-top:4px;color:var(--text);}
.apc-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:16px;}
@media(max-width:860px){.apc-summary{grid-template-columns:repeat(2,minmax(0,1fr));}}
.apc-stat{padding:12px 14px;border:1px solid var(--border);border-radius:14px;background:var(--card);text-decoration:none;color:inherit;display:block;}
.apc-stat.is-active{border-color:var(--primary);box-shadow:0 0 0 1px var(--primary) inset;}
.apc-stat-label{margin:0;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
.apc-stat-value{margin:4px 0 0;font-size:20px;font-weight:800;}
.apc-section-title{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin:6px 0 12px;}
.apc-section-title h2{margin:0;font-size:16px;font-weight:750;}
.apc-section-title span{font-size:12px;color:var(--muted);}
.apc-table-card{border:1px solid var(--border);border-radius:16px;overflow:hidden;background:var(--card);margin-bottom:22px;}
.apc-table{width:100%;border-collapse:collapse;}
.apc-table th{padding:10px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);background:color-mix(in srgb,var(--card) 88%,var(--border));text-align:left;border-bottom:1px solid var(--border);}
.apc-table td{padding:11px 14px;font-size:13px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);vertical-align:top;}
.apc-table tr:last-child td{border-bottom:none;}
.apc-main{font-weight:650;}
.apc-meta{font-size:11.5px;color:var(--muted);margin-top:2px;}
.apc-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap;text-transform:capitalize;}
.apc-badge--ok,.apc-badge--completed{background:color-mix(in srgb,#22c55e 14%,transparent);color:#16a34a;}
.apc-badge--fixed{background:color-mix(in srgb,#3b82f6 14%,transparent);color:#2563eb;}
.apc-badge--issue,.apc-badge--running{background:color-mix(in srgb,#f59e0b 15%,transparent);color:#b45309;}
.apc-badge--error,.apc-badge--failed{background:color-mix(in srgb,#ef4444 14%,transparent);color:#dc2626;}
.apc-badge--muted{background:color-mix(in srgb,#64748b 15%,transparent);color:#64748b;}
.apc-empty{padding:40px 24px;text-align:center;color:var(--muted);font-size:13px;}
.apc-run-row.is-selected td{background:color-mix(in srgb,var(--primary) 6%,transparent);}
.apc-guide{border:1px solid var(--border);border-radius:16px;background:var(--card);margin-bottom:22px;}
.apc-guide>summary{list-style:none;cursor:pointer;padding:14px 18px;display:flex;align-items:center;gap:10px;font-size:14px;font-weight:750;}
.apc-guide>summary::-webkit-details-marker{display:none;}
.apc-guide>summary .apc-chev{margin-left:auto;color:var(--muted);transition:transform .15s;}
.apc-guide[open]>summary .apc-chev{transform:rotate(180deg);}
.apc-guide-body{padding:0 18px 18px;border-top:1px solid var(--border);}
.apc-steps{margin:14px 0 0;padding:0;list-style:none;counter-reset:step;}
.apc-steps>li{position:relative;padding:0 0 16px 38px;font-size:13px;line-height:1.55;counter-increment:step;}
.apc-steps>li::before{content:counter(step);position:absolute;left:0;top:0;width:24px;height:24px;border-radius:50%;display:grid;place-items:center;font-size:12px;font-weight:800;background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);}
.apc-steps b{font-weight:700;}
.apc-copy-row{display:flex;gap:8px;align-items:stretch;margin-top:6px;flex-wrap:wrap;}
.apc-copy-row .apc-code{margin-top:0;flex:1;min-width:200px;}
.apc-events{width:100%;border-collapse:collapse;margin-top:8px;font-size:12.5px;}
.apc-events td{padding:6px 8px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);vertical-align:top;}
.apc-events td:first-child{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;white-space:nowrap;}
.apc-note{padding:10px 12px;border-radius:10px;font-size:12.5px;line-height:1.5;margin-top:8px;background:color-mix(in srgb,#f59e0b 10%,transparent);border:1px solid color-mix(in srgb,#f59e0b 30%,var(--border));}
.apc-note--info{background:color-mix(in srgb,var(--primary) 7%,transparent);border-color:color-mix(in srgb,var(--primary) 25%,var(--border));}
.apc-guide h4{margin:18px 0 6px;font-size:13px;font-weight:750;}
@media(max-width:640px){.apc-events td:first-child{white-space:normal;word-break:break-all;}}
@media(max-width:640px){
    .apc-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);}
    .apc-table,.apc-table tbody,.apc-table tr,.apc-table td{display:block;width:100%;box-sizing:border-box;}
    .apc-table tr{padding:10px 14px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);}
    .apc-table td{padding:3px 0;border-bottom:none;}
}
</style>

@php
    $outcomeLabels = ['ok' => 'In sync', 'fixed' => 'Fixed', 'issue' => 'Needs follow-up', 'error' => 'Error'];
    $results = collect($selected?->results ?? []);
    $shown = $filter ? $results->where('outcome', $filter) : $results;
    $paidCount = $results->where('paid_this_month', true)->count();
    $next = $settings->nextRunAt();
@endphp

<div class="apc-wrap">
    <div class="apc-header">
        <div>
            <h1 class="apc-title"><i class="fa fa-shield-halved" style="color:var(--primary);margin-right:8px;"></i>Payment Checks</h1>
            <p class="apc-sub">Double-checks this month's subscription payments directly with Stripe, as a backup to the webhook. Anything the webhook missed is fixed automatically; unpaid businesses are listed for follow-up.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('admin.payments.index') }}" class="apc-btn"><i class="fa fa-arrow-left"></i> Payment History</a>
            <form method="POST" action="{{ route('admin.payments.checks.run') }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='<i class=\'fa fa-spinner fa-spin\'></i> Checking with Stripe…';">
                @csrf
                <button type="submit" class="apc-btn apc-btn--primary"><i class="fa fa-rotate"></i> Run check now</button>
            </form>
        </div>
    </div>

    @if(session('status'))
        <div class="apc-msg"><i class="fa fa-circle-check" style="color:#22c55e;"></i> {{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="apc-msg apc-msg--error"><i class="fa fa-triangle-exclamation" style="color:#dc2626;"></i> {{ $errors->first() }}</div>
    @endif

    <div class="apc-grid">
        {{-- Schedule --}}
        <div class="apc-card">
            <h3><i class="fa fa-clock" style="color:var(--primary);"></i> Automatic check (cron)</h3>
            <form method="POST" action="{{ route('admin.payments.checks.settings') }}">
                @csrf @method('PUT')
                <div class="apc-form-row">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="hidden" name="is_enabled" value="0">
                        <input type="checkbox" name="is_enabled" value="1" @checked($settings->is_enabled)> Enabled
                    </label>
                </div>
                <div class="apc-form-row">
                    Run every
                    <input type="number" name="interval_minutes" id="apcInterval" min="{{ \Modules\Payment\Models\PaymentCheckSetting::MIN_INTERVAL }}" max="{{ \Modules\Payment\Models\PaymentCheckSetting::MAX_INTERVAL }}" value="{{ old('interval_minutes', $settings->interval_minutes) }}" required>
                    minutes
                </div>
                <div class="apc-presets">
                    @foreach([10, 20, 30, 60, 180, 720, 1440] as $m)
                        <button type="button" class="apc-preset" onclick="document.getElementById('apcInterval').value={{ $m }}">{{ $m < 60 ? $m.' min' : ($m / 60).' h' }}</button>
                    @endforeach
                </div>
                <button type="submit" class="apc-btn"><i class="fa fa-floppy-disk"></i> Save schedule</button>
            </form>
        </div>

        {{-- Status --}}
        <div class="apc-card">
            <h3><i class="fa fa-gauge" style="color:var(--primary);"></i> Status</h3>
            <div class="apc-kv">Last run <b>{{ $settings->last_run_at ? $settings->last_run_at->diffForHumans() : 'Never' }}</b></div>
            <div class="apc-kv">Next run <b>{{ ! $settings->is_enabled ? 'Disabled' : ($next && $next->isFuture() ? $next->diffForHumans() : 'Next scheduler tick') }}</b></div>
            <div class="apc-kv">Checking period <b>{{ now()->format('F Y') }}</b></div>
            @if($settings->editor)
                <div class="apc-kv">Schedule changed by <b>{{ $settings->editor->name }}</b></div>
            @endif
            <p class="apc-meta" style="margin-top:10px;">Needs the server cron running <code>php artisan schedule:run</code> every minute.</p>
        </div>

        {{-- Webhook --}}
        <div class="apc-card">
            <h3><i class="fa fa-plug" style="color:var(--primary);"></i> Stripe webhook</h3>
            <div class="apc-kv">Signing secret
                <b>@if($webhookConfigured)<span class="apc-badge apc-badge--ok">Configured</span>@else<span class="apc-badge apc-badge--error">Missing</span>@endif</b>
            </div>
            <div class="apc-kv">Last event received
                <b>{{ $settings->last_webhook_at ? $settings->last_webhook_at->diffForHumans() : 'None recorded yet' }}</b>
            </div>
            @if($settings->last_webhook_event)
                <div class="apc-kv">Last event type <b>{{ $settings->last_webhook_event }}</b></div>
            @endif
            <div class="apc-kv">Stripe mode
                <b>@if($stripeMode === 'live')<span class="apc-badge apc-badge--ok">Live</span>@elseif($stripeMode === 'test')<span class="apc-badge apc-badge--issue">Test</span>@else<span class="apc-badge apc-badge--error">No key</span>@endif</b>
            </div>
            <a href="#stripe-setup" onclick="document.getElementById('stripe-setup').open=true" class="apc-meta" style="display:inline-block;margin-top:8px;color:var(--primary);font-weight:600;">
                <i class="fa fa-book-open"></i> Stripe setup guide
            </a>
        </div>
    </div>

    {{-- Stripe setup guide --}}
    <details class="apc-guide" id="stripe-setup" @if(! $webhookConfigured || ! $settings->last_webhook_at) open @endif>
        <summary>
            <i class="fa fa-book-open" style="color:var(--primary);"></i> Stripe setup guide
            @if($webhookConfigured && $settings->last_webhook_at)
                <span class="apc-badge apc-badge--ok">Webhook working</span>
            @else
                <span class="apc-badge apc-badge--issue">Setup needed</span>
            @endif
            <i class="fa fa-chevron-down apc-chev"></i>
        </summary>
        <div class="apc-guide-body">
            <div class="apc-note apc-note--info" style="margin-top:14px;">
                Only the <b>webhook</b> needs setup in Stripe. The automatic check above only reads from Stripe using the existing <code>STRIPE_SECRET</code> key — nothing to configure for it.
            </div>

            <ol class="apc-steps">
                <li>
                    In Stripe, open <b>Developers › Webhooks</b> and click <b>Add endpoint</b>.
                    Make sure the <b>Test / Live</b> switch matches the key this server uses
                    (currently <b>{{ $stripeMode ? ucfirst($stripeMode) : 'not set' }}</b>) — each mode has its own endpoint and secret.
                </li>
                <li>
                    Endpoint URL:
                    <div class="apc-copy-row">
                        <code class="apc-code" id="apcWebhookUrl">{{ $webhookUrl }}</code>
                        <button type="button" class="apc-btn" data-copy="#apcWebhookUrl"><i class="fa fa-copy"></i> Copy</button>
                    </div>
                    @if($isLocalUrl)
                        <div class="apc-note">
                            This is a local address — Stripe can't reach it. On the live server this shows your real domain.
                            For local testing use the Stripe CLI (see <b>Local testing</b> below).
                        </div>
                    @endif
                </li>
                <li>
                    Click <b>Select events</b> and tick these {{ count($webhookEvents) }} events:
                    <div class="apc-copy-row" style="margin-top:8px;">
                        <span id="apcEventList" style="display:none;">{{ implode("\n", array_keys($webhookEvents)) }}</span>
                        <button type="button" class="apc-btn" data-copy="#apcEventList"><i class="fa fa-copy"></i> Copy event names</button>
                    </div>
                    <table class="apc-events">
                        @foreach($webhookEvents as $event => $meaning)
                            <tr><td>{{ $event }}</td><td style="color:var(--muted);">{{ $meaning }}</td></tr>
                        @endforeach
                    </table>
                </li>
                <li>
                    Click <b>Add endpoint</b>, open it, and click <b>Reveal</b> under <b>Signing secret</b> (starts with <code>whsec_</code>).
                </li>
                <li>
                    Put it in the server's <code>.env</code>, then run <code>php artisan config:clear</code> (or <code>config:cache</code>):
                    <div class="apc-copy-row">
                        <code class="apc-code">STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxx</code>
                    </div>
                </li>
                <li>
                    Check it: on the endpoint page in Stripe click <b>Send test event</b>, then refresh this page.
                    <b>Signing secret</b> should show <span class="apc-badge apc-badge--ok">Configured</span>
                    and <b>Last event received</b> should update.
                    Currently: {{ $webhookConfigured ? 'secret configured' : 'secret missing' }},
                    {{ $settings->last_webhook_at ? 'last event '.$settings->last_webhook_at->diffForHumans() : 'no event received yet' }}.
                </li>
            </ol>

            <h4><i class="fa fa-laptop-code" style="color:var(--primary);"></i> Local testing (127.0.0.1)</h4>
            <p class="apc-meta" style="margin:0 0 6px;font-size:12.5px;">Stripe can't call your computer directly. Install the Stripe CLI and run:</p>
            <div class="apc-copy-row">
                <code class="apc-code" id="apcCliCmd" style="white-space:pre-wrap;">stripe login
stripe listen --forward-to {{ $webhookUrl }}</code>
                <button type="button" class="apc-btn" data-copy="#apcCliCmd"><i class="fa fa-copy"></i> Copy</button>
            </div>
            <p class="apc-meta" style="margin:6px 0 0;font-size:12.5px;">It prints its own <code>whsec_…</code> secret — put that one in your <b>local</b> <code>.env</code> (it's different from the Dashboard secret) and keep the window open while testing.</p>

            <h4><i class="fa fa-lightbulb" style="color:var(--primary);"></i> Recommended</h4>
            <ul style="margin:0;padding-left:18px;font-size:12.5px;line-height:1.6;">
                <li><b>Smart Retries:</b> Stripe › Settings › Billing › Subscriptions and emails › <b>Manage failed payments</b> — turn on. When a retry succeeds, the business is unlocked automatically.</li>
                <li><b>Restricted API key:</b> if you use one instead of the full secret key, give it <b>Read</b> access to Subscriptions, Invoices and Checkout Sessions so the automatic check works.</li>
                <li>Stripe retries failed webhook deliveries for up to 3 days, and the automatic check above catches anything still missed.</li>
            </ul>
        </div>
    </details>

    {{-- Results of the selected run --}}
    <div class="apc-section-title">
        <h2>{{ $selected ? 'Results — '.\Illuminate\Support\Carbon::createFromFormat('Y-m', $selected->period)->format('F Y') : 'Results' }}</h2>
        @if($selected)
            <span>
                Run #{{ $selected->id }} · {{ $selected->trigger === 'manual' ? 'Run manually'.($selected->triggeredBy ? ' by '.$selected->triggeredBy->name : '') : 'Scheduled' }}
                · {{ $selected->started_at?->format('d M Y, H:i') }}
                @if($selected->durationSeconds() !== null) · {{ $selected->durationSeconds() }}s @endif
                · {{ $paidCount }} paid this month
            </span>
        @endif
    </div>

    @if($selected && $selected->status === 'failed')
        <div class="apc-msg apc-msg--error"><i class="fa fa-triangle-exclamation" style="color:#dc2626;"></i> This run failed: {{ $selected->error }}</div>
    @endif

    @if($selected && $selected->status === 'completed')
        <div class="apc-summary">
            <a class="apc-stat {{ $filter === null ? 'is-active' : '' }}" href="{{ route('admin.payments.checks.index', ['run' => $selected->id]) }}">
                <p class="apc-stat-label">Checked</p><p class="apc-stat-value">{{ $selected->checked_count }}</p>
            </a>
            <a class="apc-stat {{ $filter === 'ok' ? 'is-active' : '' }}" href="{{ route('admin.payments.checks.index', ['run' => $selected->id, 'outcome' => 'ok']) }}">
                <p class="apc-stat-label">In sync</p><p class="apc-stat-value" style="color:#16a34a;">{{ $selected->ok_count }}</p>
            </a>
            <a class="apc-stat {{ $filter === 'fixed' ? 'is-active' : '' }}" href="{{ route('admin.payments.checks.index', ['run' => $selected->id, 'outcome' => 'fixed']) }}">
                <p class="apc-stat-label">Fixed from Stripe</p><p class="apc-stat-value" style="color:#2563eb;">{{ $selected->fixed_count }}</p>
            </a>
            <a class="apc-stat {{ $filter === 'issue' ? 'is-active' : '' }}" href="{{ route('admin.payments.checks.index', ['run' => $selected->id, 'outcome' => 'issue']) }}">
                <p class="apc-stat-label">Needs follow-up</p><p class="apc-stat-value" style="color:#b45309;">{{ $selected->issue_count }}</p>
            </a>
            <a class="apc-stat {{ $filter === 'error' ? 'is-active' : '' }}" href="{{ route('admin.payments.checks.index', ['run' => $selected->id, 'outcome' => 'error']) }}">
                <p class="apc-stat-label">Errors</p><p class="apc-stat-value" style="color:#dc2626;">{{ $selected->error_count }}</p>
            </a>
        </div>
    @endif

    <div class="apc-table-card" style="overflow-x:auto;">
        <table class="apc-table">
            <thead>
                <tr>
                    <th>Business</th>
                    <th>Result</th>
                    <th>Local / Stripe</th>
                    <th>Paid this month</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($shown as $r)
                    <tr>
                        <td>
                            <div class="apc-main">{{ $r['business_name'] }}</div>
                            <div class="apc-meta">
                                <a href="{{ route('admin.payments.show', $r['payment_id']) }}" style="color:inherit;">Payment #{{ $r['payment_id'] }}</a>
                                · {{ $r['kind'] === 'checkout' ? 'Checkout' : 'Subscription' }}
                            </div>
                        </td>
                        <td><span class="apc-badge apc-badge--{{ $r['outcome'] }}">{{ $outcomeLabels[$r['outcome']] ?? $r['outcome'] }}</span></td>
                        <td>
                            <div style="text-transform:capitalize;">{{ $r['local_status'] ?: '—' }}</div>
                            <div class="apc-meta">Stripe: {{ $r['stripe_status'] ?: '—' }}</div>
                        </td>
                        <td>
                            @if($r['paid_this_month'])
                                <span class="apc-badge apc-badge--ok"><i class="fa fa-check"></i> Paid</span>
                                @if($r['amount'] !== null)<div class="apc-meta">{{ $r['currency'] }} {{ number_format((float) $r['amount'], 2) }}</div>@endif
                            @else
                                <span class="apc-badge apc-badge--muted">Not this month</span>
                            @endif
                        </td>
                        <td style="max-width:420px;">{{ $r['message'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">
                        <div class="apc-empty">
                            @if(! $selected)
                                No checks have run yet. Click <b>Run check now</b> to verify this month's payments with Stripe.
                            @elseif($filter)
                                Nothing in this category. <a href="{{ route('admin.payments.checks.index', ['run' => $selected->id]) }}">Show all</a>
                            @else
                                No subscriptions or checkouts to check for this month.
                            @endif
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Run history --}}
    <div class="apc-section-title"><h2>Recent runs</h2><span>Kept for 30 days</span></div>
    <div class="apc-table-card" style="overflow-x:auto;">
        <table class="apc-table">
            <thead>
                <tr><th>Run</th><th>Started</th><th>Trigger</th><th>Status</th><th>Checked</th><th>Fixed</th><th>Follow-up</th><th>Errors</th></tr>
            </thead>
            <tbody>
                @forelse($runs as $run)
                    <tr class="apc-run-row {{ $selected?->id === $run->id ? 'is-selected' : '' }}">
                        <td><a href="{{ route('admin.payments.checks.index', ['run' => $run->id]) }}" class="apc-main" style="color:inherit;">#{{ $run->id }}</a></td>
                        <td>{{ $run->started_at?->format('d M, H:i') }}<div class="apc-meta">{{ $run->started_at?->diffForHumans() }}</div></td>
                        <td>{{ $run->trigger === 'manual' ? 'Manual' : 'Scheduled' }}@if($run->triggeredBy)<div class="apc-meta">{{ $run->triggeredBy->name }}</div>@endif</td>
                        <td><span class="apc-badge apc-badge--{{ $run->status }}">{{ $run->status }}</span></td>
                        <td>{{ $run->checked_count }}</td>
                        <td>{{ $run->fixed_count }}</td>
                        <td>{{ $run->issue_count }}</td>
                        <td>{{ $run->error_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="apc-empty">No runs yet.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var el = document.querySelector(btn.getAttribute('data-copy'));
        if (!el) return;
        var original = btn.innerHTML;
        var done = function () { btn.innerHTML = '<i class="fa fa-check"></i> Copied'; setTimeout(function () { btn.innerHTML = original; }, 1500); };
        var text = el.textContent.trim();
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            var ta = document.createElement('textarea');
            ta.value = text; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); done(); } finally { ta.remove(); }
        }
    });
});
</script>
@endsection
