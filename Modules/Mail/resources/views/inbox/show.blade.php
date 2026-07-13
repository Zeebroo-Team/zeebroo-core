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
.ms-rr__percent{font-size:26px;font-weight:800;color:var(--text);}
.ms-rr__bar{height:6px;border-radius:999px;background:color-mix(in srgb,var(--muted) 20%,transparent);overflow:hidden;margin:8px 0;}
.ms-rr__bar-fill{height:100%;background:var(--primary);border-radius:999px;}
.ms-rr__caption{font-size:12px;color:var(--muted);}
.ms-assign-select{width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);font-size:13px;background:var(--card);color:var(--text);}

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

.ms-schedule-toggle-row{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:13px;color:var(--text);cursor:pointer;}
.ms-schedule-toggle-row i{color:var(--muted);margin-right:4px;}
.ms-schedule-field{margin-top:10px;}
.ms-schedule-field input[type="datetime-local"]{width:100%;max-width:100%;padding:8px 6px;border-radius:8px;border:1px solid var(--border);font-size:12px;background:var(--card);color:var(--text);box-sizing:border-box;}

.ai-toolbar{display:flex;gap:8px;margin-bottom:14px;}
.ai-toolbar__btn{padding:7px 14px;font-size:12.5px;background:transparent;border:1px solid var(--border);color:var(--text);}
.ai-toolbar__btn:disabled{opacity:.6;cursor:default;}
.ai-summary-box{border:1px solid var(--border);border-radius:12px;padding:12px 14px;margin-bottom:14px;background:color-mix(in srgb,var(--primary) 6%,transparent);}
.ai-summary-box__title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:6px;}
.ai-summary-box__text{font-size:13px;line-height:1.55;color:var(--text);white-space:pre-line;}

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

    @if($counterpartEmail)
        <div class="ai-toolbar">
            <button type="button" id="ai-summarize-btn" class="linkbtn ai-toolbar__btn"
                    data-url="{{ route('mail.inbox.aiSummary', $message) }}">
                <i class="fa fa-file-lines"></i> Summarize
            </button>
            <button type="button" id="ai-suggest-btn" class="linkbtn ai-toolbar__btn"
                    data-url="{{ route('mail.inbox.aiSuggestReply', $message) }}">
                <i class="fa fa-wand-magic-sparkles"></i> Suggest reply
            </button>
        </div>
        <div id="ai-summary-box" class="ai-summary-box" hidden>
            <div class="ai-summary-box__title"><i class="fa fa-file-lines"></i> Conversation summary</div>
            <div class="ai-summary-box__text" id="ai-summary-text"></div>
        </div>
    @endif

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
            <form method="POST" action="{{ route('mail.inbox.send') }}" id="chat-reply-form">
                @csrf
                <input type="hidden" name="to" value="{{ $counterpartEmail }}">
                <input type="hidden" name="subject" value="{{ $replySubject }}">
                <input type="hidden" name="reply_to_message" value="{{ $message->id }}">

                <div class="chat-reply">
                    <textarea name="body" rows="2" required placeholder="Type a reply…"></textarea>
                    <button type="submit" class="linkbtn chat-reply__send" id="chat-reply-send">
                        <i class="fa fa-paper-plane"></i> Send
                    </button>
                </div>
            </form>
            <a href="{{ route('mail.inbox.compose', ['reply_to' => $message->id]) }}" class="pcat-link chat-fullcompose">
                Full compose (templates, schedule)…
            </a>
        </div>
    @endif
</div>

@if($counterpartEmail)
<div class="ms-side">
    <div class="ms-side__section">
        <div class="ms-side__title">Schedule send</div>
        <label class="ms-schedule-toggle-row" for="chat-schedule-toggle">
            <span><i class="fa fa-clock"></i> Send later</span>
            <input type="checkbox" id="chat-schedule-toggle">
        </label>
        <div class="ms-schedule-field" id="chat-schedule-row" hidden>
            <input type="datetime-local" name="send_at" id="chat-send-at" form="chat-reply-form">
        </div>
    </div>

    @if($customer)
        <div class="ms-side__section">
            <div class="ms-side__title">Customer</div>
            <div class="ms-cust-name">{{ $customer->name }}</div>
            <div class="ms-cust-meta">{{ $customer->email }}</div>
            @if($customer->phone)
                <div class="ms-cust-meta">{{ $customer->phone }}</div>
            @endif
            <a href="{{ route('crm.contacts.show', $customer) }}" class="linkbtn" style="margin-top:12px;padding:7px 14px;font-size:12.5px;width:100%;display:flex;align-items:center;justify-content:center;gap:6px;box-sizing:border-box;">
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
                <button type="submit" class="linkbtn" style="padding:8px 14px;font-size:12.5px;width:100%;display:flex;align-items:center;justify-content:center;gap:6px;box-sizing:border-box;">
                    <i class="fa fa-user-plus"></i> Convert to customer
                </button>
            </form>
        </div>
    @endif

    @if($responseRate)
        <div class="ms-side__section">
            <div class="ms-side__title">Response rate</div>
            <div class="ms-rr__percent">{{ $responseRate['percent'] }}%</div>
            <div class="ms-rr__bar"><div class="ms-rr__bar-fill" style="width:{{ $responseRate['percent'] }}%;"></div></div>
            <div class="ms-rr__caption">{{ $responseRate['replied'] }} of {{ $responseRate['total'] }} messages replied to</div>
        </div>
    @endif

    @if($assignableUsers->isNotEmpty())
        <div class="ms-side__section">
            <div class="ms-side__title">Assigned to</div>
            <form method="POST" action="{{ route('mail.inbox.assign', $message) }}" id="assign-form">
                @csrf
                <select name="assigned_to" id="assign-select" class="ms-assign-select" onchange="document.getElementById('assign-form').submit();">
                    <option value="">Unassigned</option>
                    @foreach($assignableUsers as $user)
                        <option value="{{ $user->id }}" @selected($assignment && (int) $assignment->assigned_to === (int) $user->id)>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
    @endif
</div>
@endif
</div>

<script>
(function () {
    var toggle  = document.getElementById('chat-schedule-toggle');
    var row     = document.getElementById('chat-schedule-row');
    var input   = document.getElementById('chat-send-at');
    var sendBtn = document.getElementById('chat-reply-send');

    function setScheduled(on) {
        row.hidden = !on;
        sendBtn.innerHTML = on
            ? '<i class="fa fa-clock"></i> Schedule'
            : '<i class="fa fa-paper-plane"></i> Send';
        if (!on) input.value = '';
    }

    toggle?.addEventListener('change', function () { setScheduled(toggle.checked); });
})();

(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    var summarizeBtn = document.getElementById('ai-summarize-btn');
    var suggestBtn   = document.getElementById('ai-suggest-btn');
    var summaryBox   = document.getElementById('ai-summary-box');
    var summaryText  = document.getElementById('ai-summary-text');
    var replyTextarea = document.querySelector('#chat-reply-form textarea[name="body"]');

    function withLoading(btn, loadingLabel, task) {
        var original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + loadingLabel;
        task().finally(function () {
            btn.disabled = false;
            btn.innerHTML = original;
        });
    }

    summarizeBtn?.addEventListener('click', function () {
        withLoading(summarizeBtn, 'Summarizing…', function () {
            return fetch(summarizeBtn.dataset.url, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'},
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                summaryBox.hidden = false;
                summaryText.textContent = data.summary || data.error || 'Something went wrong.';
            })
            .catch(function () {
                summaryBox.hidden = false;
                summaryText.textContent = 'Something went wrong.';
            });
        });
    });

    suggestBtn?.addEventListener('click', function () {
        withLoading(suggestBtn, 'Drafting…', function () {
            return fetch(suggestBtn.dataset.url, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'},
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.reply) {
                    replyTextarea.value = data.reply;
                    replyTextarea.focus();
                } else {
                    alert(data.error || 'Something went wrong.');
                }
            })
            .catch(function () { alert('Something went wrong.'); });
        });
    });
})();
</script>
@endsection
