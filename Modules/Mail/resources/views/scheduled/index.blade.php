@extends('theme::layouts.app', ['title' => 'Scheduled mail', 'heading' => $business->name])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.msc-intro{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px;}
.msc-intro__text{max-width:520px;}
.msc-intro__title{font-size:16px;font-weight:800;color:var(--text);margin:0 0 4px;}
.msc-intro__desc{font-size:13px;color:var(--muted);line-height:1.5;margin:0;}
.msc-intro__count{font-size:12px;color:var(--muted);font-weight:600;background:color-mix(in srgb,var(--muted) 12%,transparent);padding:4px 11px;border-radius:999px;white-space:nowrap;}

.msc-list{display:flex;flex-direction:column;gap:10px;}
.msc-card{border:1px solid var(--border);border-radius:14px;padding:14px 16px;background:var(--card);display:flex;align-items:center;gap:12px;transition:border-color .15s,box-shadow .15s;}
.msc-card:hover{border-color:color-mix(in srgb,var(--primary) 30%,var(--border));box-shadow:0 4px 14px rgba(0,0,0,.05);}
.msc-card--done{opacity:.7;}
.msc-card__icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;}
.msc-card__icon--pending{background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--primary);}
.msc-card__icon--sending{background:color-mix(in srgb,#f59e0b 16%,transparent);color:#f59e0b;}
.msc-card__icon--sent{background:color-mix(in srgb,#22c55e 16%,transparent);color:#22c55e;}
.msc-card__icon--failed{background:color-mix(in srgb,#ef4444 16%,transparent);color:#ef4444;}
.msc-card__icon--cancelled{background:color-mix(in srgb,var(--muted) 16%,transparent);color:var(--muted);}

.msc-card__body{flex:1;min-width:0;}
.msc-card__to{font-size:13.5px;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.msc-card__subject{font-size:12.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px;}
.msc-card__meta{display:flex;align-items:center;gap:8px;margin-top:6px;flex-wrap:wrap;}
.msc-card__time{font-size:11.5px;color:var(--muted);display:inline-flex;align-items:center;gap:5px;}

.msc-badge{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;padding:3px 9px;border-radius:999px;text-transform:uppercase;letter-spacing:.03em;border:1px solid var(--border);color:var(--muted);}
.msc-badge--pending{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));color:var(--primary);background:color-mix(in srgb,var(--primary) 10%,transparent);}
.msc-badge--sending{border-color:color-mix(in srgb,#f59e0b 45%,var(--border));color:#f59e0b;background:color-mix(in srgb,#f59e0b 10%,transparent);}
.msc-badge--sent{border-color:color-mix(in srgb,#22c55e 45%,var(--border));color:#22c55e;background:color-mix(in srgb,#22c55e 10%,transparent);}
.msc-badge--failed{border-color:color-mix(in srgb,#ef4444 45%,var(--border));color:#ef4444;background:color-mix(in srgb,#ef4444 10%,transparent);cursor:help;}
.msc-badge--cancelled{color:var(--muted);}

.msc-card__actions{flex-shrink:0;}
.msc-icon-btn{width:30px;height:30px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--muted);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:12.5px;transition:all .15s;}
.msc-icon-btn:hover{color:#ef4444;border-color:#ef4444;background:color-mix(in srgb,#ef4444 8%,transparent);}

.msc-empty{border:1.5px dashed var(--border);border-radius:14px;padding:40px 20px;text-align:center;}
.msc-empty__icon{width:52px;height:52px;border-radius:14px;background:color-mix(in srgb,var(--primary) 12%,transparent);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:20px;margin:0 auto 14px;}
.msc-empty__title{font-size:14px;font-weight:700;color:var(--text);margin:0 0 4px;}
.msc-empty__desc{font-size:12.5px;color:var(--muted);margin:0 0 16px;}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:20px;">
    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="pcat-banner pcat-banner--err">{{ session('error') }}</div>
    @endif

    <div class="msc-intro">
        <div class="msc-intro__text">
            <h1 class="msc-intro__title">Scheduled mail</h1>
            <p class="msc-intro__desc">Emails composed with a future send time. A pending message can still be cancelled before it goes out.</p>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span class="msc-intro__count">{{ $scheduled->count() }} {{ $scheduled->count() === 1 ? 'message' : 'messages' }}</span>
            <a href="{{ route('mail.inbox.compose') }}" class="linkbtn" style="padding:9px 18px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-pen"></i> Compose
            </a>
        </div>
    </div>

    @if($scheduled->isEmpty())
        <div class="msc-empty">
            <div class="msc-empty__icon"><i class="fa fa-clock"></i></div>
            <p class="msc-empty__title">Nothing scheduled</p>
            <p class="msc-empty__desc">Send later from Compose to see it appear here.</p>
            <a href="{{ route('mail.inbox.compose') }}" class="linkbtn" style="padding:8px 18px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-pen"></i> Compose
            </a>
        </div>
    @else
        @php
            $statusMeta = [
                'pending'   => ['icon' => 'fa-clock',         'label' => 'Pending',   'class' => 'pending'],
                'sending'   => ['icon' => 'fa-spinner fa-spin','label' => 'Sending…',  'class' => 'sending'],
                'sent'      => ['icon' => 'fa-check',         'label' => 'Sent',      'class' => 'sent'],
                'failed'    => ['icon' => 'fa-triangle-exclamation', 'label' => 'Failed', 'class' => 'failed'],
                'cancelled' => ['icon' => 'fa-ban',           'label' => 'Cancelled', 'class' => 'cancelled'],
            ];
        @endphp
        <div class="msc-list">
            @foreach($scheduled as $s)
                @php $meta = $statusMeta[$s->status] ?? $statusMeta['cancelled']; @endphp
                <article class="msc-card {{ in_array($s->status, ['sent', 'cancelled']) ? 'msc-card--done' : '' }}">
                    <div class="msc-card__icon msc-card__icon--{{ $meta['class'] }}"><i class="fa {{ $meta['icon'] }}"></i></div>
                    <div class="msc-card__body">
                        <div class="msc-card__to">{{ $s->to_address }}</div>
                        <div class="msc-card__subject">{{ $s->subject ?: '(no subject)' }}</div>
                        <div class="msc-card__meta">
                            <span class="msc-card__time"><i class="fa fa-calendar"></i> {{ $s->scheduled_at->format('d M Y, H:i') }}</span>
                            <span class="msc-badge msc-badge--{{ $meta['class'] }}" @if($s->status === 'failed' && $s->error) title="{{ $s->error }}" @endif>
                                <i class="fa {{ $meta['icon'] }}"></i> {{ $meta['label'] }}
                            </span>
                        </div>
                    </div>
                    <div class="msc-card__actions">
                        @if($s->status === 'pending')
                            <form method="POST" action="{{ route('mail.scheduled.cancel', $s) }}" onsubmit="return confirm('Cancel this scheduled message?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="msc-icon-btn" title="Cancel"><i class="fa fa-xmark"></i></button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
