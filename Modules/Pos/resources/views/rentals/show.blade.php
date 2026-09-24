@php $rental = $row['rental']; @endphp
@extends('theme::layouts.app', ['title' => 'Rental #'.$rental->id, 'heading' => 'Rental detail'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.rt-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);white-space:nowrap;}
.rt-badge--active{border-color:color-mix(in srgb,#3b82f6 45%,var(--border));background:color-mix(in srgb,#3b82f6 10%,transparent);color:#3b82f6;}
.rt-badge--overdue{border-color:color-mix(in srgb,#ef4444 45%,var(--border));background:color-mix(in srgb,#ef4444 10%,transparent);color:#ef4444;}
.rt-badge--returned{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);color:#16a34a;}
.rt-badge--cancelled{border-color:color-mix(in srgb,#94a3b8 45%,var(--border));color:var(--muted);}

.pos-receipt-meta{display:grid;gap:10px;margin-bottom:14px;}
@media (min-width:640px){.pos-receipt-meta{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media (min-width:960px){.pos-receipt-meta{grid-template-columns:repeat(3,minmax(0,1fr));}}
.pos-receipt-meta__card{border:1px solid var(--border);border-radius:10px;padding:10px 12px;background:color-mix(in srgb,var(--card) 96%,transparent);}
.pos-receipt-meta__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin:0 0 4px;}
.pos-receipt-meta__value{margin:0;font-size:14px;font-weight:700;color:var(--text);}

.rt-summary{display:flex;flex-wrap:wrap;gap:10px;margin:14px 0;}
.rt-summary__card{flex:1;min-width:150px;border:1px solid var(--border);border-radius:10px;padding:12px 14px;background:color-mix(in srgb,var(--card) 96%,transparent);}
.rt-summary__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin:0 0 4px;}
.rt-summary__value{margin:0;font-size:18px;font-weight:800;color:var(--text);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:14px;">
        <a href="{{ route('pos.rentals.index') }}" class="pcat-link" style="font-weight:700;"><i class="fa fa-arrow-left"></i> Rentals</a>
        @if($rental->sale)
            <a href="{{ route('pos.sales.show', $rental->sale) }}" class="pcat-link" style="font-weight:700;"><i class="fa fa-receipt"></i> {{ $rental->sale->sale_number }}</a>
        @endif

        @if(in_array($rental->status, ['active', 'overdue'], true))
            <form method="post" action="{{ route('pos.rentals.return', $rental) }}" onsubmit="return confirm('Mark this rental as returned?');" style="margin-left:auto;">
                @csrf
                <button type="submit" class="linkbtn" style="padding:8px 14px;font-size:13px;border:1px solid var(--border);background:var(--card);color:var(--text);border-radius:8px;cursor:pointer;font-weight:700;">
                    <i class="fa fa-box"></i> Mark returned
                </button>
            </form>
        @endif
    </div>

    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:6px;">
        <h2 style="margin:0;font-size:18px;font-weight:800;">Rental #{{ $rental->id }}</h2>
        @php $badgeClass = match($row['status']) {
            'active' => 'rt-badge--active',
            'overdue' => 'rt-badge--overdue',
            'returned' => 'rt-badge--returned',
            default => 'rt-badge--cancelled',
        }; @endphp
        <span class="rt-badge {{ $badgeClass }}">{{ $statusLabels[$row['status']] ?? ucfirst($row['status']) }}</span>
    </div>

    <div class="pos-receipt-meta">
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label"><i class="fa fa-user" style="font-size:9px;"></i> Customer</p>
            <p class="pos-receipt-meta__value">{{ $rental->customer?->name ?? 'Walk-in / Unknown' }}</p>
            @if($rental->customer?->phone)
                <p class="muted" style="margin:4px 0 0;font-size:12px;">{{ $rental->customer->phone }}</p>
            @endif
            @if($rental->customer?->email)
                <p class="muted" style="margin:2px 0 0;font-size:12px;">{{ $rental->customer->email }}</p>
            @endif
        </div>
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label"><i class="fa fa-box" style="font-size:9px;"></i> Product</p>
            <p class="pos-receipt-meta__value">{{ $rental->product?->name ?? '—' }}</p>
            @if($rental->product?->sku)
                <p class="muted" style="margin:4px 0 0;font-size:12px;">SKU: {{ $rental->product->sku }}</p>
            @endif
        </div>
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label">Quantity</p>
            <p class="pos-receipt-meta__value">{{ rtrim(rtrim(number_format((float) $rental->quantity, 3), '0'), '.') }}</p>
            <p class="muted" style="margin:4px 0 0;font-size:12px;">Daily rate: {{ number_format((float) $rental->daily_rate, 2) }} @if(filled($currency)) {{ $currency }} @endif</p>
        </div>
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label">Rented at</p>
            <p class="pos-receipt-meta__value">{{ $rental->rented_at?->format('M j, Y') ?? '—' }}</p>
        </div>
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label">Due date</p>
            <p class="pos-receipt-meta__value">{{ $rental->due_at?->format('M j, Y') ?? '—' }}</p>
            @if($row['status'] === 'overdue')
                <p style="margin:4px 0 0;font-size:12px;color:#ef4444;">{{ $row['days_late'] }} day(s) late</p>
            @elseif($row['status'] === 'active')
                <p class="muted" style="margin:4px 0 0;font-size:12px;">{{ $row['days_remaining'] }} day(s) left</p>
            @endif
        </div>
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label">Returned at</p>
            <p class="pos-receipt-meta__value">{{ $rental->returned_at?->format('M j, Y') ?? '—' }}</p>
        </div>
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label">Rental duration</p>
            <p class="pos-receipt-meta__value">{{ $row['duration_days'] }} day(s)</p>
        </div>
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label">Late fee multiplier</p>
            <p class="pos-receipt-meta__value">{{ number_format((float) $rental->late_fee_multiplier, 2) }}×</p>
        </div>
        @if($rental->sale)
        <div class="pos-receipt-meta__card">
            <p class="pos-receipt-meta__label"><i class="fa fa-receipt" style="font-size:9px;"></i> Sale reference</p>
            <p class="pos-receipt-meta__value">{{ $rental->sale->sale_number }}</p>
        </div>
        @endif
    </div>

    <div class="rt-summary">
        <div class="rt-summary__card">
            <p class="rt-summary__label">Base total</p>
            <p class="rt-summary__value">{{ number_format($row['base_total'], 2) }} @if(filled($currency))<span class="muted" style="font-size:12px;font-weight:600;">{{ $currency }}</span>@endif</p>
        </div>
        <div class="rt-summary__card">
            <p class="rt-summary__label">Late fee</p>
            <p class="rt-summary__value">{{ $row['late_fee'] > 0 ? number_format($row['late_fee'], 2) : '—' }}</p>
        </div>
        <div class="rt-summary__card">
            <p class="rt-summary__label">Total amount</p>
            <p class="rt-summary__value">{{ number_format($row['total_amount'], 2) }} @if(filled($currency))<span class="muted" style="font-size:12px;font-weight:600;">{{ $currency }}</span>@endif</p>
        </div>
    </div>
</div>
@endsection
