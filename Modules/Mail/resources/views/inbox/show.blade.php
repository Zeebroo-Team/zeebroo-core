@extends('theme::layouts.app', ['title' => $message->subject ?: 'Message', 'heading' => $business->name])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:720px;padding:14px;">
    <div style="margin-bottom:14px;">
        <a href="{{ route('mail.inbox.index', ['box' => $message->direction === 'outbound' ? 'sent' : 'inbox']) }}" class="pcat-link">
            <i class="fa fa-arrow-left"></i> {{ $message->direction === 'outbound' ? 'Sent' : 'Inbox' }}
        </a>
    </div>

    <h1 style="font-size:18px;font-weight:800;margin:0 0 10px;">{{ $message->subject ?: '(no subject)' }}</h1>

    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--border);font-size:13px;">
        <div>
            @if($message->direction === 'outbound')
                <div><strong style="color:var(--text);">To:</strong> <span class="muted">{{ $message->to_address }}</span></div>
            @else
                <div><strong style="color:var(--text);">From:</strong> <span class="muted">{{ $message->from_name ? $message->from_name . ' <' . $message->from_address . '>' : $message->from_address }}</span></div>
                @if($message->to_address)
                    <div><strong style="color:var(--text);">To:</strong> <span class="muted">{{ $message->to_address }}</span></div>
                @endif
            @endif
        </div>
        <div class="muted" style="font-size:12px;">{{ $message->occurred_at?->format('d M Y, H:i') }}</div>
    </div>

    <div style="font-size:14px;line-height:1.6;color:var(--text);">
        @if($message->body_html)
            {!! $message->body_html !!}
        @else
            <p style="white-space:pre-line;">{{ $message->body_text }}</p>
        @endif
    </div>

    @if($message->direction === 'inbound')
        <div style="margin-top:20px;padding-top:14px;border-top:1px solid var(--border);">
            <a href="{{ route('mail.inbox.compose', ['reply_to' => $message->id]) }}" class="linkbtn" style="padding:8px 18px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-reply"></i> Reply
            </a>
        </div>
    @endif
</div>
@endsection
