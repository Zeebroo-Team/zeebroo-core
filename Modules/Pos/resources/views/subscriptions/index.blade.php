@extends('theme::layouts.app', ['title' => 'Recurring sales', 'heading' => 'Recurring sales'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.sh-filter-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin-bottom:14px;}
.sh-filter-group{display:flex;flex-direction:column;gap:4px;}
.sh-filter-group label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
.sh-input{padding:7px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);min-width:0;}
.sh-input:focus{outline:none;border-color:var(--primary);}
.sh-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;padding-right:28px;}

.rs-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);white-space:nowrap;}
.rs-badge--trial{border-color:color-mix(in srgb,#3b82f6 45%,var(--border));background:color-mix(in srgb,#3b82f6 10%,transparent);color:#3b82f6;}
.rs-badge--active{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);color:#16a34a;}
.rs-badge--paused{border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 10%,transparent);color:#b45309;}
.rs-badge--cancelled{border-color:color-mix(in srgb,#94a3b8 45%,var(--border));color:var(--muted);}

.rs-actions{display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-end;}
.rs-actions form{display:inline;}
.rs-actions button{padding:5px 10px;font-size:11px;font-weight:700;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);cursor:pointer;}
.rs-actions button:hover{background:color-mix(in srgb,var(--primary) 8%,transparent);}

.sh-pagination{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:14px;font-size:13px;}
.sh-pagination a,.sh-pagination span{padding:5px 10px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);text-decoration:none;font-weight:600;}
.sh-pagination span.sh-page-active{background:color-mix(in srgb,var(--primary) 15%,transparent);border-color:color-mix(in srgb,var(--primary) 50%,var(--border));color:var(--primary);}
.sh-pagination a:hover{background:color-mix(in srgb,var(--primary) 8%,transparent);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner" style="font-weight:600;color:#ef4444;">{{ $errors->first() }}</div>
    @endif

    <form method="get" action="{{ route('pos.subscriptions.index') }}" class="sh-filter-bar">
        <div class="sh-filter-group" style="flex:1;min-width:180px;">
            <label>Search</label>
            <input type="search" name="q" value="{{ $search }}" placeholder="Customer or product…" class="sh-input" style="width:100%;box-sizing:border-box;">
        </div>
        <div class="sh-filter-group">
            <label>Status</label>
            <select name="status" class="sh-input sh-select">
                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All statuses</option>
                @foreach($statusLabels as $key => $label)
                    <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;gap:6px;align-items:flex-end;">
            <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:13px;">Filter</button>
            @if($search !== '' || $status !== 'all')
                <a href="{{ route('pos.subscriptions.index') }}" class="pcat-link" style="font-size:13px;padding:7px 0;">Clear</a>
            @endif
        </div>
    </form>

    <div class="pcat-table-wrap">
        <table class="pcat-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Period</th>
                    <th>Price @if(filled($currency))({{ $currency }})@endif</th>
                    <th>Next billing</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($subscriptions as $subscription)
                    <tr>
                        <td>
                            <span style="color:var(--text);font-weight:600;">{{ $subscription->customer?->name ?? 'Walk-in / Unknown' }}</span>
                            @if($subscription->customer?->phone)
                                <span class="muted" style="display:block;font-size:11px;">{{ $subscription->customer->phone }}</span>
                            @endif
                        </td>
                        <td>
                            {{ $subscription->product?->name ?? '—' }}
                            @if($subscription->product?->sku)
                                <span class="muted" style="display:block;font-size:11px;">{{ $subscription->product->sku }}</span>
                            @endif
                        </td>
                        <td class="muted" style="text-transform:capitalize;">{{ $subscription->recurring_period }}</td>
                        <td><strong style="color:var(--text);">{{ number_format((float) $subscription->price, 2) }}</strong></td>
                        <td class="muted">{{ $subscription->next_billing_at?->format('M j, Y') ?? '—' }}</td>
                        <td>
                            @php $badgeClass = match($subscription->status) {
                                'trial' => 'rs-badge--trial',
                                'active' => 'rs-badge--active',
                                'paused' => 'rs-badge--paused',
                                default => 'rs-badge--cancelled',
                            }; @endphp
                            <span class="rs-badge {{ $badgeClass }}">{{ $statusLabels[$subscription->status] ?? ucfirst($subscription->status) }}</span>
                        </td>
                        <td>
                            <div class="rs-actions">
                                @if($subscription->status !== 'cancelled')
                                    @if($subscription->status === 'paused')
                                        <form method="post" action="{{ route('pos.subscriptions.resume', $subscription) }}">
                                            @csrf
                                            <button type="submit"><i class="fa fa-play"></i> Resume</button>
                                        </form>
                                    @else
                                        <form method="post" action="{{ route('pos.subscriptions.pause', $subscription) }}">
                                            @csrf
                                            <button type="submit"><i class="fa fa-pause"></i> Pause</button>
                                        </form>
                                    @endif
                                    <form method="post" action="{{ route('pos.subscriptions.renew', $subscription) }}">
                                        @csrf
                                        <button type="submit"><i class="fa fa-rotate"></i> Renew</button>
                                    </form>
                                    <form method="post" action="{{ route('pos.subscriptions.notify', $subscription) }}">
                                        @csrf
                                        <button type="submit"><i class="fa fa-envelope"></i> Notify</button>
                                    </form>
                                    <form method="post" action="{{ route('pos.subscriptions.cancel', $subscription) }}" onsubmit="return confirm('Cancel this subscription?');">
                                        @csrf
                                        <button type="submit"><i class="fa fa-ban"></i> Cancel</button>
                                    </form>
                                @else
                                    <span class="muted" style="font-size:12px;">No actions</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:32px;text-align:center;">
                            <p class="muted" style="margin:0;">No recurring sales match your filters.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($subscriptions->hasPages())
    <div class="sh-pagination">
        @if($subscriptions->onFirstPage())
            <span style="opacity:.4;">‹ Prev</span>
        @else
            <a href="{{ $subscriptions->previousPageUrl() }}">‹ Prev</a>
        @endif

        @foreach($subscriptions->getUrlRange(max(1, $subscriptions->currentPage() - 2), min($subscriptions->lastPage(), $subscriptions->currentPage() + 2)) as $page => $url)
            @if($page === $subscriptions->currentPage())
                <span class="sh-page-active">{{ $page }}</span>
            @else
                <a href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach

        @if($subscriptions->hasMorePages())
            <a href="{{ $subscriptions->nextPageUrl() }}">Next ›</a>
        @else
            <span style="opacity:.4;">Next ›</span>
        @endif

        <span class="muted" style="font-size:12px;margin-left:auto;">
            Showing {{ $subscriptions->firstItem() }}–{{ $subscriptions->lastItem() }} of {{ number_format($subscriptions->total()) }} subscriptions
        </span>
    </div>
    @endif
</div>
@endsection
