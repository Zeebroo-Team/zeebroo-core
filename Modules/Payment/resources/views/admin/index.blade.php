@extends('theme::layouts.app', ['title' => 'Payment History', 'heading' => 'Payment History'])

@section('content')
<style>
.apay-wrap{max-width:1280px;margin:0 auto;}
.apay-header{margin-bottom:22px;}
.apay-title{margin:0;font-size:22px;font-weight:800;letter-spacing:-.025em;}
.apay-sub{margin:4px 0 0;font-size:13px;color:var(--muted);}
.apay-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:22px;}
@media(max-width:860px){.apay-summary{grid-template-columns:repeat(2,minmax(0,1fr));}}
.apay-stat{padding:14px 16px;border:1px solid var(--border);border-radius:14px;background:var(--card);}
.apay-stat-label{margin:0;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
.apay-stat-value{margin:6px 0 0;font-size:20px;font-weight:800;color:var(--text);line-height:1.25;}
.apay-stat-note{margin:4px 0 0;font-size:11.5px;color:var(--muted);}
.apay-filters{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;}
.apay-filter-date{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);}
.apay-filters select,.apay-filters input[type=date],.apay-filter-search input{padding:9px 13px;border-radius:11px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);font-size:13px;font-family:inherit;}
.apay-filter-search{position:relative;flex:1;min-width:220px;max-width:360px;}
.apay-filter-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12px;}
.apay-filter-search input{width:100%;box-sizing:border-box;padding-left:34px;}
.apay-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 14px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;text-decoration:none;}
.apay-btn:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);}
.apay-card{border:1px solid var(--border);border-radius:16px;overflow:hidden;background:var(--card);}
.apay-table{width:100%;border-collapse:collapse;}
.apay-table th{padding:11px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);background:color-mix(in srgb,var(--card) 88%,var(--border));text-align:left;border-bottom:1px solid var(--border);}
.apay-table td{padding:13px 16px;font-size:13.5px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);vertical-align:middle;}
.apay-table tr:last-child td{border-bottom:none;}
.apay-row{cursor:pointer;}
.apay-row:hover td{background:color-mix(in srgb,var(--primary) 4%,transparent);}
.apay-id{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;color:var(--muted);}
.apay-main{font-weight:650;color:var(--text);}
.apay-meta{font-size:12px;color:var(--muted);margin-top:1px;}
.apay-amount{font-weight:750;white-space:nowrap;}
.apay-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;text-transform:capitalize;white-space:nowrap;}
.apay-badge--succeeded{background:color-mix(in srgb,#22c55e 14%,transparent);color:#16a34a;}
.apay-badge--pending,.apay-badge--processing{background:color-mix(in srgb,#f59e0b 15%,transparent);color:#b45309;}
.apay-badge--failed,.apay-badge--canceled{background:color-mix(in srgb,#ef4444 14%,transparent);color:#dc2626;}
.apay-badge--refunded,.apay-badge--muted{background:color-mix(in srgb,#64748b 15%,transparent);color:#64748b;}
.apay-empty{padding:48px 24px;text-align:center;}
.apay-empty-icon{width:52px;height:52px;border-radius:14px;margin:0 auto 14px;display:grid;place-items:center;font-size:22px;background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);}
.apay-empty-title{margin:0 0 6px;font-size:16px;font-weight:700;}
.apay-empty-sub{margin:0;font-size:13px;color:var(--muted);}
@media(max-width:640px){
    .apay-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;}
    .apay-table, .apay-table tbody, .apay-table tr, .apay-table td{display:block;width:100%;box-sizing:border-box;}
    .apay-table tr{padding:12px 16px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);}
    .apay-table tr:last-child{border-bottom:none;}
    .apay-table td{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:4px 0;border-bottom:none;text-align:right;}
    .apay-table td[data-label]::before{content:attr(data-label);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);text-align:left;flex-shrink:0;}
}
</style>

@php
    $formatTotals = fn ($totals) => $totals->isEmpty()
        ? '—'
        : $totals->map(fn ($total, $cur) => $cur.' '.number_format($total, 2))->implode(' · ');
    $hasFilters = collect(['search','status','type','gateway','platform','from','to'])->contains(fn ($k) => request()->filled($k))
        || in_array(request('sort'), ['oldest','amount_desc','amount_asc'], true);
@endphp

<div class="apay-wrap">
    <div class="apay-header">
        <h1 class="apay-title"><i class="fa fa-credit-card" style="color:var(--primary);margin-right:8px;"></i>Payment History</h1>
        <p class="apay-sub">Every subscription and plan payment across all businesses. Click a row for the full details.</p>
    </div>

    <div class="apay-summary">
        <div class="apay-stat">
            <p class="apay-stat-label">Revenue (succeeded)</p>
            <p class="apay-stat-value">{{ $formatTotals($summary['revenue']) }}</p>
            <p class="apay-stat-note">{{ $summary['succeeded_count'] }} successful payment{{ $summary['succeeded_count'] === 1 ? '' : 's' }}</p>
        </div>
        <div class="apay-stat">
            <p class="apay-stat-label">Paid this month</p>
            <p class="apay-stat-value">{{ $formatTotals($summary['revenue_this_month']) }}</p>
            <p class="apay-stat-note">Since {{ now()->startOfMonth()->format('d M Y') }}</p>
        </div>
        <div class="apay-stat">
            <p class="apay-stat-label">Total payments</p>
            <p class="apay-stat-value">{{ number_format($summary['count']) }}</p>
            <p class="apay-stat-note">{{ $summary['pending_count'] }} pending / processing</p>
        </div>
        <div class="apay-stat">
            <p class="apay-stat-label">Failed</p>
            <p class="apay-stat-value" style="{{ $summary['failed_count'] ? 'color:#dc2626;' : '' }}">{{ number_format($summary['failed_count']) }}</p>
            <p class="apay-stat-note">Needs follow-up</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.payments.index') }}" class="apay-filters">
        <div class="apay-filter-search">
            <i class="fa fa-magnifying-glass"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Business, user, email, Stripe ID or #ID…">
        </div>
        <select name="status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            @foreach($statuses as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <select name="type" onchange="this.form.submit()">
            <option value="">All types</option>
            <option value="subscription" @selected(request('type') === 'subscription')>Subscription</option>
            <option value="free" @selected(request('type') === 'free')>Free</option>
        </select>
        @if($gateways->isNotEmpty())
            <select name="gateway" onchange="this.form.submit()">
                <option value="">All gateways</option>
                @foreach($gateways as $g)
                    <option value="{{ $g }}" @selected(request('gateway') === $g)>{{ ucfirst($g) }}</option>
                @endforeach
            </select>
        @endif
        <select name="platform" onchange="this.form.submit()">
            <option value="">All platforms</option>
            <option value="web" @selected(request('platform') === 'web')>Web</option>
            <option value="desktop" @selected(request('platform') === 'desktop')>Desktop</option>
        </select>
        <label class="apay-filter-date">From <input type="date" name="from" value="{{ request('from') }}" onchange="this.form.submit()"></label>
        <label class="apay-filter-date">To <input type="date" name="to" value="{{ request('to') }}" onchange="this.form.submit()"></label>
        <select name="sort" onchange="this.form.submit()">
            <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest first</option>
            <option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option>
            <option value="amount_desc" @selected(request('sort') === 'amount_desc')>Amount high–low</option>
            <option value="amount_asc" @selected(request('sort') === 'amount_asc')>Amount low–high</option>
        </select>
        <button type="submit" class="apay-btn"><i class="fa fa-magnifying-glass"></i> Search</button>
        @if($hasFilters)
            <a href="{{ route('admin.payments.index') }}" class="apay-btn"><i class="fa fa-xmark"></i> Clear</a>
        @endif
    </form>

    <div class="apay-card" style="overflow-x:auto;">
        <table class="apay-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Business / User</th>
                    <th>Package</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Gateway</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $p)
                    @php $url = route('admin.payments.show', $p->id); @endphp
                    <tr class="apay-row" data-href="{{ $url }}" title="View payment details">
                        <td data-label="#"><span class="apay-id">#{{ $p->id }}</span></td>
                        <td data-label="Business / User">
                            <div class="apay-main">{{ $p->business?->name ?? '—' }}</div>
                            <div class="apay-meta">{{ $p->user?->name ?? 'Unknown user' }}@if($p->user?->email) · {{ $p->user->email }}@endif</div>
                        </td>
                        <td data-label="Package">
                            {{ $p->package?->name ?? '—' }}
                            <div class="apay-meta" style="text-transform:capitalize;">{{ $p->payment_type }}@if($p->billing_cycle) · {{ $p->billing_cycle }}@endif</div>
                        </td>
                        <td data-label="Amount"><span class="apay-amount">{{ strtoupper($p->currency ?? 'USD') }} {{ number_format((float) $p->amount, 2) }}</span></td>
                        <td data-label="Status"><span class="apay-badge apay-badge--{{ $p->payment_status }}">{{ $p->payment_status }}</span></td>
                        <td data-label="Gateway" style="text-transform:capitalize;">
                            {{ $p->gateway ?: '—' }}
                            <div class="apay-meta" style="text-transform:none;">{{ $p->platform === 'pos_lite' ? 'POS Lite' : ucwords(str_replace('_', ' ', (string) $p->platform)) }}</div>
                        </td>
                        <td data-label="Date" style="white-space:nowrap;" title="{{ ($p->paid_at ?? $p->created_at)?->format('d M Y, H:i:s') }}">
                            {{ ($p->paid_at ?? $p->created_at)?->format('d M Y') }}
                            <div class="apay-meta">{{ ($p->paid_at ?? $p->created_at)?->format('H:i') }} · {{ $p->paid_at ? 'paid' : 'created' }}</div>
                        </td>
                        <td style="text-align:right;">
                            <a href="{{ $url }}" class="apay-btn" style="padding:5px 10px;font-size:12px;"><i class="fa fa-eye"></i> View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="apay-empty">
                                <div class="apay-empty-icon"><i class="fa fa-receipt"></i></div>
                                <p class="apay-empty-title">No payments found</p>
                                <p class="apay-empty-sub">{{ $hasFilters ? 'Try clearing the filters.' : 'Payments will appear here once businesses subscribe.' }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($payments->hasPages())
        <div style="margin-top:16px;">{{ $payments->links() }}</div>
    @endif
</div>

<script>
document.querySelectorAll('.apay-row').forEach(function (row) {
    row.addEventListener('click', function (e) {
        if (e.target.closest('a')) return;
        window.location.href = row.getAttribute('data-href');
    });
});
</script>
@endsection
