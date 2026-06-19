@extends('theme::layouts.app', ['title' => 'Bulk Services', 'heading' => 'Service Catalog'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="padding:14px;">
    @include('service::partials.service-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:12px;">{{ session('status') }}</div>
    @endif

    @if($bundles->isEmpty() && !$search)

        {{-- ── EMPTY STATE with inline create form ── --}}
        @if(!$hasItems)
        <div style="text-align:center;padding:48px 24px;">
            <div style="font-size:36px;color:var(--muted);margin-bottom:12px;"><i class="fa fa-layer-group"></i></div>
            <h3 style="margin:0 0 6px;font-size:17px;font-weight:700;">No services yet</h3>
            <p style="margin:0 0 18px;color:var(--muted);font-size:13px;">Add individual services first, then group them into bulk bundles.</p>
            <a href="{{ route('service.catalog.index') }}" class="linkbtn" style="padding:9px 18px;font-size:13px;">
                <i class="fa fa-plus" style="margin-right:6px;"></i>Add Services
            </a>
        </div>
        @else
        <div style="margin-bottom:20px;">
            <h3 style="margin:0 0 4px;font-size:15px;font-weight:700;">Create your first bulk bundle</h3>
            <p style="margin:0 0 16px;color:var(--muted);font-size:13px;">Group multiple services into a single package with a combined price.</p>
            @include('service::bundles.partials.bundle-form')
        </div>
        @endif

    @else

        {{-- ── LIST + optional create form ── --}}
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
            <form method="GET" style="display:flex;gap:8px;flex:1;max-width:360px;">
                <input type="text" name="q" value="{{ $search }}" placeholder="Search bundles…"
                       style="flex:1;padding:7px 12px;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--text);font-size:13px;outline:none;">
                @if($search)
                    <a href="{{ route('service.bundles.index') }}" class="linkbtn"
                       style="padding:7px 10px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Clear</a>
                @endif
            </form>
            <button type="button" class="linkbtn" id="bundleCreateToggle" style="padding:7px 14px;font-size:13px;">
                <i class="fa fa-plus"></i> New Bundle
            </button>
        </div>

        {{-- Inline create form (hidden) --}}
        <div id="bundleCreateForm" style="display:none;border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:18px;background:color-mix(in srgb,var(--card) 70%,transparent);">
            <h4 style="margin:0 0 14px;font-size:14px;font-weight:700;"><i class="fa fa-layer-group" style="margin-right:7px;opacity:.7;"></i>New Bulk Bundle</h4>
            @include('service::bundles.partials.bundle-form')
        </div>

        @if($bundles->isEmpty())
            <p style="color:var(--muted);font-size:13px;padding:24px 0;text-align:center;">No bundles match your search.</p>
        @else
        <div style="display:grid;gap:12px;">
            @foreach($bundles as $bundle)
            @php
                $svcCount = $bundle->services->count();
                $indivTotal = null;
                $calcTotal  = 0.0;
                $allPriced  = true;
                foreach ($bundle->services as $s) {
                    if ($s->price === null) { $allPriced = false; }
                    else $calcTotal += (float) $s->price * (int) ($s->pivot->qty ?? 1);
                }
                if ($allPriced && $svcCount > 0) $indivTotal = $calcTotal;
            @endphp
            <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden;">
                <div style="padding:12px 16px;display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                    <div style="min-width:0;">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <a href="{{ route('service.bundles.show', $bundle) }}"
                               style="font-size:15px;font-weight:800;color:var(--text);text-decoration:none;">{{ $bundle->name }}</a>
                            <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;
                                {{ $bundle->is_active ? 'background:color-mix(in srgb,#10b981 10%,transparent);border:1px solid color-mix(in srgb,#10b981 30%,var(--border));color:#10b981;' : 'background:color-mix(in srgb,var(--muted) 10%,transparent);border:1px solid var(--border);color:var(--muted);' }}">
                                {{ $bundle->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        @if($bundle->description)
                            <p style="margin:3px 0 0;font-size:12px;color:var(--muted);">{{ Str::limit($bundle->description, 100) }}</p>
                        @endif
                        <p style="margin:5px 0 0;font-size:12px;color:var(--muted);"><i class="fa fa-wrench" style="margin-right:4px;opacity:.6;"></i>{{ $svcCount }} service{{ $svcCount !== 1 ? 's' : '' }}</p>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        @if($bundle->price !== null)
                            <div style="font-size:17px;font-weight:800;color:var(--text);">{{ ($currency ? $currency . ' ' : '') . number_format((float) $bundle->price, 2) }}</div>
                            @if($indivTotal !== null && $indivTotal > (float) $bundle->price)
                                <div style="font-size:11px;color:#10b981;font-weight:600;">Save {{ ($currency ? $currency . ' ' : '') . number_format($indivTotal - (float) $bundle->price, 2) }}</div>
                            @endif
                        @elseif($indivTotal !== null)
                            <div style="font-size:17px;font-weight:800;color:var(--text);">{{ ($currency ? $currency . ' ' : '') . number_format($indivTotal, 2) }}</div>
                        @else
                            <div style="font-size:17px;font-weight:800;color:var(--muted);">—</div>
                        @endif
                        <div style="display:flex;gap:6px;justify-content:flex-end;margin-top:8px;">
                            <a href="{{ route('service.bundles.edit', $bundle) }}" class="linkbtn"
                               style="padding:5px 11px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                                <i class="fa fa-pen"></i>
                            </a>
                            <form method="POST" action="{{ route('service.bundles.destroy', $bundle) }}"
                                  onsubmit="return confirm('Delete this bundle?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="pcat-btn-del" style="padding:5px 9px;font-size:12px;"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                @if($bundle->services->isNotEmpty())
                <div style="padding:0 16px 12px;display:flex;flex-wrap:wrap;gap:5px;">
                    @foreach($bundle->services as $svc)
                        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:600;border:1px solid var(--border);color:var(--text);background:color-mix(in srgb,var(--card) 70%,transparent);">
                            <i class="fa fa-wrench" style="font-size:9px;opacity:.5;"></i>
                            {{ $svc->name }}
                            @if($svc->pivot->qty > 1) <span style="color:var(--muted);">×{{ $svc->pivot->qty }}</span> @endif
                        </span>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    @endif
</div>

<script>
(function () {
    const btn  = document.getElementById('bundleCreateToggle');
    const form = document.getElementById('bundleCreateForm');
    if (!btn || !form) return;
    btn.addEventListener('click', () => {
        const open = form.style.display !== 'none';
        form.style.display = open ? 'none' : '';
        btn.innerHTML = open ? '<i class="fa fa-plus"></i> New Bundle' : '<i class="fa fa-times"></i> Cancel';
        if (!open) form.querySelector('input[name=name]')?.focus();
    });
    @if($errors->any()) form.style.display = ''; btn.innerHTML = '<i class="fa fa-times"></i> Cancel'; @endif
})();
</script>
@endsection
