@extends('theme::layouts.app', ['title' => $item->name, 'heading' => 'Service'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:760px;margin:0 auto;padding:14px;">
    @include('service::partials.service-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:12px;">{{ session('status') }}</div>
    @endif

    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
        <div>
            <h2 style="margin:0 0 4px;font-size:19px;font-weight:800;">{{ $item->name }}</h2>
            @if($item->category)
                <span class="muted" style="font-size:12px;">{{ $item->category }}</span>
            @endif
            <div style="margin-top:8px;">
                <span style="display:inline-block;font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;
                    {{ $item->is_active ? 'background:color-mix(in srgb,#10b981 12%,transparent);border:1px solid color-mix(in srgb,#10b981 40%,var(--border));color:#10b981;' : 'background:color-mix(in srgb,var(--muted) 12%,transparent);border:1px solid var(--border);color:var(--muted);' }}">
                    {{ $item->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('service.catalog.edit', $item) }}" class="linkbtn"
               style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                <i class="fa fa-pen"></i> Edit
            </a>
            <form method="POST" action="{{ route('service.catalog.destroy', $item) }}" style="display:inline;" onsubmit="return confirm('Delete this service?')">
                @csrf @method('DELETE')
                <button type="submit" class="pcat-btn-del" style="padding:8px 10px;"><i class="fa fa-trash"></i></button>
            </form>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
        <div style="border:1px solid var(--border);border-radius:10px;padding:14px;">
            <p class="muted" style="margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Price</p>
            <p style="margin:0;font-size:18px;font-weight:800;color:var(--text);">
                {{ $item->price !== null ? ($currency ? $currency . ' ' : '') . number_format($item->price, 2) : '—' }}
            </p>
        </div>
        <div style="border:1px solid var(--border);border-radius:10px;padding:14px;">
            <p class="muted" style="margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Duration</p>
            <p style="margin:0;font-size:18px;font-weight:800;color:var(--text);">{{ $item->durationLabel() }}</p>
        </div>
    </div>

    @if($item->description)
    <div style="border-left:3px solid var(--border);padding:10px 14px;border-radius:0 8px 8px 0;background:color-mix(in srgb,var(--card) 96%,transparent);margin-bottom:16px;">
        <p class="muted" style="margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Description</p>
        <p style="margin:0;font-size:13px;color:var(--text);white-space:pre-line;">{{ $item->description }}</p>
    </div>
    @endif
</div>

<div style="margin-top:14px;max-width:760px;margin-left:auto;margin-right:auto;">
    <a href="{{ route('service.catalog.index') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> All services
    </a>
</div>
@endsection
