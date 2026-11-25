@extends('theme::layouts.app', ['title' => 'User Seats & Extra Users', 'heading' => 'User Seats & Extra Users'])

@section('content')
<style>
.aus-wrap{max-width:1280px;margin:0 auto;}
.aus-header{margin-bottom:22px;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;}
.aus-title{margin:0;font-size:22px;font-weight:800;letter-spacing:-.025em;}
.aus-sub{margin:4px 0 0;font-size:13px;color:var(--muted);}
.aus-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:22px;}
@media(max-width:860px){.aus-summary{grid-template-columns:repeat(2,minmax(0,1fr));}}
.aus-stat{padding:14px 16px;border:1px solid var(--border);border-radius:14px;background:var(--card);}
.aus-stat-label{margin:0;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
.aus-stat-value{margin:6px 0 0;font-size:20px;font-weight:800;color:var(--text);line-height:1.25;}
.aus-stat-note{margin:4px 0 0;font-size:11.5px;color:var(--muted);}
.aus-filters{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;}
.aus-filters select,.aus-search input{padding:9px 13px;border-radius:11px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);font-size:13px;font-family:inherit;}
.aus-search{position:relative;flex:1;min-width:220px;max-width:360px;}
.aus-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12px;}
.aus-search input{width:100%;box-sizing:border-box;padding-left:34px;}
.aus-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 14px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;text-decoration:none;}
.aus-btn:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);}
.aus-card{border:1px solid var(--border);border-radius:16px;overflow:hidden;background:var(--card);}
.aus-table{width:100%;border-collapse:collapse;}
.aus-table th{padding:11px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);background:color-mix(in srgb,var(--card) 88%,var(--border));text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;}
.aus-table td{padding:13px 16px;font-size:13.5px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);vertical-align:middle;}
.aus-table tr:last-child td{border-bottom:none;}
.aus-table .num{text-align:right;white-space:nowrap;}
.aus-row{cursor:pointer;}
.aus-row:hover td{background:color-mix(in srgb,var(--primary) 4%,transparent);}
.aus-main{font-weight:650;color:var(--text);}
.aus-meta{font-size:12px;color:var(--muted);margin-top:1px;}
.aus-bar{height:6px;border-radius:999px;background:color-mix(in srgb,var(--border) 80%,transparent);margin:5px 0 3px;overflow:hidden;}
.aus-bar>div{height:100%;border-radius:999px;}
.aus-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap;}
.aus-badge--full{background:color-mix(in srgb,#d97706 15%,transparent);color:#b45309;}
.aus-badge--near{background:color-mix(in srgb,#f59e0b 13%,transparent);color:#b45309;}
.aus-badge--ok{background:color-mix(in srgb,#22c55e 14%,transparent);color:#16a34a;}
.aus-badge--muted{background:color-mix(in srgb,#64748b 15%,transparent);color:#64748b;}
.aus-badge--primary{background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);}
.aus-empty{padding:48px 24px;text-align:center;}
.aus-empty-icon{width:52px;height:52px;border-radius:14px;margin:0 auto 14px;display:grid;place-items:center;font-size:22px;background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);}
@media(max-width:640px){
    .aus-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;}
    .aus-table, .aus-table tbody, .aus-table tr, .aus-table td{display:block;width:100%;box-sizing:border-box;}
    .aus-table tr{padding:12px 16px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);}
    .aus-table td{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:4px 0;border-bottom:none;text-align:right;}
    .aus-table td[data-label]::before{content:attr(data-label);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);text-align:left;flex-shrink:0;}
}
</style>

@php
    $so = $overview;
    $money = fn ($totals) => $totals->isEmpty() ? '—' : $totals->map(fn ($t, $c) => $c.' '.number_format($t, 2))->implode(' · ');
    $hasFilters = collect(['search', 'seats', 'package'])->contains(fn ($k) => request()->filled($k))
        || (request()->filled('sort') && request('sort') !== 'fullest');
@endphp

<div class="aus-wrap">
    <div class="aus-header">
        <div>
            <h1 class="aus-title"><i class="fa fa-user-plus" style="color:var(--primary);margin-right:8px;"></i>User Seats &amp; Extra Users</h1>
            <p class="aus-sub">How many users each business has, its package limit, extra users bought and what they pay. Click a business for every user and purchase.</p>
        </div>
        <a href="{{ route('admin.payments.index', ['type' => 'extra_users']) }}" class="aus-btn"><i class="fa fa-receipt"></i> Extra-user payments</a>
    </div>

    <div class="aus-summary">
        <div class="aus-stat">
            <p class="aus-stat-label">Extra users sold</p>
            <p class="aus-stat-value">{{ number_format($so['extra_seats_total']) }}</p>
            <p class="aus-stat-note">across {{ $so['businesses_with_extras'] }} {{ $so['businesses_with_extras'] === 1 ? 'business' : 'businesses' }} · {{ $so['extra_purchases_this_month'] }} bought this month</p>
        </div>
        <div class="aus-stat">
            <p class="aus-stat-label">Extra users / month</p>
            <p class="aus-stat-value" style="font-size:17px;">{{ $money($so['extra_mrr']) }}</p>
            <p class="aus-stat-note">added to subscriptions every renewal</p>
        </div>
        <div class="aus-stat">
            <p class="aus-stat-label">Extra users collected</p>
            <p class="aus-stat-value" style="font-size:17px;">{{ $money($so['extra_revenue']) }}</p>
            <p class="aus-stat-note">first-month seat purchases (paid)</p>
        </div>
        <div class="aus-stat">
            <p class="aus-stat-label">At the user limit</p>
            <p class="aus-stat-value" style="{{ $so['full_businesses'] ? 'color:#d97706;' : '' }}">{{ $so['full_businesses'] }} <span style="font-size:13px;color:var(--muted);font-weight:600;">of {{ $so['limited_businesses'] }} limited</span></p>
            <p class="aus-stat-note">{{ $so['near_full_businesses'] }} more at 80%+</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.user-seats.index') }}" class="aus-filters">
        <div class="aus-search">
            <i class="fa fa-magnifying-glass"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Business, owner name or email…">
        </div>
        <select name="seats" onchange="this.form.submit()">
            <option value="">All businesses</option>
            <option value="limited" @selected(request('seats') === 'limited')>With a user limit</option>
            <option value="unlimited" @selected(request('seats') === 'unlimited')>Unlimited users</option>
            <option value="full" @selected(request('seats') === 'full')>At the limit (full)</option>
            <option value="near_full" @selected(request('seats') === 'near_full')>Near the limit (80%+)</option>
            <option value="extras" @selected(request('seats') === 'extras')>Bought extra users</option>
        </select>
        <select name="package" onchange="this.form.submit()">
            <option value="">All packages</option>
            @foreach($packages as $pkg)
                <option value="{{ $pkg->id }}" @selected((string) request('package') === (string) $pkg->id)>{{ $pkg->name }}{{ $pkg->max_users ? ' ('.$pkg->max_users.' users)' : '' }}</option>
            @endforeach
        </select>
        <select name="sort" onchange="this.form.submit()">
            <option value="fullest" @selected(request('sort', 'fullest') === 'fullest')>Fullest first</option>
            <option value="extras" @selected(request('sort') === 'extras')>Most extra users</option>
            <option value="users" @selected(request('sort') === 'users')>Most users</option>
            <option value="newest" @selected(request('sort') === 'newest')>Newest business</option>
            <option value="name" @selected(request('sort') === 'name')>Name A–Z</option>
        </select>
        <button type="submit" class="aus-btn"><i class="fa fa-magnifying-glass"></i> Search</button>
        @if($hasFilters)
            <a href="{{ route('admin.user-seats.index') }}" class="aus-btn"><i class="fa fa-xmark"></i> Clear</a>
        @endif
    </form>

    <div class="aus-card" style="overflow-x:auto;">
        <table class="aus-table">
            <thead>
                <tr>
                    <th>Business / Owner</th>
                    <th>Package</th>
                    <th>Users</th>
                    <th class="num">Included</th>
                    <th class="num">Extra users</th>
                    <th class="num">Monthly total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($businesses as $r)
                    @php
                        $url = route('admin.user-seats.show', $r['business_id']);
                        $pct = min(100, (int) round($r['usage_ratio'] * 100));
                    @endphp
                    <tr class="aus-row" data-href="{{ $url }}" title="View users & seat details">
                        <td data-label="Business / Owner">
                            <div class="aus-main">{{ $r['business'] }}</div>
                            <div class="aus-meta">{{ $r['owner_name'] ?? 'Unknown owner' }}@if($r['owner_email']) · {{ $r['owner_email'] }}@endif</div>
                        </td>
                        <td data-label="Package">
                            {{ $r['plan'] ?? 'No package' }}
                            @if($r['custom_limit'])<div class="aus-meta"><span class="aus-badge aus-badge--primary">Custom limit</span></div>@endif
                        </td>
                        <td data-label="Users" style="min-width:160px;">
                            @if($r['limited'])
                                <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;font-weight:700;">
                                    <span>{{ $r['users_used'] }} / {{ $r['user_limit'] }}</span>
                                    @if($r['is_full'])
                                        <span class="aus-badge aus-badge--full">Full</span>
                                    @elseif($r['is_near_full'])
                                        <span class="aus-badge aus-badge--near">{{ $pct }}%</span>
                                    @else
                                        <span class="aus-badge aus-badge--ok">{{ $r['users_remaining'] }} free</span>
                                    @endif
                                </div>
                                <div class="aus-bar"><div style="width:{{ $pct }}%;background:{{ $r['is_full'] ? '#d97706' : ($r['is_near_full'] ? '#f59e0b' : 'var(--primary)') }};"></div></div>
                            @else
                                <div style="font-size:12.5px;font-weight:700;">{{ $r['users_used'] }} <span class="aus-badge aus-badge--muted">Unlimited</span></div>
                            @endif
                        </td>
                        <td data-label="Included" class="num">{{ $r['included_users'] ?? '∞' }}</td>
                        <td data-label="Extra users" class="num">
                            @if($r['extra_users'])
                                <strong>+{{ $r['extra_users'] }}</strong>
                                <div class="aus-meta">{{ $r['currency'] }} {{ number_format($r['extra_monthly'], 2) }} / mo</div>
                            @else
                                <span class="aus-meta">—</span>
                            @endif
                        </td>
                        <td data-label="Monthly total" class="num">
                            <strong>{{ $r['currency'] }} {{ number_format($r['monthly_total'], 2) }}</strong>
                            <div class="aus-meta">plan {{ number_format($r['base_amount'], 2) }}</div>
                        </td>
                        <td style="text-align:right;">
                            <a href="{{ $url }}" class="aus-btn" style="padding:5px 10px;font-size:12px;"><i class="fa fa-eye"></i> View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="aus-empty">
                                <div class="aus-empty-icon"><i class="fa fa-users"></i></div>
                                <p style="margin:0 0 6px;font-size:16px;font-weight:700;">No businesses found</p>
                                <p style="margin:0;font-size:13px;color:var(--muted);">{{ $hasFilters ? 'Try clearing the filters.' : 'Businesses will appear here once users sign up.' }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($businesses->hasPages())
        <div style="margin-top:16px;">{{ $businesses->links() }}</div>
    @endif
</div>

<script>
document.querySelectorAll('.aus-row').forEach(function (row) {
    row.addEventListener('click', function (e) {
        if (e.target.closest('a, button')) return;
        window.location.href = row.dataset.href;
    });
});
</script>
@endsection
