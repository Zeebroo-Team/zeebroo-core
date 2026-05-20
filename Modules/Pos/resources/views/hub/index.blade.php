@extends('theme::layouts.app', ['title' => 'Sales & POS', 'heading' => 'Sales & point of sale'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.pos-hub-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-top:8px;}
.pos-hub-tile{border:1px solid var(--border);border-radius:12px;padding:16px;background:color-mix(in srgb,var(--card) 96%,transparent);text-decoration:none;color:inherit;display:flex;flex-direction:column;gap:8px;transition:border-color .2s ease,transform .15s ease;}
.pos-hub-tile:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));transform:translateY(-1px);}
.pos-hub-tile__icon{width:40px;height:40px;border-radius:10px;display:grid;place-items:center;background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);font-size:18px;}
.pos-hub-tile__title{margin:0;font-size:15px;font-weight:800;color:var(--text);}
.pos-hub-tile__desc{margin:0;font-size:12px;line-height:1.45;color:var(--muted);}
.pos-hub-stats{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));margin:14px 0;}
.pos-hub-stat{border:1px solid var(--border);border-radius:10px;padding:12px;background:color-mix(in srgb,var(--card) 96%,transparent);}
.pos-hub-stat__label{margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
.pos-hub-stat__value{margin:0;font-size:20px;font-weight:800;color:var(--text);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Retail sales and online point of sale for <strong style="color:var(--text);">{{ $business->name }}</strong>.
        Stock is sold using FIFO batch pricing from goods receipts.
    </p>

    <div class="pos-hub-stats" aria-label="Today's sales">
        <div class="pos-hub-stat">
            <p class="pos-hub-stat__label">Today's sales</p>
            <p class="pos-hub-stat__value">{{ (int) ($today['count'] ?? 0) }}</p>
        </div>
        <div class="pos-hub-stat">
            <p class="pos-hub-stat__label">Today's revenue @if(filled($currency))({{ $currency }})@endif</p>
            <p class="pos-hub-stat__value">{{ number_format((float) ($today['total'] ?? 0), 2) }}</p>
        </div>
        <div class="pos-hub-stat">
            <p class="pos-hub-stat__label">Online POS today</p>
            <p class="pos-hub-stat__value">{{ (int) ($today['online_count'] ?? 0) }}</p>
        </div>
    </div>

    @if(!$hasProducts)
        <div class="pcat-banner pcat-banner--err" role="alert" style="margin-bottom:14px;">
            Add active <a href="{{ route('product.index') }}" class="pcat-link">products</a> and stock before opening the register.
        </div>
    @endif

    <div class="pos-hub-grid">
        <a href="{{ route('pos.online') }}" class="pos-hub-tile" @if(!$hasProducts) style="opacity:.65;pointer-events:none;" @endif>
            <span class="pos-hub-tile__icon"><i class="fa fa-store" aria-hidden="true"></i></span>
            <h3 class="pos-hub-tile__title">Online retail POS</h3>
            <p class="pos-hub-tile__desc">Full-screen terminal with categories, SKU scan, and quick checkout for retail & online sales.</p>
        </a>
        <a href="{{ route('pos.register') }}" class="pos-hub-tile" @if(!$hasProducts) style="opacity:.65;pointer-events:none;" @endif>
            <span class="pos-hub-tile__icon"><i class="fa fa-cash-register" aria-hidden="true"></i></span>
            <h3 class="pos-hub-tile__title">Retail register</h3>
            <p class="pos-hub-tile__desc">Compact in-store register layout for counter sales.</p>
        </a>
        <a href="{{ route('pos.sales.index') }}" class="pos-hub-tile">
            <span class="pos-hub-tile__icon"><i class="fa fa-receipt" aria-hidden="true"></i></span>
            <h3 class="pos-hub-tile__title">Sales history</h3>
            <p class="pos-hub-tile__desc">
                @if($hasSales)
                    View receipts, void sales, and track completed transactions.
                @else
                    Completed sales will appear here after your first checkout.
                @endif
            </p>
        </a>
    </div>
</div>
@endsection
