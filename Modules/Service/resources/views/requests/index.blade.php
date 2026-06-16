@extends('theme::layouts.app', ['title' => 'Service Requests', 'heading' => 'Services'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.sreq-status{display:inline-block;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;border:1px solid var(--border);}
.sreq-status--pending     {border-color:color-mix(in srgb,#f59e0b 45%,var(--border));background:color-mix(in srgb,#f59e0b 12%,transparent);color:#b45309;}
.sreq-status--in_progress {border-color:color-mix(in srgb,#3b82f6 45%,var(--border));background:color-mix(in srgb,#3b82f6 12%,transparent);color:#1d4ed8;}
.sreq-status--completed   {border-color:color-mix(in srgb,#10b981 45%,var(--border));background:color-mix(in srgb,#10b981 12%,transparent);color:#047857;}
.sreq-status--cancelled   {border-color:color-mix(in srgb,#94a3b8 45%,var(--border));background:color-mix(in srgb,#94a3b8 12%,transparent);opacity:.8;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('service::partials.service-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;margin-bottom:12px;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
    @endif

    @if(!$hasRequests)
        {{-- ── Inline create when no requests ── --}}
        <div style="max-width:600px;margin:0 auto;padding:24px 0;">
            <h3 style="margin:0 0 4px;font-size:16px;font-weight:800;">Log your first service request</h3>
            <p class="muted" style="margin:0 0 20px;font-size:13px;">Track customer service jobs, repairs, bookings, and work orders.</p>
            @include('service::requests.partials.request-form')
        </div>
    @else
        {{-- ── Toolbar ── --}}
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
            <form method="GET" action="{{ route('service.requests.index') }}" style="display:flex;gap:6px;flex:1;min-width:180px;">
                <input type="hidden" name="status" value="{{ $statusFilter }}">
                <input type="text" name="q" value="{{ $search }}" placeholder="Search requests…"
                       style="flex:1;padding:7px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:13px;">
                <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:13px;">Search</button>
                @if($search)<a href="{{ route('service.requests.index') }}" class="linkbtn" style="padding:7px 12px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Clear</a>@endif
            </form>
            <button type="button" class="linkbtn" style="padding:7px 14px;font-size:13px;" id="sreq-open-modal-btn">
                <i class="fa fa-plus"></i> New request
            </button>
        </div>

        {{-- ── Status tabs ── --}}
        <div style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap;">
            @foreach($statusTabs as $key => $label)
                <a href="{{ route('service.requests.index', array_merge(request()->query(), ['status' => $key])) }}"
                   style="padding:5px 12px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid var(--border);text-decoration:none;
                          {{ $statusFilter === $key ? 'background:var(--text);color:var(--bg);border-color:var(--text);' : 'background:transparent;color:var(--muted);' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- ── Table ── --}}
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th style="width:110px;">#</th>
                        <th>Title</th>
                        <th>Service</th>
                        <th>Customer</th>
                        <th style="width:130px;">Scheduled</th>
                        <th style="width:100px;">Status</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        <tr>
                            <td class="muted" style="font-size:12px;font-family:monospace;">
                                <a href="{{ route('service.requests.show', $req) }}" style="color:var(--text);text-decoration:none;font-weight:700;">{{ $req->request_number }}</a>
                            </td>
                            <td>
                                <a href="{{ route('service.requests.show', $req) }}" style="font-weight:700;color:var(--text);text-decoration:none;">{{ $req->title }}</a>
                            </td>
                            <td class="muted">{{ $req->serviceItem?->name ?? '—' }}</td>
                            <td class="muted">{{ $req->customer?->name ?? '—' }}</td>
                            <td class="muted" style="font-size:12px;">
                                {{ $req->scheduled_at ? $req->scheduled_at->format('M j, Y H:i') : '—' }}
                            </td>
                            <td>
                                <span class="sreq-status sreq-status--{{ $req->status }}">{{ $req->statusLabel() }}</span>
                            </td>
                            <td style="text-align:right;">
                                <a href="{{ route('service.requests.show', $req) }}" class="linkbtn"
                                   style="padding:5px 10px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--muted);">No requests found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ── New request modal ── --}}
        <div id="sreq-modal" class="pcat-modal" role="dialog" aria-modal="true" aria-labelledby="sreq-modal-title" aria-hidden="true">
            <div class="pcat-modal__backdrop" data-sreq-modal-close tabindex="-1"></div>
            <div class="pcat-modal__panel" style="max-width:600px;">
                <div class="pcat-modal__head">
                    <h2 id="sreq-modal-title">New service request</h2>
                    <button type="button" class="pcat-modal__close" data-sreq-modal-close aria-label="Close">&times;</button>
                </div>
                <div class="pcat-modal__body">
                    @include('service::requests.partials.request-form')
                </div>
            </div>
        </div>

        @once
        <script>
        (function () {
            var modal = document.getElementById('sreq-modal');
            function openModal() {
                if (!modal) return;
                modal.classList.add('pcat-modal--open');
                modal.setAttribute('aria-hidden', 'false');
                document.documentElement.classList.add('pcat-modal-open-html');
            }
            function closeModal() {
                if (!modal) return;
                modal.classList.remove('pcat-modal--open');
                modal.setAttribute('aria-hidden', 'true');
                document.documentElement.classList.remove('pcat-modal-open-html');
            }
            document.getElementById('sreq-open-modal-btn')?.addEventListener('click', openModal);
            modal?.querySelectorAll('[data-sreq-modal-close]').forEach(function (el) {
                el.addEventListener('click', closeModal);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal?.classList.contains('pcat-modal--open')) {
                    e.preventDefault(); closeModal();
                }
            }, true);
        })();
        </script>
        @endonce
    @endif
</div>
@endsection
