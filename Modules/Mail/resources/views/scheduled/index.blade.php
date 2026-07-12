@extends('theme::layouts.app', ['title' => 'Scheduled mail', 'heading' => $business->name])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:760px;padding:14px;">
    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="pcat-banner pcat-banner--err">{{ session('error') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Emails composed with a future send time. A pending message can still be cancelled before it goes out.
    </p>

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            {{ $scheduled->count() }} {{ $scheduled->count() === 1 ? 'message' : 'messages' }}.
        </span>
        <a href="{{ route('mail.inbox.compose') }}" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-pen"></i> Compose
        </a>
    </div>

    @if($scheduled->isEmpty())
        <p class="muted" style="margin:24px 0;font-size:13px;">Nothing scheduled — send later from Compose to see it here.</p>
    @endif

    <div class="pcat-table-wrap">
        <table class="pcat-table">
            <thead>
                <tr>
                    <th>To</th>
                    <th>Subject</th>
                    <th>Send at</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($scheduled as $s)
                    <tr>
                        <td>{{ $s->to_address }}</td>
                        <td>{{ $s->subject }}</td>
                        <td>{{ $s->scheduled_at->format('d M Y, H:i') }}</td>
                        <td>
                            @if($s->status === 'pending')
                                <span class="pcat-badge">Pending</span>
                            @elseif($s->status === 'sending')
                                <span class="pcat-badge">Sending…</span>
                            @elseif($s->status === 'sent')
                                <span class="pcat-badge pcat-badge--on">Sent</span>
                            @elseif($s->status === 'failed')
                                <span class="pcat-badge" style="border-color:color-mix(in srgb,#ef4444 45%,var(--border));color:#f97373;" title="{{ $s->error }}">Failed</span>
                            @else
                                <span class="pcat-badge">Cancelled</span>
                            @endif
                        </td>
                        <td style="text-align:right;">
                            @if($s->status === 'pending')
                                <form method="POST" action="{{ route('mail.scheduled.cancel', $s) }}" onsubmit="return confirm('Cancel this scheduled message?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="pcat-link" style="background:none;border:none;cursor:pointer;font-size:12.5px;padding:0;color:#f87171;">Cancel</button>
                                </form>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
