@extends('theme::layouts.app', ['title' => 'Service Catalog', 'heading' => 'Services'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('service::partials.service-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:12px;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
    @endif

    @if(!$hasItems)
        {{-- ── Inline create form when no services yet ── --}}
        <div style="max-width:560px;margin:0 auto;padding:24px 0;">
            <h3 style="margin:0 0 4px;font-size:16px;font-weight:800;">Add your first service</h3>
            <p class="muted" style="margin:0 0 20px;font-size:13px;">Define the services your business offers — customers can then raise requests against them.</p>
            @include('service::catalog.partials.item-form')
        </div>
    @else
        {{-- ── Search + New button ── --}}
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
            <form method="GET" action="{{ route('service.catalog.index') }}" style="display:flex;gap:6px;flex:1;min-width:180px;">
                <input type="text" name="q" value="{{ $search }}" placeholder="Search services…"
                       style="flex:1;padding:7px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:13px;">
                <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:13px;">Search</button>
                @if($search)<a href="{{ route('service.catalog.index') }}" class="linkbtn" style="padding:7px 12px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Clear</a>@endif
            </form>
            <button type="button" class="linkbtn" style="padding:7px 14px;font-size:13px;" onclick="document.getElementById('svc-new-modal').classList.add('pcat-modal--open');document.getElementById('svc-new-modal').setAttribute('aria-hidden','false');document.documentElement.classList.add('pcat-modal-open-html');document.getElementById('svc-item-name')?.focus();">
                <i class="fa fa-plus"></i> New service
            </button>
        </div>

        {{-- ── Status filter ── --}}
        <div style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap;">
            @foreach(['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $key => $label)
                <a href="{{ route('service.catalog.index', array_merge(request()->query(), ['status' => $key])) }}"
                   style="padding:5px 12px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid var(--border);text-decoration:none;
                          {{ $status === $key ? 'background:var(--text);color:var(--bg);border-color:var(--text);' : 'background:transparent;color:var(--muted);' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- ── Table ── --}}
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Service name</th>
                        <th>Category</th>
                        <th style="width:110px;">Price{{ $currency ? ' ('.$currency.')' : '' }}</th>
                        <th style="width:90px;">Duration</th>
                        <th style="width:80px;">Status</th>
                        <th style="width:90px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>
                                <a href="{{ route('service.catalog.show', $item) }}" style="font-weight:700;color:var(--text);text-decoration:none;">{{ $item->name }}</a>
                                @if($item->description)
                                    <div class="muted" style="font-size:11px;margin-top:2px;">{{ Str::limit($item->description, 60) }}</div>
                                @endif
                            </td>
                            <td class="muted">{{ $item->category ?: '—' }}</td>
                            <td>{{ $item->price !== null ? number_format($item->price, 2) : '—' }}</td>
                            <td class="muted">{{ $item->durationLabel() }}</td>
                            <td>
                                <span style="display:inline-block;font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;
                                    {{ $item->is_active ? 'background:color-mix(in srgb,#10b981 12%,transparent);border:1px solid color-mix(in srgb,#10b981 40%,var(--border));color:#10b981;' : 'background:color-mix(in srgb,var(--muted) 12%,transparent);border:1px solid var(--border);color:var(--muted);' }}">
                                    {{ $item->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <a href="{{ route('service.catalog.edit', $item) }}" class="linkbtn" style="padding:5px 10px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Edit</a>
                                <form action="{{ route('service.catalog.destroy', $item) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this service?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="pcat-btn-del" style="padding:5px 8px;"><i class="fa fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--muted);">No services found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ── New service modal ── --}}
        <div id="svc-new-modal" class="pcat-modal" role="dialog" aria-modal="true" aria-labelledby="svc-new-modal-title" aria-hidden="true">
            <div class="pcat-modal__backdrop" onclick="document.getElementById('svc-new-modal').classList.remove('pcat-modal--open');document.getElementById('svc-new-modal').setAttribute('aria-hidden','true');document.documentElement.classList.remove('pcat-modal-open-html');" tabindex="-1"></div>
            <div class="pcat-modal__panel">
                <div class="pcat-modal__head">
                    <h2 id="svc-new-modal-title">New service</h2>
                    <button type="button" class="pcat-modal__close" aria-label="Close"
                            onclick="document.getElementById('svc-new-modal').classList.remove('pcat-modal--open');document.getElementById('svc-new-modal').setAttribute('aria-hidden','true');document.documentElement.classList.remove('pcat-modal-open-html');">&times;</button>
                </div>
                <div class="pcat-modal__body">
                    @include('service::catalog.partials.item-form')
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
