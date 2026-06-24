@extends('theme::layouts.app', [
    'title' => __('Bills for modification'),
    'heading' => __('Modification'),
])

@section('content')
<div class="mod-page">
<style>
.mod-page{max-width:none;width:100%;margin:0;box-sizing:border-box;--mod-r:12px;--mod-r-sm:9px;}
.mod-hero{display:flex;flex-wrap:wrap;gap:12px 20px;justify-content:space-between;align-items:flex-start;padding:0 2px 16px;margin-bottom:16px;border-bottom:1px solid var(--border);}
.mod-hero__eyebrow{margin:0 0 4px;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--primary);display:flex;align-items:center;gap:6px;}
.mod-hero__title{margin:0;font-size:20px;line-height:1.25;font-weight:800;letter-spacing:-.03em;}
.mod-hero__meta{margin:6px 0 0;font-size:12px;color:var(--muted);}
.mod-hero__actions{display:flex;flex-wrap:wrap;gap:9px;align-items:center;padding-top:4px;}
.mod-btn--ghost{display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);text-decoration:none;cursor:pointer;transition:background .18s ease,border-color .18s ease;}
.mod-btn--ghost:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 6%,transparent);}
</style>

<div class="mod-hero">
    <div>
        <p class="mod-hero__eyebrow">
            <i class="fa fa-receipt" style="font-size:9px;"></i>
            {{ __('Bills assigned to') }}
        </p>
        <h1 class="mod-hero__title">{{ $modification->name }}</h1>
        <p class="mod-hero__meta">
            <a href="{{ route('modification.show', $modification) }}"
               style="color:var(--primary);font-weight:600;text-decoration:none;">
                {{ __('View modification details') }} <i class="fa fa-arrow-up-right-from-square" style="font-size:10px;"></i>
            </a>
        </p>
    </div>
    <div class="mod-hero__actions">
        <a href="{{ route('modification.show', $modification) }}" class="mod-btn--ghost">
            <i class="fa fa-arrow-left" style="font-size:11px;"></i>{{ __('Back to modification') }}
        </a>
        <a href="{{ route('account.bills.index') }}" class="mod-btn--ghost">
            <i class="fa fa-receipt" style="font-size:11px;"></i>{{ __('All bills') }}
        </a>
    </div>
</div>

@include('modification::partials.assigned-bills-table', ['bills' => $bills, 'business' => $business])

</div>
@endsection
