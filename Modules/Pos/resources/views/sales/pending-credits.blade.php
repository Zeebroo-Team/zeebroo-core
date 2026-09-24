@extends('theme::layouts.app', ['title' => 'Pending credits', 'heading' => 'Pending credits'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.sh-stats{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:18px;}
@media(min-width:640px){.sh-stats{grid-template-columns:repeat(3,1fr);}}
.sh-stat{border:1px solid var(--border);border-radius:11px;padding:12px 14px;background:var(--card);}
.sh-stat__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:0 0 6px;}
.sh-stat__value{font-size:22px;font-weight:800;color:var(--text);margin:0;line-height:1.15;}
.sh-stat__sub{font-size:11px;color:var(--muted);margin:3px 0 0;}
.sh-stat--red .sh-stat__value{color:#ef4444;}

.pc-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);white-space:nowrap;}
.pc-badge--overdue{border-color:color-mix(in srgb,#ef4444 45%,var(--border));background:color-mix(in srgb,#ef4444 10%,transparent);color:#ef4444;}
.pc-badge--ok{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);color:#16a34a;}

.pc-sales{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:4px;}
.pc-sales li{display:flex;justify-content:space-between;gap:10px;font-size:12px;padding:4px 0;border-bottom:1px dashed var(--border);}
.pc-sales li:last-child{border-bottom:none;}

.sh-pagination{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:14px;font-size:13px;}
.sh-pagination a,.sh-pagination span{padding:5px 10px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--text);text-decoration:none;font-weight:600;}
.sh-pagination span.sh-page-active{background:color-mix(in srgb,var(--primary) 15%,transparent);border-color:color-mix(in srgb,var(--primary) 50%,var(--border));color:var(--primary);}
.sh-pagination a:hover{background:color-mix(in srgb,var(--primary) 8%,transparent);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    <div class="sh-stats">
        <div class="sh-stat">
            <p class="sh-stat__label">Customers owing</p>
            <p class="sh-stat__value">{{ number_format($customerCount) }}</p>
            <p class="sh-stat__sub">with outstanding credit sales</p>
        </div>
        <div class="sh-stat">
            <p class="sh-stat__label">Total owed @if(filled($currency))({{ $currency }})@endif</p>
            <p class="sh-stat__value">{{ number_format($totalOwed, 2) }}</p>
            <p class="sh-stat__sub">across all pending credit sales</p>
        </div>
        <div class="sh-stat sh-stat--red">
            <p class="sh-stat__label">Overdue @if(filled($currency))({{ $currency }})@endif</p>
            <p class="sh-stat__value">{{ number_format($totalOverdue, 2) }}</p>
            <p class="sh-stat__sub">past the credit due date</p>
        </div>
    </div>

    <div class="pcat-table-wrap">
        <table class="pcat-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Sales</th>
                    <th>Total owed @if(filled($currency))({{ $currency }})@endif</th>
                    <th>Overdue @if(filled($currency))({{ $currency }})@endif</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($groups as $group)
                    <tr>
                        <td>
                            <span style="color:var(--text);font-weight:600;">{{ $group['customer_name'] }}</span>
                            @if($group['customer_phone'])
                                <span class="muted" style="display:block;font-size:11px;">{{ $group['customer_phone'] }}</span>
                            @endif
                        </td>
                        <td>
                            <ul class="pc-sales">
                                @foreach($group['sales'] as $sale)
                                    <li>
                                        <a href="{{ route('pos.sales.show', $sale['id']) }}" style="color:var(--text);text-decoration:none;font-weight:700;">{{ $sale['sale_number'] }}</a>
                                        <span class="muted">{{ $sale['sold_at']?->format('M j, Y') ?? '—' }}</span>
                                        <span>{{ number_format($sale['total'], 2) }}</span>
                                        @if($sale['is_overdue'])
                                            <span class="pc-badge pc-badge--overdue"><i class="fa fa-triangle-exclamation"></i> Due {{ $sale['credit_due_date']?->format('M j, Y') }}</span>
                                        @elseif($sale['credit_due_date'])
                                            <span class="muted">Due {{ $sale['credit_due_date']->format('M j, Y') }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </td>
                        <td><strong style="color:var(--text);font-size:14px;">{{ number_format($group['total_owed'], 2) }}</strong></td>
                        <td>{{ $group['overdue_amount'] > 0 ? number_format($group['overdue_amount'], 2) : '—' }}</td>
                        <td>
                            @if($group['has_overdue'])
                                <span class="pc-badge pc-badge--overdue"><i class="fa fa-triangle-exclamation"></i> Overdue</span>
                            @else
                                <span class="pc-badge pc-badge--ok"><i class="fa fa-check"></i> On time</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding:32px;text-align:center;">
                            <p class="muted" style="margin:0;">No pending credit sales.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($groups->hasPages())
    <div class="sh-pagination">
        @if($groups->onFirstPage())
            <span style="opacity:.4;">‹ Prev</span>
        @else
            <a href="{{ $groups->previousPageUrl() }}">‹ Prev</a>
        @endif

        @foreach($groups->getUrlRange(max(1, $groups->currentPage() - 2), min($groups->lastPage(), $groups->currentPage() + 2)) as $page => $url)
            @if($page === $groups->currentPage())
                <span class="sh-page-active">{{ $page }}</span>
            @else
                <a href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach

        @if($groups->hasMorePages())
            <a href="{{ $groups->nextPageUrl() }}">Next ›</a>
        @else
            <span style="opacity:.4;">Next ›</span>
        @endif

        <span class="muted" style="font-size:12px;margin-left:auto;">
            Showing {{ $groups->firstItem() }}–{{ $groups->lastItem() }} of {{ number_format($groups->total()) }} customers
        </span>
    </div>
    @endif
</div>
@endsection
