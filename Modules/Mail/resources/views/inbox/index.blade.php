@extends('theme::layouts.app', ['title' => 'Mail', 'heading' => $business->name])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.mi-list{display:flex;flex-direction:column;border:1px solid var(--border);border-radius:12px;overflow:hidden;}
.mi-row{display:flex;align-items:center;gap:12px;padding:12px 14px;text-decoration:none;color:var(--text);border-bottom:1px solid var(--border);transition:background .12s;}
.mi-row:last-child{border-bottom:none;}
.mi-row:hover{background:color-mix(in srgb,var(--card) 80%,var(--border) 20%);}
.mi-row--unread{background:color-mix(in srgb,var(--primary) 5%,transparent);font-weight:700;}
.mi-row__name{flex:1;min-width:0;font-size:13.5px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.mi-row__meta{display:flex;align-items:center;gap:8px;flex-shrink:0;}
.mi-badge-new{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;padding:2px 8px;border-radius:999px;background:color-mix(in srgb,var(--primary) 18%,transparent);color:var(--primary);}
.mi-row__count{font-size:12px;font-weight:600;color:var(--muted);background:color-mix(in srgb,var(--muted) 15%,transparent);padding:2px 9px;border-radius:999px;}

.mi-layout{display:flex;align-items:flex-start;gap:18px;}
.mi-main{flex:1;min-width:0;}
.mi-side{width:220px;flex-shrink:0;}
.mi-side__section{border:1px solid var(--border);border-radius:12px;padding:12px;margin-bottom:14px;}
.mi-side__title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:8px;}
.mi-side__link{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:6px 8px;border-radius:8px;font-size:13px;text-decoration:none;color:var(--text);white-space:nowrap;overflow:hidden;}
.mi-side__link span:first-child{overflow:hidden;text-overflow:ellipsis;}
.mi-side__link:hover{background:color-mix(in srgb,var(--card) 80%,var(--border) 20%);}
.mi-side__link--active{background:color-mix(in srgb,var(--primary) 12%,transparent);font-weight:700;}
.mi-side__count{color:var(--muted);font-size:11.5px;flex-shrink:0;}
.mi-side__link--active .mi-side__count{color:inherit;}
@media (max-width: 860px){
    .mi-layout{flex-direction:column;}
    .mi-side{width:100%;}
}
</style>

<div class="pcat-page-card card" style="padding:14px;">
    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="pcat-banner pcat-banner--err">{{ session('error') }}</div>
    @endif

    <div class="pcat-toolbar">
        <div style="display:flex;gap:6px;">
            <a href="{{ route('mail.inbox.index', ['box' => 'inbox']) }}" class="linkbtn"
               style="padding:7px 14px;font-size:12.5px;{{ $box === 'inbox' ? '' : 'background:transparent;border:1px solid var(--border);color:var(--text);' }}">
                Inbox @if($unreadCount) <span class="pcat-badge pcat-badge--on" style="margin-left:4px;">{{ $unreadCount }}</span> @endif
            </a>
            <a href="{{ route('mail.inbox.index', ['box' => 'sent']) }}" class="linkbtn"
               style="padding:7px 14px;font-size:12.5px;{{ $box === 'sent' ? '' : 'background:transparent;border:1px solid var(--border);color:var(--text);' }}">
                Sent
            </a>
        </div>
        <a href="{{ route('mail.inbox.compose') }}" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-pen"></i> Compose
        </a>
    </div>

    <form method="GET" action="{{ route('mail.inbox.index') }}" style="display:flex;gap:8px;margin-bottom:14px;">
        <input type="hidden" name="box" value="{{ $box }}">
        @if($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
        @if($contact !== '')<input type="hidden" name="contact" value="{{ $contact }}">@endif
        <input type="text" name="q" value="{{ $search }}" placeholder="Search subject, sender, or message…"
               style="flex:1;padding:8px 12px;border-radius:8px;border:1px solid var(--border);font-size:13px;background:var(--card);color:var(--text);">
        <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Search</button>
        @if($search !== '')
            <a href="{{ route('mail.inbox.index', ['box' => $box, 'status' => $status !== 'all' ? $status : null, 'contact' => $contact !== '' ? $contact : null]) }}" class="linkbtn"
               style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Clear</a>
        @endif
    </form>

    <div class="mi-layout">
        <div class="mi-main">
            @if($messages->isEmpty())
                <p class="muted" style="margin:24px 0;font-size:13px;">
                    @if($search !== '' || $status !== 'all' || $contact !== '')
                        No messages match this filter.
                    @elseif($box === 'inbox')
                        No messages yet. Connect a mailbox under <a href="{{ route('mail.settings.edit') }}" class="pcat-link">Mail Settings</a> to receive mail here.
                    @else
                        No sent messages yet.
                    @endif
                </p>
            @else
                <div class="mi-list">
                    @foreach($groupedMessages as $address => $group)
                        @php
                            $latest = $group->first();
                            $hasUnread = $box === 'inbox' && $group->contains(fn ($m) => !$m->is_read);
                            $displayName = $box === 'sent' ? ($address ?: '(unknown recipient)') : ($latest->from_name ?: ($address ?: '(unknown sender)'));
                        @endphp
                        <a href="{{ route('mail.inbox.show', $latest) }}" class="mi-row {{ $hasUnread ? 'mi-row--unread' : '' }}">
                            <span class="mi-row__name">{{ $displayName }}</span>
                            <span class="mi-row__meta">
                                @if($hasUnread)
                                    <span class="mi-badge-new">New</span>
                                @endif
                                <span class="mi-row__count">{{ $group->count() }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
                <div style="margin-top:14px;">{{ $messages->links() }}</div>
            @endif
        </div>

        <div class="mi-side">
            @if($box === 'inbox')
                <div class="mi-side__section">
                    <div class="mi-side__title">Status</div>
                    @foreach(['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'] as $key => $label)
                        <a href="{{ route('mail.inbox.index', ['box' => $box, 'q' => $search !== '' ? $search : null, 'status' => $key !== 'all' ? $key : null, 'contact' => $contact !== '' ? $contact : null]) }}"
                           class="mi-side__link {{ $status === $key ? 'mi-side__link--active' : '' }}">
                            <span>{{ $label }}</span>
                            <span class="mi-side__count">{{ $statusCounts[$key] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if($contacts->isNotEmpty())
                <div class="mi-side__section">
                    <div class="mi-side__title">{{ $box === 'sent' ? 'To' : 'From' }}</div>
                    @if($contact !== '')
                        <a href="{{ route('mail.inbox.index', ['box' => $box, 'q' => $search !== '' ? $search : null, 'status' => $status !== 'all' ? $status : null]) }}"
                           class="mi-side__link">
                            <span><i class="fa fa-xmark" style="margin-right:4px;"></i>Clear</span>
                        </a>
                    @endif
                    @foreach($contacts as $c)
                        <a href="{{ route('mail.inbox.index', ['box' => $box, 'q' => $search !== '' ? $search : null, 'status' => $status !== 'all' ? $status : null, 'contact' => $c->address]) }}"
                           class="mi-side__link {{ $contact === $c->address ? 'mi-side__link--active' : '' }}" title="{{ $c->address }}">
                            <span>{{ $box === 'sent' ? $c->address : ($c->from_name ?: $c->address) }}</span>
                            <span class="mi-side__count">{{ $c->cnt }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
