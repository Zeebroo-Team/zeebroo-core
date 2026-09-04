@extends('theme::layouts.app', ['title' => $user->name, 'heading' => $user->name])

@section('content')
<style>
.ads-wrap{max-width:1280px;margin:0 auto;}
.ads-back{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);text-decoration:none;margin-bottom:10px;}
.ads-back:hover{color:var(--primary);}
.ads-header{display:flex;align-items:center;gap:16px;margin-bottom:22px;flex-wrap:wrap;}
.ads-avatar{width:56px;height:56px;border-radius:50%;background:color-mix(in srgb,var(--primary) 14%,transparent);
    display:grid;place-items:center;font-size:22px;font-weight:700;color:var(--primary);flex-shrink:0;}
.ads-name{margin:0;font-size:21px;font-weight:800;letter-spacing:-.02em;display:flex;align-items:center;gap:8px;}
.ads-email{margin:3px 0 0;font-size:13px;color:var(--muted);}
.ads-meta{margin:6px 0 0;font-size:12px;color:var(--muted);display:flex;flex-wrap:wrap;gap:10px;}
.ads-role{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;}
.ads-role--admin{background:color-mix(in srgb,#6366f1 13%,transparent);color:#6366f1;}
.ads-role--user{background:color-mix(in srgb,#64748b 13%,transparent);color:#64748b;}
.ads-protected{font-size:11px;color:var(--muted);font-style:italic;}
.ads-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:22px;}
@media(max-width:760px){.ads-summary{grid-template-columns:repeat(2,minmax(0,1fr));}}
.ads-stat{padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:var(--card);}
.ads-stat-label{margin:0;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
.ads-stat-value{margin:5px 0 0;font-size:20px;font-weight:800;color:var(--text);}
.ads-section{margin-bottom:22px;}
.ads-section-title{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:800;margin:0 0 10px;}
.ads-section-title .count{font-size:11px;font-weight:700;padding:1px 8px;border-radius:999px;background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--muted);}
.ads-card{border:1px solid var(--border);border-radius:14px;background:var(--card);overflow:hidden;}
.ads-biz{padding:16px 18px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);}
.ads-biz:last-child{border-bottom:none;}
.ads-biz-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap;}
.ads-biz-name{margin:0;font-size:15px;font-weight:750;}
.ads-biz-cat{font-size:11px;color:var(--muted);text-transform:capitalize;}
.ads-biz-date{font-size:11.5px;color:var(--muted);white-space:nowrap;}
.ads-branch-list{margin:12px 0 0;padding:0;list-style:none;display:grid;gap:8px;}
.ads-branch{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 12px;border-radius:10px;background:color-mix(in srgb,var(--primary) 4%,transparent);border:1px solid color-mix(in srgb,var(--border) 60%,transparent);flex-wrap:wrap;}
.ads-branch-name{font-size:13px;font-weight:650;}
.ads-branch-meta{font-size:11.5px;color:var(--muted);}
.ads-branch-badge{font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;}
.ads-branch-badge--active{background:color-mix(in srgb,#22c55e 14%,transparent);color:#16a34a;}
.ads-branch-badge--inactive{background:color-mix(in srgb,#94a3b8 16%,transparent);color:#64748b;}
.ads-empty-inline{font-size:12.5px;color:var(--muted);margin:10px 0 0;}
.ads-biz-tabs{display:flex;flex-wrap:wrap;gap:5px;margin:14px 0 12px;border-bottom:1px solid color-mix(in srgb,var(--border) 75%,transparent);}
.ads-biz-tab{display:inline-flex;align-items:center;gap:6px;padding:7px 12px 9px;margin:0 0 -1px;font-size:12px;font-weight:700;color:var(--muted);
    border:1px solid transparent;border-bottom:none;border-radius:8px 8px 0 0;background:transparent;cursor:pointer;font-family:inherit;}
.ads-biz-tab:hover{color:var(--text);background:color-mix(in srgb,var(--card) 90%,transparent);}
.ads-biz-tab.is-active{color:var(--text);border-color:color-mix(in srgb,var(--primary) 30%,var(--border));background:color-mix(in srgb,var(--primary) 8%,var(--card));}
.ads-biz-panel[hidden]{display:none !important;}
.ads-stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;}
@media(max-width:640px){.ads-stat-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
.ads-mini-stat{padding:10px 12px;border-radius:10px;background:color-mix(in srgb,var(--primary) 4%,transparent);border:1px solid color-mix(in srgb,var(--border) 60%,transparent);}
.ads-mini-stat-label{margin:0;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
.ads-mini-stat-value{margin:4px 0 0;font-size:16px;font-weight:800;color:var(--text);}
.ads-table{width:100%;border-collapse:collapse;}
.ads-table th{padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);background:color-mix(in srgb,var(--card) 88%,var(--border));text-align:left;border-bottom:1px solid var(--border);}
.ads-table td{padding:12px 16px;font-size:13px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);vertical-align:middle;}
.ads-table tr:last-child td{border-bottom:none;}
.ads-empty{padding:36px 20px;text-align:center;color:var(--muted);font-size:13px;}
.ads-activity-list{margin:0;padding:0;list-style:none;}
.ads-activity-item{display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);}
.ads-activity-item:last-child{border-bottom:none;}
.ads-activity-icon{width:32px;height:32px;border-radius:9px;display:grid;place-items:center;flex-shrink:0;font-size:13px;}
.ads-activity-icon--sale{background:color-mix(in srgb,#22c55e 14%,transparent);color:#16a34a;}
.ads-activity-icon--purchase{background:color-mix(in srgb,#3b82f6 14%,transparent);color:#3b82f6;}
.ads-activity-body{flex:1;min-width:0;}
.ads-activity-label{font-size:13px;font-weight:650;}
.ads-activity-meta{font-size:11.5px;color:var(--muted);margin-top:1px;}
.ads-activity-amount{font-size:13px;font-weight:700;white-space:nowrap;}
.ads-conn-lastseen{margin:0;font-size:12.5px;color:var(--text);display:flex;align-items:center;gap:8px;}
.ads-conn-list{margin:14px 0 0;padding:0;list-style:none;display:grid;gap:8px;}
.ads-conn-item{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:10px 12px;border-radius:10px;background:color-mix(in srgb,var(--primary) 4%,transparent);border:1px solid color-mix(in srgb,var(--border) 60%,transparent);flex-wrap:wrap;}
.ads-conn-provider{font-size:13px;font-weight:650;}
.ads-conn-meta{font-size:11.5px;color:var(--muted);margin-top:1px;}
@media(max-width:640px){
    .ads-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;}
    .ads-table, .ads-table tbody, .ads-table tr, .ads-table td{display:block;width:100%;}
    .ads-table tr{padding:12px 16px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);}
    .ads-table tr:last-child{border-bottom:none;}
    .ads-table td{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:4px 0;border-bottom:none;text-align:right;}
    .ads-table td::before{content:attr(data-label);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);text-align:left;flex-shrink:0;}
}
</style>

<div class="ads-wrap">
    <a href="{{ route('admin.users.index') }}" class="ads-back"><i class="fa fa-arrow-left"></i> User Accounts</a>

    <div class="ads-header">
        <div class="ads-avatar">{{ strtoupper(substr($user->name ?: '?', 0, 1)) }}</div>
        <div>
            @php $roleName = $user->roles->first()->name ?? 'user'; @endphp
            <h1 class="ads-name">
                {{ $user->name }}
                @if((int) $user->id === (int) auth()->id())<span class="ads-protected">(you)</span>@endif
                <span class="ads-role ads-role--{{ $roleName }}">{{ ucfirst($roleName) }}</span>
            </h1>
            <p class="ads-email">{{ $user->email }}</p>
            <p class="ads-meta">
                <span><i class="fa fa-calendar"></i> Joined {{ $user->created_at?->format('d M Y') }} ({{ $user->created_at?->diffForHumans() }})</span>
                @if($user->email_verified_at)
                    <span><i class="fa fa-circle-check" style="color:#22c55e;"></i> Email verified</span>
                @else
                    <span><i class="fa fa-circle-exclamation" style="color:#f59e0b;"></i> Email not verified</span>
                @endif
                @if($user->google_id)
                    <span><i class="fa fa-google" style="color:#ea4335;"></i> Google-linked account</span>
                @endif
            </p>
        </div>
    </div>

    @php
        $branchCount = $user->businesses->sum(fn ($b) => $b->branches->count());
    @endphp
    <div class="ads-summary">
        <div class="ads-stat">
            <p class="ads-stat-label">Businesses</p>
            <p class="ads-stat-value">{{ $user->businesses->count() }}</p>
        </div>
        <div class="ads-stat">
            <p class="ads-stat-label">Branches</p>
            <p class="ads-stat-value">{{ $branchCount }}</p>
        </div>
        <div class="ads-stat">
            <p class="ads-stat-label">Accounts</p>
            <p class="ads-stat-value">{{ $user->accounts->count() }}</p>
        </div>
        <div class="ads-stat">
            <p class="ads-stat-label">HR employee records</p>
            <p class="ads-stat-value">{{ $user->hrEmployees->count() }}</p>
        </div>
    </div>

    <div class="ads-section">
        <h2 class="ads-section-title"><i class="fa fa-building" style="color:var(--primary);"></i> Registered businesses <span class="count">{{ $user->businesses->count() }}</span></h2>

        @if($user->businesses->isEmpty())
            <div class="ads-card"><div class="ads-empty">No businesses registered by this user.</div></div>
        @else
            <div class="ads-card">
                @foreach($user->businesses as $business)
                    @php $stats = $businessStats[$business->id]; @endphp
                    <div class="ads-biz">
                        <div class="ads-biz-head">
                            <div>
                                <p class="ads-biz-name">{{ $business->name }}</p>
                                @if($business->category)<p class="ads-biz-cat">{{ str_replace('_', ' ', $business->category) }}</p>@endif
                            </div>
                            <span class="ads-biz-date" title="{{ $business->created_at?->format('d M Y, H:i:s') }}">Registered {{ $business->created_at?->format('d M Y') }}</span>
                        </div>

                        @if($business->branches->isEmpty())
                            <p class="ads-empty-inline">No branches added for this business.</p>
                        @else
                            <ul class="ads-branch-list">
                                @foreach($business->branches as $branch)
                                    <li class="ads-branch">
                                        <div>
                                            <div class="ads-branch-name">{{ $branch->name }}</div>
                                            <div class="ads-branch-meta">
                                                @if($branch->address){{ $branch->address }}@endif
                                                @if($branch->phone)@if($branch->address) · @endif{{ $branch->phone }}@endif
                                                @if(! $branch->address && ! $branch->phone)No address or phone on file @endif
                                            </div>
                                        </div>
                                        <span class="ads-branch-badge {{ $branch->is_active ? 'ads-branch-badge--active' : 'ads-branch-badge--inactive' }}">{{ $branch->is_active ? 'Active' : 'Inactive' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="ads-biz-tabs" role="tablist" aria-label="{{ $business->name }} analysis">
                            <button type="button" class="ads-biz-tab is-active" data-tab="overview"><i class="fa fa-chart-simple"></i> Overview</button>
                            <button type="button" class="ads-biz-tab" data-tab="sales"><i class="fa fa-cart-shopping"></i> Sales &amp; Purchases</button>
                            <button type="button" class="ads-biz-tab" data-tab="hr"><i class="fa fa-people-group"></i> HR</button>
                            <button type="button" class="ads-biz-tab" data-tab="crm"><i class="fa fa-bullseye"></i> CRM</button>
                            <button type="button" class="ads-biz-tab" data-tab="docs"><i class="fa fa-file-invoice-dollar"></i> Quotes &amp; Invoices</button>
                        </div>

                        <div class="ads-biz-panel" data-panel="overview">
                            <div class="ads-stat-grid">
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Products</p>
                                    <p class="ads-mini-stat-value">{{ $stats['overview']['products_active'] }} <span style="font-size:11px;font-weight:600;color:var(--muted);">/ {{ $stats['overview']['products_total'] }}</span></p>
                                </div>
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Stock value (cost)</p>
                                    <p class="ads-mini-stat-value">{{ $stats['currency'] }} {{ number_format($stats['overview']['stock_value'], 2) }}</p>
                                </div>
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Suppliers</p>
                                    <p class="ads-mini-stat-value">{{ $stats['overview']['suppliers_count'] }}</p>
                                </div>
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Customers</p>
                                    <p class="ads-mini-stat-value">{{ $stats['overview']['customers_count'] }}</p>
                                </div>
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Branches</p>
                                    <p class="ads-mini-stat-value">{{ $stats['overview']['branches_count'] }}</p>
                                </div>
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Storage used</p>
                                    <p class="ads-mini-stat-value">{{ $stats['overview']['storage_human'] }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="ads-biz-panel" data-panel="sales" hidden>
                            <div class="ads-stat-grid">
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Completed sales</p>
                                    <p class="ads-mini-stat-value">{{ $stats['sales_purchases']['sales_count'] }}</p>
                                </div>
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Sales revenue</p>
                                    <p class="ads-mini-stat-value">{{ $stats['currency'] }} {{ number_format($stats['sales_purchases']['sales_total'], 2) }}</p>
                                </div>
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Received purchases</p>
                                    <p class="ads-mini-stat-value">{{ $stats['sales_purchases']['purchases_count'] }}</p>
                                </div>
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Purchase spend</p>
                                    <p class="ads-mini-stat-value">{{ $stats['currency'] }} {{ number_format($stats['sales_purchases']['purchases_total'], 2) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="ads-biz-panel" data-panel="hr" hidden>
                            <div class="ads-stat-grid">
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Employees</p>
                                    <p class="ads-mini-stat-value">{{ $stats['hr']['employees_count'] }}</p>
                                </div>
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Departments</p>
                                    <p class="ads-mini-stat-value">{{ $stats['hr']['departments_count'] }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="ads-biz-panel" data-panel="crm" hidden>
                            <div class="ads-stat-grid">
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Leads</p>
                                    <p class="ads-mini-stat-value">{{ $stats['crm']['leads_count'] }}</p>
                                </div>
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Won / Lost</p>
                                    <p class="ads-mini-stat-value">{{ $stats['crm']['leads_won'] }} / {{ $stats['crm']['leads_lost'] }}</p>
                                </div>
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Pipeline value</p>
                                    <p class="ads-mini-stat-value">{{ $stats['currency'] }} {{ number_format($stats['crm']['leads_value'], 2) }}</p>
                                </div>
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Projects</p>
                                    <p class="ads-mini-stat-value">{{ $stats['crm']['projects_active'] }} <span style="font-size:11px;font-weight:600;color:var(--muted);">/ {{ $stats['crm']['projects_count'] }}</span></p>
                                </div>
                            </div>
                        </div>

                        <div class="ads-biz-panel" data-panel="docs" hidden>
                            <div class="ads-stat-grid">
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Quotations</p>
                                    <p class="ads-mini-stat-value">{{ $stats['quotes_invoices']['quotations_accepted'] }} <span style="font-size:11px;font-weight:600;color:var(--muted);">/ {{ $stats['quotes_invoices']['quotations_count'] }} accepted</span></p>
                                </div>
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Invoices</p>
                                    <p class="ads-mini-stat-value">{{ $stats['quotes_invoices']['invoices_count'] }}</p>
                                </div>
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Invoiced &amp; paid</p>
                                    <p class="ads-mini-stat-value">{{ $stats['currency'] }} {{ number_format($stats['quotes_invoices']['invoices_paid_total'], 2) }}</p>
                                </div>
                                <div class="ads-mini-stat">
                                    <p class="ads-mini-stat-label">Outstanding</p>
                                    <p class="ads-mini-stat-value">{{ $stats['currency'] }} {{ number_format($stats['quotes_invoices']['invoices_outstanding_total'], 2) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="ads-section">
        <h2 class="ads-section-title"><i class="fa fa-user-tag" style="color:var(--primary);"></i> Staff access at other businesses <span class="count">{{ $memberships->count() }}</span></h2>

        @if($memberships->isEmpty())
            <div class="ads-card"><div class="ads-empty">Not a staff member of any other business.</div></div>
        @else
            <div class="ads-card" style="overflow-x:auto;">
                <table class="ads-table">
                    <thead>
                        <tr>
                            <th>Business</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Since</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($memberships as $membership)
                            <tr>
                                <td data-label="Business">{{ $membership->business?->name ?: '—' }}</td>
                                <td data-label="Role"><span class="ads-role" style="background:color-mix(in srgb,{{ $membership->roleBadgeColor() }} 15%,transparent);color:{{ $membership->roleBadgeColor() }};">{{ $membership->roleLabel() }}</span></td>
                                <td data-label="Status" style="text-transform:capitalize;">{{ $membership->status }}</td>
                                <td data-label="Since">{{ $membership->created_at?->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="ads-section">
        <h2 class="ads-section-title"><i class="fa fa-clock-rotate-left" style="color:var(--primary);"></i> Recent activity</h2>

        @if($recentActivity->isEmpty())
            <div class="ads-card"><div class="ads-empty">No completed sales or received purchases yet.</div></div>
        @else
            <div class="ads-card">
                <ul class="ads-activity-list">
                    @foreach($recentActivity as $item)
                        <li class="ads-activity-item">
                            <span class="ads-activity-icon ads-activity-icon--{{ $item['type'] }}">
                                <i class="fa fa-{{ $item['type'] === 'sale' ? 'cart-shopping' : 'truck-ramp-box' }}"></i>
                            </span>
                            <div class="ads-activity-body">
                                <div class="ads-activity-label">{{ $item['label'] }}</div>
                                <div class="ads-activity-meta">{{ $item['business'] }} · {{ $item['date']?->format('d M Y, H:i') }}</div>
                            </div>
                            <div class="ads-activity-amount">{{ $item['currency'] }} {{ number_format($item['amount'], 2) }}</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="ads-section">
        <h2 class="ads-section-title"><i class="fa fa-wallet" style="color:var(--primary);"></i> Financial accounts <span class="count">{{ $user->accounts->count() }}</span></h2>

        @if($user->accounts->isEmpty())
            <div class="ads-card"><div class="ads-empty">No financial accounts registered by this user.</div></div>
        @else
            <div class="ads-card" style="overflow-x:auto;">
                <table class="ads-table">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Category</th>
                            <th>Bank</th>
                            <th>Business / Branch</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($user->accounts as $account)
                            @php
                                $acctCurrency = $account->business
                                    ? (string) (get_settings('business.currency', '', $account->business) ?: 'LKR')
                                    : 'LKR';
                            @endphp
                            <tr>
                                <td data-label="Account">{{ $account->account_name }}</td>
                                <td data-label="Category" style="text-transform:capitalize;">{{ str_replace('_', ' ', $account->category) }}</td>
                                <td data-label="Bank">{{ $account->bank?->name ?: $account->bank_name ?: '—' }}</td>
                                <td data-label="Business / Branch">
                                    {{ $account->business?->name ?: '—' }}
                                    @if($account->warehouse)<div style="font-size:11.5px;color:var(--muted);">{{ $account->warehouse->name }}</div>@endif
                                </td>
                                <td data-label="Balance">{{ $acctCurrency }} {{ number_format((float) $account->current_balance, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="ads-section">
        <h2 class="ads-section-title"><i class="fa fa-display" style="color:var(--primary);"></i> Platform activity</h2>
        <div class="ads-card" style="padding:16px 18px;">
            <p class="ads-conn-lastseen">
                <i class="fa fa-flag"></i>
                Registered via
                @if($registeredPlatform === \App\Models\UserActivityLog::PLATFORM_DESKTOP)
                    <strong>pos-desktop software</strong>
                @elseif($registeredPlatform === \App\Models\UserActivityLog::PLATFORM_WEB)
                    <strong>online platform</strong>
                @else
                    <strong>online platform</strong> <span style="color:var(--muted);">(assumed — before activity tracking)</span>
                @endif
            </p>
            <div class="ads-stat-grid" style="margin-top:10px;">
                <div class="ads-mini-stat">
                    <p class="ads-mini-stat-label"><i class="fa fa-globe"></i> Online platform</p>
                    <p class="ads-stat-value" style="font-size:14px;">
                        @if($lastActiveByPlatform[\App\Models\UserActivityLog::PLATFORM_WEB] ?? null)
                            {{ $lastActiveByPlatform[\App\Models\UserActivityLog::PLATFORM_WEB]->diffForHumans() }}
                        @else
                            Never used
                        @endif
                    </p>
                </div>
                <div class="ads-mini-stat">
                    <p class="ads-mini-stat-label"><i class="fa fa-desktop"></i> Desktop software</p>
                    <p class="ads-stat-value" style="font-size:14px;">
                        @if($lastActiveByPlatform[\App\Models\UserActivityLog::PLATFORM_DESKTOP] ?? null)
                            {{ $lastActiveByPlatform[\App\Models\UserActivityLog::PLATFORM_DESKTOP]->diffForHumans() }}
                        @else
                            Never used
                        @endif
                    </p>
                </div>
            </div>

            @if($platformActivity->isNotEmpty())
                <div style="overflow-x:auto;margin-top:14px;">
                    <table class="ads-table">
                        <thead>
                            <tr>
                                <th>Platform</th>
                                <th>Event</th>
                                <th>Device</th>
                                <th>IP address</th>
                                <th>When</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($platformActivity as $log)
                                <tr>
                                    <td data-label="Platform" style="text-transform:capitalize;">
                                        <i class="fa {{ $log->platform === \App\Models\UserActivityLog::PLATFORM_DESKTOP ? 'fa-desktop' : 'fa-globe' }}"></i>
                                        {{ $log->platform === \App\Models\UserActivityLog::PLATFORM_DESKTOP ? 'Desktop' : 'Online' }}
                                    </td>
                                    <td data-label="Event" style="text-transform:capitalize;">{{ $log->event }}</td>
                                    <td data-label="Device">{{ $log->device_name ?: '—' }}</td>
                                    <td data-label="IP address">{{ $log->ip_address ?: '—' }}</td>
                                    <td data-label="When">{{ $log->created_at?->format('d M Y, H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:12px;">{{ $platformActivity->links() }}</div>
            @endif

            <p class="ads-conn-lastseen" style="margin-top:14px;">
                <i class="fa fa-signal"></i>
                @if($lastSeenAt)
                    Last web session seen {{ $lastSeenAt->diffForHumans() }} <span style="color:var(--muted);">({{ $lastSeenAt->format('d M Y, H:i') }})</span>
                @else
                    No recent web session activity on record.
                @endif
            </p>

            @if($user->appConnections->isEmpty())
                <p class="ads-empty-inline" style="margin-top:12px;">No linked third-party app connections.</p>
            @else
                <ul class="ads-conn-list">
                    @foreach($user->appConnections as $connection)
                        <li class="ads-conn-item">
                            <div>
                                <div class="ads-conn-provider">{{ ucfirst($connection->provider) }}</div>
                                <div class="ads-conn-meta">{{ $connection->email ?: $connection->name ?: $connection->provider_user_id }}</div>
                            </div>
                            <div class="ads-conn-meta" style="text-align:right;">
                                Linked {{ $connection->created_at?->format('d M Y') }}
                                @if($connection->token_expires_at)
                                    <div>Token expires {{ $connection->token_expires_at->format('d M Y') }}</div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @if($user->hrEmployees->isNotEmpty())
        <div class="ads-section">
            <h2 class="ads-section-title"><i class="fa fa-id-badge" style="color:var(--primary);"></i> HR employee records <span class="count">{{ $user->hrEmployees->count() }}</span></h2>
            <div class="ads-card" style="overflow-x:auto;">
                <table class="ads-table">
                    <thead>
                        <tr>
                            <th>Business</th>
                            <th>Full name</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($user->hrEmployees as $employee)
                            <tr>
                                <td data-label="Business">{{ $employee->business?->name ?: '—' }}</td>
                                <td data-label="Full name">{{ $employee->full_name }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<script>
document.querySelectorAll('.ads-biz-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
        var biz = tab.closest('.ads-biz');
        var target = tab.getAttribute('data-tab');

        biz.querySelectorAll('.ads-biz-tab').forEach(function (t) { t.classList.toggle('is-active', t === tab); });
        biz.querySelectorAll('.ads-biz-panel').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-panel') !== target;
        });
    });
});
</script>
@endsection
