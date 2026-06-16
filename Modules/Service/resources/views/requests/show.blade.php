@extends('theme::layouts.app', ['title' => $req->request_number, 'heading' => 'Service Request'])

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

    <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:20px;">
        <div>
            <p class="muted" style="margin:0 0 4px;font-size:12px;font-family:monospace;">{{ $req->request_number }}</p>
            <h2 style="margin:0;font-size:19px;font-weight:800;color:var(--text);">{{ $req->title }}</h2>
            @if($req->reference)
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Ref: {{ $req->reference }}</p>
            @endif
            <div style="margin-top:8px;">
                <span class="sreq-status sreq-status--{{ $req->status }}">{{ $req->statusLabel() }}</span>
            </div>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-start;">
            @if($req->isEditable())
                <a href="{{ route('service.requests.edit', $req) }}" class="linkbtn"
                   style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                    <i class="fa fa-pen"></i> Edit
                </a>
            @endif

            @if($req->status === \Modules\Service\Models\ServiceRequest::STATUS_PENDING)
                <form method="POST" action="{{ route('service.requests.in-progress', $req) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="linkbtn"
                            style="padding:8px 14px;font-size:13px;background:color-mix(in srgb,#3b82f6 12%,transparent);border:1px solid color-mix(in srgb,#3b82f6 45%,var(--border));color:var(--text);">
                        <i class="fa fa-play"></i> Start work
                    </button>
                </form>
            @endif

            @if(in_array($req->status, [\Modules\Service\Models\ServiceRequest::STATUS_PENDING, \Modules\Service\Models\ServiceRequest::STATUS_IN_PROGRESS]))
                <form method="POST" action="{{ route('service.requests.complete', $req) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="linkbtn"
                            style="padding:8px 14px;font-size:13px;background:color-mix(in srgb,#10b981 12%,transparent);border:1px solid color-mix(in srgb,#10b981 45%,var(--border));color:var(--text);">
                        <i class="fa fa-circle-check"></i> Mark complete
                    </button>
                </form>
            @endif

            @if($req->status !== \Modules\Service\Models\ServiceRequest::STATUS_COMPLETED && $req->status !== \Modules\Service\Models\ServiceRequest::STATUS_CANCELLED)
                <form method="POST" action="{{ route('service.requests.cancel', $req) }}" style="display:inline;">
                    @csrf
                    <button type="submit" onclick="return confirm('Cancel this request?')" class="linkbtn"
                            style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid color-mix(in srgb,#94a3b8 45%,var(--border));color:var(--muted);">
                        Cancel
                    </button>
                </form>
            @endif

            <form method="POST" action="{{ route('service.requests.destroy', $req) }}" style="display:inline;">
                @csrf @method('DELETE')
                <button type="submit" onclick="return confirm('Delete this request? This cannot be undone.')"
                        class="pcat-btn-del" style="padding:8px 10px;">
                    <i class="fa fa-trash"></i>
                </button>
            </form>
        </div>
    </div>

    {{-- Details grid --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px;">
        @if($req->serviceItem)
        <div style="border:1px solid var(--border);border-radius:10px;padding:12px;">
            <p class="muted" style="margin:0 0 3px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Service</p>
            <p style="margin:0;font-size:13px;font-weight:700;color:var(--text);">{{ $req->serviceItem->name }}</p>
        </div>
        @endif

        @if($req->customer)
        <div style="border:1px solid var(--border);border-radius:10px;padding:12px;">
            <p class="muted" style="margin:0 0 3px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Customer</p>
            <p style="margin:0;font-size:13px;font-weight:700;color:var(--text);">{{ $req->customer->name }}</p>
            @if($req->customer->phone)<p class="muted" style="margin:2px 0 0;font-size:12px;">{{ $req->customer->phone }}</p>@endif
        </div>
        @endif

        @if($req->scheduled_at)
        <div style="border:1px solid var(--border);border-radius:10px;padding:12px;">
            <p class="muted" style="margin:0 0 3px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Scheduled</p>
            <p style="margin:0;font-size:13px;font-weight:700;color:var(--text);">{{ $req->scheduled_at->format('M j, Y') }}</p>
            <p class="muted" style="margin:2px 0 0;font-size:12px;">{{ $req->scheduled_at->format('H:i') }}</p>
        </div>
        @endif

        @if($req->total_price !== null)
        <div style="border:1px solid var(--border);border-radius:10px;padding:12px;">
            <p class="muted" style="margin:0 0 3px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Price{{ $currency ? ' ('.$currency.')' : '' }}</p>
            <p style="margin:0;font-size:18px;font-weight:800;color:var(--text);">{{ number_format($req->total_price, 2) }}</p>
        </div>
        @endif
    </div>

    @if($req->notes)
    <div style="border-left:3px solid var(--border);padding:10px 14px;border-radius:0 8px 8px 0;background:color-mix(in srgb,var(--card) 96%,transparent);">
        <p class="muted" style="margin:0 0 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Notes</p>
        <p style="margin:0;font-size:13px;color:var(--text);white-space:pre-line;">{{ $req->notes }}</p>
    </div>
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('service.requests.index') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> All requests
    </a>
</div>
@endsection
