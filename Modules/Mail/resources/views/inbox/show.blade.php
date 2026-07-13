@extends('theme::layouts.app', ['title' => $message->subject ?: 'Message', 'heading' => $business->name])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.ms-layout{display:flex;align-items:flex-start;gap:18px;}
.ms-main{flex:0 1 720px;min-width:0;}
.ms-side{width:260px;flex-shrink:0;position:sticky;top:90px;}
.ms-side__section{border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:14px;}
.ms-side__title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:10px;}
.ms-cust-name{font-size:14px;font-weight:700;color:var(--text);}
.ms-cust-meta{font-size:12.5px;color:var(--muted);margin-top:2px;}

.chat-thread{display:flex;flex-direction:column;gap:14px;margin:6px 0 18px;}
.chat-msg{display:flex;}
.chat-msg--in{justify-content:flex-start;}
.chat-msg--out{justify-content:flex-end;}
.chat-bubble{max-width:85%;border-radius:18px;padding:10px 14px;box-shadow:0 1px 3px rgba(0,0,0,.08);}
.chat-msg--in .chat-bubble{background:color-mix(in srgb,var(--card) 88%,var(--border) 12%);border:1px solid var(--border);border-bottom-left-radius:4px;}
.chat-msg--out .chat-bubble{background:color-mix(in srgb,var(--primary) 16%,var(--card) 84%);border:1px solid color-mix(in srgb,var(--primary) 35%,transparent);border-bottom-right-radius:4px;}
.chat-msg--current .chat-bubble{box-shadow:0 0 0 2px var(--primary);}
.chat-bubble__meta{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:4px;}
.chat-bubble__from{font-size:11.5px;font-weight:700;color:var(--text);}
.chat-bubble__date{font-size:11px;color:var(--muted);flex-shrink:0;}
.chat-bubble__subject{font-size:11.5px;color:var(--muted);font-style:italic;margin-bottom:5px;}
.chat-bubble__body{font-size:13.5px;line-height:1.55;color:var(--text);overflow-x:auto;}
.chat-bubble__body img{max-width:100%;height:auto;}
.chat-bubble__body p{margin:0 0 8px;}
.chat-bubble__body p:last-child{margin-bottom:0;}

.chat-reply-bar{position:sticky;bottom:0;margin:14px -14px -14px;padding:14px;background:var(--card);border-top:1px solid var(--border);box-shadow:0 -2px 10px rgba(0,0,0,.06);border-radius:0 0 16px 16px;}
.chat-reply{display:flex;gap:8px;align-items:flex-end;}
.chat-reply textarea{flex:1;padding:10px 12px;border-radius:10px;border:1px solid var(--border);font-size:13px;background:var(--card);color:var(--text);resize:none;font-family:inherit;}
.chat-reply__send{padding:9px 18px;font-size:13px;flex-shrink:0;}
.chat-fullcompose{display:block;margin-top:8px;font-size:12px;}

@media (max-width: 860px){
    .ms-layout{flex-direction:column;}
    .ms-side{width:100%;position:static;}
    .chat-bubble{max-width:92%;}
}
</style>

<div class="ms-layout">
<div class="pcat-page-card card ms-main" style="padding:14px;max-width:720px;">
    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="pcat-banner pcat-banner--err">{{ session('error') }}</div>
    @endif

    <div style="margin-bottom:14px;">
        <a href="{{ route('mail.inbox.index', ['box' => $message->direction === 'outbound' ? 'sent' : 'inbox']) }}" class="pcat-link">
            <i class="fa fa-arrow-left"></i> {{ $message->direction === 'outbound' ? 'Sent' : 'Inbox' }}
        </a>
    </div>

    <h1 style="font-size:18px;font-weight:800;margin:0 0 14px;">
        {{ $customer->name ?? ($message->from_name ?: $counterpartEmail) ?: 'Conversation' }}
    </h1>

    <div class="chat-thread">
        @foreach($timeline as $tm)
            @php $isCurrent = (int) $tm->id === (int) $message->id; @endphp
            <div class="chat-msg {{ $tm->direction === 'outbound' ? 'chat-msg--out' : 'chat-msg--in' }} {{ $isCurrent ? 'chat-msg--current' : '' }}">
                <div class="chat-bubble">
                    <div class="chat-bubble__meta">
                        <span class="chat-bubble__from">{{ $tm->direction === 'outbound' ? 'You' : ($tm->from_name ?: $tm->from_address) }}</span>
                        <span class="chat-bubble__date">{{ $tm->occurred_at?->format('d M, H:i') }}</span>
                    </div>
                    @if($tm->subject)
                        <div class="chat-bubble__subject">{{ $tm->subject }}</div>
                    @endif
                    <div class="chat-bubble__body">
                        @if($tm->body_html)
                            {!! $tm->body_html !!}
                        @else
                            <p style="white-space:pre-line;">{{ $tm->body_text }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if($counterpartEmail)
        @php
            $lastSubject = $timeline->last()?->subject ?: '(no subject)';
            $replySubject = \Illuminate\Support\Str::startsWith($lastSubject, 'Re:') ? $lastSubject : 'Re: ' . $lastSubject;
        @endphp
        <div class="chat-reply-bar">
            <form method="POST" action="{{ route('mail.inbox.send') }}" class="chat-reply">
                @csrf
                <input type="hidden" name="to" value="{{ $counterpartEmail }}">
                <input type="hidden" name="subject" value="{{ $replySubject }}">
                <input type="hidden" name="reply_to_message" value="{{ $message->id }}">
                <textarea name="body" rows="2" required placeholder="Type a reply…"></textarea>
                <button type="submit" class="linkbtn chat-reply__send">
                    <i class="fa fa-paper-plane"></i> Send
                </button>
            </form>
            <a href="{{ route('mail.inbox.compose', ['reply_to' => $message->id]) }}" class="pcat-link chat-fullcompose">
                Full compose (templates, schedule)…
            </a>
        </div>
    @endif
</div>

@if($counterpartEmail)
<div class="ms-side">
    @if($customer)
        <div class="ms-side__section">
            <div class="ms-side__title">Customer</div>
            <div class="ms-cust-name">{{ $customer->name }}</div>
            <div class="ms-cust-meta">{{ $customer->email }}</div>
            @if($customer->phone)
                <div class="ms-cust-meta">{{ $customer->phone }}</div>
            @endif
            <a href="{{ route('crm.contacts.show', $customer) }}" class="linkbtn" style="margin-top:12px;padding:7px 14px;font-size:12.5px;width:100%;justify-content:center;">
                <i class="fa fa-address-book"></i> View customer
            </a>
        </div>
    @else
        <div class="ms-side__section">
            <div class="ms-side__title">Customer</div>
            <p class="muted" style="font-size:12.5px;margin:0 0 12px;">
                {{ $counterpartEmail }} isn't a customer yet.
            </p>
            <form method="POST" action="{{ route('mail.inbox.convertToCustomer', $message) }}">
                @csrf
                <button type="submit" class="linkbtn" style="padding:8px 14px;font-size:12.5px;width:100%;justify-content:center;">
                    <i class="fa fa-user-plus"></i> Convert to customer
                </button>
            </form>
        </div>
    @endif
</div>
@endif
</div>
@endsection
