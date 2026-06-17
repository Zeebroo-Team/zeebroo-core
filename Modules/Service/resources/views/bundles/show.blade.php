@extends('theme::layouts.app', ['title' => $bundle->name, 'heading' => 'Bulk Bundle'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:760px;margin:0 auto;padding:14px;">
    @include('service::partials.service-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:12px;">{{ session('status') }}</div>
    @endif

    {{-- Header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
        <div>
            <h2 style="margin:0 0 4px;font-size:19px;font-weight:800;">{{ $bundle->name }}</h2>
            <span style="display:inline-block;font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;
                {{ $bundle->is_active ? 'background:color-mix(in srgb,#10b981 12%,transparent);border:1px solid color-mix(in srgb,#10b981 40%,var(--border));color:#10b981;' : 'background:color-mix(in srgb,var(--muted) 12%,transparent);border:1px solid var(--border);color:var(--muted);' }}">
                {{ $bundle->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('service.bundles.edit', $bundle) }}" class="linkbtn"
               style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                <i class="fa fa-pen"></i> Edit
            </a>
            <form method="POST" action="{{ route('service.bundles.destroy', $bundle) }}"
                  onsubmit="return confirm('Delete this bundle?')">
                @csrf @method('DELETE')
                <button type="submit" class="pcat-btn-del" style="padding:8px 10px;"><i class="fa fa-trash"></i></button>
            </form>
        </div>
    </div>

    {{-- Price card --}}
    @php
        $indivTotal = $bundle->totalIndividualPrice();
        $hasPrice   = $bundle->price !== null;
        $fmt = fn($n) => ($currency ? $currency . ' ' : '') . number_format((float) $n, 2);
    @endphp
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
        <div style="border:1px solid var(--border);border-radius:10px;padding:14px;">
            <p class="muted" style="margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Bundle Price</p>
            @if($hasPrice)
                <p style="margin:0;font-size:20px;font-weight:800;color:var(--text);">{{ $fmt($bundle->price) }}</p>
                @if($indivTotal !== null && $indivTotal > (float) $bundle->price)
                    <p style="margin:3px 0 0;font-size:11px;color:#10b981;font-weight:600;">
                        <i class="fa fa-circle-check" style="margin-right:3px;"></i>Saves {{ $fmt($indivTotal - (float) $bundle->price) }} vs individual
                    </p>
                @endif
            @elseif($indivTotal !== null)
                <p style="margin:0;font-size:20px;font-weight:800;color:var(--text);">{{ $fmt($indivTotal) }}</p>
                <p style="margin:3px 0 0;font-size:11px;color:var(--muted);">Sum of individual prices</p>
            @else
                <p style="margin:0;font-size:20px;font-weight:800;color:var(--muted);">—</p>
            @endif
        </div>
        <div style="border:1px solid var(--border);border-radius:10px;padding:14px;">
            <p class="muted" style="margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Services</p>
            <p style="margin:0;font-size:20px;font-weight:800;color:var(--text);">{{ $bundle->services->count() }}</p>
        </div>
    </div>

    {{-- Description --}}
    @if($bundle->description)
    <div style="border-left:3px solid var(--border);padding:10px 14px;border-radius:0 8px 8px 0;background:color-mix(in srgb,var(--card) 96%,transparent);margin-bottom:16px;">
        <p class="muted" style="margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Description</p>
        <p style="margin:0;font-size:13px;color:var(--text);white-space:pre-line;">{{ $bundle->description }}</p>
    </div>
    @endif

    {{-- Included services --}}
    <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden;">
        <div style="padding:10px 14px;border-bottom:1px solid var(--border);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">
            <i class="fa fa-layer-group" style="margin-right:5px;"></i>Included Services
        </div>
        @forelse($bundle->services as $svc)
        @php $linePrice = $svc->price !== null ? (float) $svc->price * (int) ($svc->pivot->qty ?? 1) : null; @endphp
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--border);gap:10px;">
            <div style="min-width:0;">
                <div style="font-size:13px;font-weight:700;color:var(--text);">
                    <i class="fa fa-wrench" style="font-size:11px;opacity:.5;margin-right:5px;"></i>
                    <a href="{{ route('service.catalog.show', $svc) }}" style="color:inherit;text-decoration:none;">{{ $svc->name }}</a>
                </div>
                @if($svc->price !== null)
                    <div style="font-size:11px;color:var(--muted);margin-top:2px;">{{ $fmt($svc->price) }} each</div>
                @endif
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <div style="font-size:12px;font-weight:700;color:var(--muted);">Qty {{ $svc->pivot->qty ?? 1 }}</div>
                @if($linePrice !== null)
                    <div style="font-size:13px;font-weight:800;color:var(--text);">{{ $fmt($linePrice) }}</div>
                @endif
            </div>
        </div>
        @empty
        <p style="text-align:center;padding:24px;color:var(--muted);font-size:13px;font-style:italic;">No services in this bundle.</p>
        @endforelse
    </div>
</div>

<div style="margin-top:14px;max-width:760px;margin-left:auto;margin-right:auto;">
    <a href="{{ route('service.bundles.index') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> All bundles
    </a>
</div>
@endsection
