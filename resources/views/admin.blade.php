@extends('theme::layouts.app', ['title' => 'Admin Dashboard', 'heading' => 'Admin Dashboard'])

@section('content')
<style>
/* ── layout ── */
.adash{max-width:1100px;margin:0 auto;}
.adash-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-bottom:28px;}
/* ── stat cards ── */
.adash-card{border:1px solid var(--border);border-radius:16px;background:var(--card);padding:20px 22px;display:flex;flex-direction:column;gap:6px;}
.adash-card-icon{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;font-size:17px;flex-shrink:0;margin-bottom:4px;}
.adash-card-label{font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);}
.adash-card-value{font-size:30px;font-weight:800;letter-spacing:-.03em;line-height:1;}
.adash-card-sub{font-size:12px;color:var(--muted);margin-top:2px;display:flex;align-items:center;gap:5px;}
.adash-badge{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:999px;font-size:11px;font-weight:700;}
.adash-badge--up{background:color-mix(in srgb,#16a34a 12%,transparent);color:#16a34a;}
.adash-badge--neutral{background:color-mix(in srgb,#64748b 12%,transparent);color:#64748b;}
/* ── chart section ── */
.adash-charts{display:grid;grid-template-columns:1fr;gap:20px;margin-bottom:28px;}
@media(min-width:720px){.adash-charts{grid-template-columns:2fr 1fr;}}
.adash-chart-card{border:1px solid var(--border);border-radius:16px;background:var(--card);padding:22px 24px;}
.adash-chart-title{font-size:14px;font-weight:700;margin:0 0 18px;display:flex;align-items:center;gap:8px;}
.adash-chart-wrap{position:relative;width:100%;}
/* ── recent users ── */
.adash-recent{border:1px solid var(--border);border-radius:16px;background:var(--card);overflow:hidden;}
.adash-recent-head{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.adash-recent-title{margin:0;font-size:14px;font-weight:700;}
.adash-recent-link{font-size:12px;font-weight:600;color:var(--primary);text-decoration:none;}
.adash-recent-link:hover{text-decoration:underline;}
.adash-recent-row{display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid color-mix(in srgb,var(--border) 55%,transparent);}
.adash-recent-row:last-child{border-bottom:none;}
.adash-avatar{width:34px;height:34px;border-radius:50%;background:color-mix(in srgb,var(--primary) 14%,transparent);display:grid;place-items:center;font-size:13px;font-weight:700;color:var(--primary);flex-shrink:0;}
.adash-recent-name{font-size:13px;font-weight:650;color:var(--text);}
.adash-recent-email{font-size:11.5px;color:var(--muted);}
.adash-recent-role{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;margin-left:auto;flex-shrink:0;}
.adash-recent-role--admin{background:color-mix(in srgb,#6366f1 13%,transparent);color:#6366f1;}
.adash-recent-role--user{background:color-mix(in srgb,#64748b 13%,transparent);color:#64748b;}
.adash-recent-time{font-size:11px;color:var(--muted);flex-shrink:0;}
/* ── recent payments ── */
.adash-recent-head-meta{font-size:12px;color:var(--muted);font-weight:500;margin-left:10px;}
a.adash-recent-row{text-decoration:none;color:inherit;transition:background .12s ease;}
a.adash-recent-row:hover{background:color-mix(in srgb,var(--primary) 5%,transparent);}
.adash-pay-icon{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;font-size:13px;flex-shrink:0;background:color-mix(in srgb,#10b981 12%,transparent);color:#059669;}
.adash-pay-amount{font-size:13px;font-weight:750;white-space:nowrap;flex-shrink:0;}
.adash-pay-status{display:inline-flex;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;text-transform:capitalize;flex-shrink:0;min-width:72px;justify-content:center;}
.adash-pay-status--succeeded{background:color-mix(in srgb,#22c55e 14%,transparent);color:#16a34a;}
.adash-pay-status--pending,.adash-pay-status--processing{background:color-mix(in srgb,#f59e0b 15%,transparent);color:#b45309;}
.adash-pay-status--failed,.adash-pay-status--canceled{background:color-mix(in srgb,#ef4444 14%,transparent);color:#dc2626;}
.adash-pay-status--refunded{background:color-mix(in srgb,#64748b 15%,transparent);color:#64748b;}
.adash-empty{padding:28px 20px;text-align:center;font-size:13px;color:var(--muted);}
@media(max-width:560px){.adash-recent-time{display:none;}}
/* ── user seats & extra users ── */
.adash-seat-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));border-bottom:1px solid var(--border);}
.adash-seat-stat{padding:16px 20px;border-right:1px solid color-mix(in srgb,var(--border) 55%,transparent);display:flex;flex-direction:column;gap:4px;}
.adash-seat-stat:last-child{border-right:none;}
.adash-seat-value{font-size:24px;font-weight:800;letter-spacing:-.02em;line-height:1.15;}
.adash-seat-value--sm{font-size:17px;}
.adash-seat-table-wrap{overflow-x:auto;}
.adash-seat-table{width:100%;border-collapse:collapse;font-size:13px;}
.adash-seat-table th{text-align:left;padding:10px 20px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);border-bottom:1px solid var(--border);}
.adash-seat-table td{padding:12px 20px;border-bottom:1px solid color-mix(in srgb,var(--border) 55%,transparent);vertical-align:top;}
.adash-seat-table tr:last-child td{border-bottom:none;}
.adash-seat-table .num{text-align:right;white-space:nowrap;}
.adash-seat-bar{height:6px;border-radius:999px;background:color-mix(in srgb,var(--border) 80%,transparent);margin:5px 0 3px;overflow:hidden;}
.adash-seat-bar>div{height:100%;border-radius:999px;}
.adash-seat-flag{padding:0 7px;border-radius:999px;font-size:10.5px;}
.adash-seat-flag--full{background:color-mix(in srgb,#d97706 15%,transparent);color:#b45309;}
.adash-seat-flag--near{background:color-mix(in srgb,#f59e0b 13%,transparent);color:#b45309;}
</style>

<div class="adash">

    {{-- ── STAT CARDS ── --}}
    <div class="adash-grid">

        {{-- Total Users --}}
        <div class="adash-card">
            <div class="adash-card-icon" style="background:color-mix(in srgb,#6366f1 12%,transparent);color:#6366f1;">
                <i class="fa fa-users"></i>
            </div>
            <div class="adash-card-label">Total Users</div>
            <div class="adash-card-value">{{ number_format($totalUsers) }}</div>
            <div class="adash-card-sub">
                <span class="adash-badge adash-badge--{{ $usersThisMonth > 0 ? 'up' : 'neutral' }}">
                    <i class="fa fa-{{ $usersThisMonth > 0 ? 'arrow-up' : 'minus' }}"></i> {{ $usersThisMonth }}
                </span>
                this month
            </div>
        </div>

        {{-- New This Month --}}
        <div class="adash-card">
            <div class="adash-card-icon" style="background:color-mix(in srgb,#0ea5e9 12%,transparent);color:#0ea5e9;">
                <i class="fa fa-user-plus"></i>
            </div>
            <div class="adash-card-label">Joined This Month</div>
            <div class="adash-card-value">{{ $usersThisMonth }}</div>
            <div class="adash-card-sub">
                @if($usersLastMonth > 0)
                    <span class="adash-badge adash-badge--{{ $usersThisMonth >= $usersLastMonth ? 'up' : 'neutral' }}">
                        <i class="fa fa-{{ $usersThisMonth >= $usersLastMonth ? 'arrow-up' : 'arrow-down' }}"></i>
                        {{ $usersLastMonth }}
                    </span>
                    last month
                @else
                    <span class="adash-badge adash-badge--neutral"><i class="fa fa-minus"></i> 0</span> last month
                @endif
            </div>
        </div>

        {{-- Total Businesses --}}
        <div class="adash-card">
            <div class="adash-card-icon" style="background:color-mix(in srgb,#f59e0b 12%,transparent);color:#d97706;">
                <i class="fa fa-briefcase"></i>
            </div>
            <div class="adash-card-label">Total Businesses</div>
            <div class="adash-card-value">{{ number_format($totalBusinesses) }}</div>
            <div class="adash-card-sub">
                <span class="adash-badge adash-badge--{{ $bizThisMonth > 0 ? 'up' : 'neutral' }}">
                    <i class="fa fa-{{ $bizThisMonth > 0 ? 'arrow-up' : 'minus' }}"></i> {{ $bizThisMonth }}
                </span>
                this month
            </div>
        </div>

        {{-- Total Accounts --}}
        <div class="adash-card">
            <div class="adash-card-icon" style="background:color-mix(in srgb,#10b981 12%,transparent);color:#059669;">
                <i class="fa fa-building-columns"></i>
            </div>
            <div class="adash-card-label">Bank Accounts</div>
            <div class="adash-card-value">{{ number_format($totalAccounts) }}</div>
            <div class="adash-card-sub">
                <span class="adash-badge adash-badge--{{ $accountsThisMonth > 0 ? 'up' : 'neutral' }}">
                    <i class="fa fa-{{ $accountsThisMonth > 0 ? 'arrow-up' : 'minus' }}"></i> {{ $accountsThisMonth }}
                </span>
                this month
            </div>
        </div>

    </div>

    {{-- ── CHARTS ── --}}
    <div class="adash-charts">

        {{-- Line chart: user + business joins per month --}}
        <div class="adash-chart-card">
            <h3 class="adash-chart-title">
                <i class="fa fa-chart-line" style="color:var(--primary);"></i> Registrations — last 12 months
            </h3>
            <div class="adash-chart-wrap" style="height:240px;">
                <canvas id="adashLineChart"></canvas>
            </div>
        </div>

        {{-- Doughnut: user vs business ratio --}}
        <div class="adash-chart-card">
            <h3 class="adash-chart-title">
                <i class="fa fa-chart-pie" style="color:var(--primary);"></i> Platform breakdown
            </h3>
            <div class="adash-chart-wrap" style="height:240px;display:flex;align-items:center;justify-content:center;">
                <canvas id="adashDonutChart" style="max-width:220px;max-height:220px;"></canvas>
            </div>
        </div>

    </div>

    {{-- ── USER SEATS & EXTRA USERS ── --}}
    @php
        $so = $seatOverview;
        $soMoney = fn ($totals) => $totals->isEmpty() ? '—' : $totals->map(fn ($t, $c) => $c.' '.number_format($t, 2))->implode(' · ');
    @endphp
    <div class="adash-recent" style="margin-bottom:28px;">
        <div class="adash-recent-head">
            <h3 class="adash-recent-title">
                <i class="fa fa-users-gear" style="color:var(--primary);margin-right:7px;"></i>User Seats &amp; Extra Users
                <span class="adash-recent-head-meta">{{ $so['limited_businesses'] }} {{ $so['limited_businesses'] === 1 ? 'business' : 'businesses' }} on plans with a user limit</span>
            </h3>
            <a href="{{ route('admin.payments.index', ['type' => 'extra_users']) }}" class="adash-recent-link">Extra-user payments <i class="fa fa-arrow-right"></i></a>
        </div>

        <div class="adash-seat-stats">
            <div class="adash-seat-stat">
                <div class="adash-card-label">Extra users sold</div>
                <div class="adash-seat-value">{{ number_format($so['extra_seats_total']) }}</div>
                <div class="adash-card-sub">across {{ $so['businesses_with_extras'] }} {{ $so['businesses_with_extras'] === 1 ? 'business' : 'businesses' }} · {{ $so['extra_purchases_this_month'] }} bought this month</div>
            </div>
            <div class="adash-seat-stat">
                <div class="adash-card-label">Extra users / month</div>
                <div class="adash-seat-value adash-seat-value--sm">{{ $soMoney($so['extra_mrr']) }}</div>
                <div class="adash-card-sub">added to subscriptions every renewal</div>
            </div>
            <div class="adash-seat-stat">
                <div class="adash-card-label">Extra users collected</div>
                <div class="adash-seat-value adash-seat-value--sm">{{ $soMoney($so['extra_revenue']) }}</div>
                <div class="adash-card-sub">first-month seat purchases (paid)</div>
            </div>
            <div class="adash-seat-stat">
                <div class="adash-card-label">At the user limit</div>
                <div class="adash-seat-value" style="{{ $so['full_businesses'] ? 'color:#d97706;' : '' }}">{{ $so['full_businesses'] }}</div>
                <div class="adash-card-sub">{{ $so['near_full_businesses'] }} more at 80%+ — likely to buy extra users</div>
            </div>
        </div>

        @if($so['rows']->isEmpty())
            <div class="adash-empty">No business is on a package with a user limit yet. Set “Max users” on a package in Package Management.</div>
        @else
            <div class="adash-seat-table-wrap">
                <table class="adash-seat-table">
                    <thead>
                        <tr>
                            <th>Business</th>
                            <th>Plan</th>
                            <th>Users</th>
                            <th class="num">Extra users</th>
                            <th class="num">Monthly total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($so['rows'] as $r)
                            @php $pct = min(100, (int) round($r['usage_ratio'] * 100)); @endphp
                            <tr>
                                <td>
                                    @if($r['owner_id'])
                                        <a href="{{ route('admin.users.show', $r['owner_id']) }}" class="adash-recent-name" style="text-decoration:none;">{{ $r['business'] }}</a>
                                    @else
                                        <span class="adash-recent-name">{{ $r['business'] }}</span>
                                    @endif
                                    <div class="adash-recent-email">{{ $r['owner_email'] ?? '—' }}</div>
                                </td>
                                <td>{{ $r['plan'] ?? '—' }}</td>
                                <td style="min-width:150px;">
                                    <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;">
                                        <span>{{ $r['users_used'] }} / {{ $r['user_limit'] }}</span>
                                        @if($r['is_full'])
                                            <span class="adash-seat-flag adash-seat-flag--full">Full</span>
                                        @elseif($r['is_near_full'])
                                            <span class="adash-seat-flag adash-seat-flag--near">{{ $pct }}%</span>
                                        @endif
                                    </div>
                                    <div class="adash-seat-bar"><div style="width:{{ $pct }}%;background:{{ $r['is_full'] ? '#d97706' : ($r['is_near_full'] ? '#f59e0b' : 'var(--primary)') }};"></div></div>
                                    <div class="adash-recent-email">{{ $r['included_users'] }} {{ $r['custom_limit'] ? 'custom limit' : 'included' }}{{ $r['extra_users'] ? ' + '.$r['extra_users'].' extra' : '' }}</div>
                                </td>
                                <td class="num">
                                    @if($r['extra_users'])
                                        <strong>+{{ $r['extra_users'] }}</strong>
                                        <div class="adash-recent-email">{{ $r['currency'] }} {{ number_format($r['extra_monthly'], 2) }} / mo</div>
                                    @else
                                        <span class="adash-recent-email">—</span>
                                    @endif
                                </td>
                                <td class="num">
                                    <strong>{{ $r['currency'] }} {{ number_format($r['monthly_total'], 2) }}</strong>
                                    <div class="adash-recent-email">plan {{ number_format($r['base_amount'], 2) }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ── RECENT USERS ── --}}
    <div class="adash-recent" style="margin-bottom:28px;">
        <div class="adash-recent-head">
            <h3 class="adash-recent-title"><i class="fa fa-clock-rotate-left" style="color:var(--primary);margin-right:7px;"></i>Recently Joined</h3>
            <a href="{{ route('admin.users.index') }}" class="adash-recent-link">View all <i class="fa fa-arrow-right"></i></a>
        </div>
        @foreach($recentUsers as $ru)
            @php $rRole = $ru->roles->first()->name ?? 'user'; @endphp
            <div class="adash-recent-row">
                <div class="adash-avatar">{{ strtoupper(substr($ru->name ?: '?', 0, 1)) }}</div>
                <div style="flex:1;min-width:0;">
                    <div class="adash-recent-name">{{ $ru->name }}</div>
                    <div class="adash-recent-email">{{ $ru->email }}</div>
                </div>
                <div class="adash-recent-time" title="{{ $ru->created_at?->format('d M Y, H:i') }}">
                    {{ $ru->created_at?->diffForHumans() }}
                </div>
                <span class="adash-recent-role adash-recent-role--{{ $rRole }}">{{ ucfirst($rRole) }}</span>
            </div>
        @endforeach
    </div>

    {{-- ── RECENT PAYMENTS ── --}}
    <div class="adash-recent" style="margin-bottom:28px;">
        <div class="adash-recent-head">
            <h3 class="adash-recent-title">
                <i class="fa fa-credit-card" style="color:var(--primary);margin-right:7px;"></i>Payment History
                @if($paymentSummary['revenue']->isNotEmpty())
                    <span class="adash-recent-head-meta">
                        {{ $paymentSummary['revenue']->map(fn ($t, $c) => $c.' '.number_format($t, 2))->implode(' · ') }} collected
                    </span>
                @endif
            </h3>
            <a href="{{ route('admin.payments.index') }}" class="adash-recent-link">View all <i class="fa fa-arrow-right"></i></a>
        </div>
        @forelse($recentPayments as $rp)
            @php $rpDate = $rp->paid_at ?? $rp->created_at; @endphp
            <a href="{{ route('admin.payments.show', $rp->id) }}" class="adash-recent-row" title="View payment details">
                <div class="adash-pay-icon"><i class="fa fa-receipt"></i></div>
                <div style="flex:1;min-width:0;">
                    <div class="adash-recent-name">{{ $rp->business?->name ?? 'Unknown business' }}</div>
                    <div class="adash-recent-email">{{ $rp->package?->name ?? ucfirst($rp->payment_type) }}@if($rp->isExtraUsers()) · <strong style="color:var(--primary);">Extra users (+{{ (int) ($rp->metadata['quantity'] ?? 1) }})</strong>@endif · {{ $rp->user?->email ?? '—' }}</div>
                </div>
                <div class="adash-recent-time" title="{{ $rpDate?->format('d M Y, H:i') }}">{{ $rpDate?->diffForHumans() }}</div>
                <div class="adash-pay-amount">{{ strtoupper($rp->currency ?? 'USD') }} {{ number_format((float) $rp->amount, 2) }}</div>
                <span class="adash-pay-status adash-pay-status--{{ $rp->payment_status }}">{{ $rp->payment_status }}</span>
            </a>
        @empty
            <div class="adash-empty">No payments recorded yet.</div>
        @endforelse
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    var labels   = @json($chartLabels);
    var userData = @json($chartUsers);
    var bizData  = @json($chartBiz);

    /* ── detect theme accent colour ── */
    var style   = getComputedStyle(document.documentElement);
    var primary = style.getPropertyValue('--primary').trim() || '#6366f1';
    var muted   = style.getPropertyValue('--muted').trim()   || '#94a3b8';
    var text    = style.getPropertyValue('--text').trim()     || '#f1f5f9';
    var border  = style.getPropertyValue('--border').trim()   || '#1e293b';

    Chart.defaults.color = muted;
    Chart.defaults.borderColor = border;
    Chart.defaults.font.family = style.fontFamily || 'inherit';

    /* ── Line chart ── */
    var lineCtx = document.getElementById('adashLineChart').getContext('2d');
    var gradU = lineCtx.createLinearGradient(0, 0, 0, 240);
    gradU.addColorStop(0, 'rgba(99,102,241,.28)');
    gradU.addColorStop(1, 'rgba(99,102,241,0)');
    var gradB = lineCtx.createLinearGradient(0, 0, 0, 240);
    gradB.addColorStop(0, 'rgba(245,158,11,.22)');
    gradB.addColorStop(1, 'rgba(245,158,11,0)');

    new Chart(lineCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Users joined',
                    data: userData,
                    borderColor: '#6366f1',
                    backgroundColor: gradU,
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#6366f1',
                    tension: .38,
                    fill: true,
                },
                {
                    label: 'Businesses created',
                    data: bizData,
                    borderColor: '#f59e0b',
                    backgroundColor: gradB,
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#f59e0b',
                    tension: .38,
                    fill: true,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 18, usePointStyle: true } },
                tooltip: { padding: 10, cornerRadius: 10 },
            },
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 35 } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: border } },
            },
        },
    });

    /* ── Doughnut chart ── */
    var donutCtx = document.getElementById('adashDonutChart').getContext('2d');
    new Chart(donutCtx, {
        type: 'doughnut',
        data: {
            labels: ['Users', 'Businesses', 'Accounts'],
            datasets: [{
                data: [{{ $totalUsers }}, {{ $totalBusinesses }}, {{ $totalAccounts }}],
                backgroundColor: ['#6366f1', '#f59e0b', '#10b981'],
                hoverOffset: 8,
                borderWidth: 3,
                borderColor: style.getPropertyValue('--card').trim() || '#0f172a',
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '68%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14, usePointStyle: true } },
                tooltip: { padding: 10, cornerRadius: 10 },
            },
        },
    });
})();
</script>
@endsection
