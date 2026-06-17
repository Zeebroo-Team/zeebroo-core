@extends('theme::layouts.app', ['title' => 'Edit Service — ' . $item->name, 'heading' => 'Service Catalog'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div>

    {{-- Page header --}}
    <div class="card" style="padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:14px;">
        <a href="{{ route('service.catalog.show', $item) }}"
           style="display:grid;place-items:center;width:34px;height:34px;border-radius:8px;
                  border:1px solid var(--border);background:transparent;color:var(--muted);text-decoration:none;
                  flex-shrink:0;transition:background .15s,color .15s;"
           title="Back to service">
            <i class="fa fa-arrow-left" style="font-size:12px;"></i>
        </a>
        <div style="min-width:0;">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:2px;">
                Editing service
            </div>
            <div style="font-size:16px;font-weight:800;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                {{ $item->name }}
            </div>
        </div>
        <span style="margin-left:auto;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;flex-shrink:0;
            {{ $item->is_active ? 'background:color-mix(in srgb,#10b981 12%,transparent);border:1px solid color-mix(in srgb,#10b981 35%,var(--border));color:#10b981;' : 'background:color-mix(in srgb,var(--muted) 10%,transparent);border:1px solid var(--border);color:var(--muted);' }}">
            {{ $item->is_active ? 'Active' : 'Inactive' }}
        </span>
    </div>

    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" style="margin-bottom:14px;" role="alert">
            <i class="fa fa-circle-exclamation" style="margin-right:6px;"></i>{{ $errors->first() }}
        </div>
    @endif

    @include('service::catalog.partials.item-form', [
        'item'              => $item,
        'currency'          => $currency,
        'serviceCategories' => $serviceCategories,
        'employees'         => $employees,
        'products'          => $products,
    ])

</div>
@endsection
