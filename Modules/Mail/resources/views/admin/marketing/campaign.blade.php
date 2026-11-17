@extends('theme::layouts.app', ['title' => 'Campaign', 'heading' => 'Email Marketing'])

@section('content')
@include('mail::admin.marketing._styles')
<style>
.aem-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px;}
@media(max-width:760px){.aem-stats{grid-template-columns:repeat(2,minmax(0,1fr));}}
.aem-stat{padding:14px 16px;border:1px solid var(--border);border-radius:14px;background:var(--card);}
.aem-stat-label{margin:0;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);}
.aem-stat-value{margin:6px 0 0;font-size:22px;font-weight:800;line-height:1.2;}
.aem-split{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);gap:18px;align-items:start;}
@media(max-width:1000px){.aem-split{grid-template-columns:1fr;}}
.aem-filter-chips{display:flex;gap:6px;flex-wrap:wrap;}
.aem-filter-chips a{padding:5px 11px;border-radius:999px;border:1px solid var(--border);color:var(--muted);font-size:12px;font-weight:600;text-decoration:none;}
.aem-filter-chips a.is-on{background:color-mix(in srgb,var(--primary) 14%,transparent);border-color:color-mix(in srgb,var(--primary) 45%,var(--border));color:var(--primary);}
.aem-error{font-size:11.5px;color:#dc2626;margin-top:2px;max-width:340px;overflow-wrap:anywhere;}
</style>

@php
    $total = max(1, $campaign->recipients_count);
    $pending = $campaign->pendingCount();
@endphp

<div class="aem-wrap">
    <a href="{{ route('admin.email-marketing.index', ['tab' => 'campaigns']) }}" class="aem-back"><i class="fa fa-arrow-left"></i> Sent campaigns</a>
    <div class="aem-header">
        <div style="min-width:0;">
            <h1 class="aem-title" style="overflow-wrap:anywhere;">{{ $campaign->subject }}</h1>
            <p class="aem-sub">
                Sent {{ $campaign->created_at?->format('d M Y, H:i') }}
                @if($campaign->creator) by {{ $campaign->creator->name }} @endif
                · {{ $campaign->template?->name ? 'Template: '.$campaign->template->name : 'Custom email' }}
            </p>
        </div>
        <div class="aem-actions">
            <span class="aem-badge aem-badge--{{ $campaign->status }}" style="font-size:12px;padding:4px 12px;">
                @if($campaign->status === 'sending')<i class="fa fa-spinner fa-spin"></i>@else<i class="fa fa-circle-check"></i>@endif
                {{ $campaign->status }}
            </span>
            @if($campaign->failed_count > 0)
                <form method="POST" action="{{ route('admin.email-marketing.campaigns.retry', $campaign) }}" style="margin:0;" onsubmit="return confirm('Retry the {{ $campaign->failed_count }} failed email(s)?');">
                    @csrf
                    <button type="submit" class="aem-btn"><i class="fa fa-rotate-right"></i> Retry failed</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('status'))
        <div class="aem-msg"><i class="fa fa-circle-check" style="color:#22c55e;"></i> {{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="aem-msg aem-msg--err"><i class="fa fa-circle-exclamation"></i> {{ $errors->first() }}</div>
    @endif

    <div class="aem-stats">
        <div class="aem-stat"><p class="aem-stat-label">Recipients</p><p class="aem-stat-value">{{ number_format($campaign->recipients_count) }}</p></div>
        <div class="aem-stat"><p class="aem-stat-label">Delivered</p><p class="aem-stat-value" style="color:#16a34a;">{{ number_format($campaign->sent_count) }}</p></div>
        <div class="aem-stat"><p class="aem-stat-label">Failed</p><p class="aem-stat-value" style="{{ $campaign->failed_count ? 'color:#dc2626;' : '' }}">{{ number_format($campaign->failed_count) }}</p></div>
        <div class="aem-stat"><p class="aem-stat-label">Waiting</p><p class="aem-stat-value" style="{{ $pending ? 'color:#b45309;' : '' }}">{{ number_format($pending) }}</p></div>
    </div>

    <div class="aem-progress" style="height:8px;margin-bottom:6px;">
        <span class="ok" style="width:{{ $campaign->sent_count / $total * 100 }}%"></span>
        <span class="bad" style="width:{{ $campaign->failed_count / $total * 100 }}%"></span>
    </div>
    @if($campaign->status === 'sending')
        <p class="aem-hint" style="margin:0 0 18px;">
            <i class="fa fa-circle-info"></i> Emails are delivered in the background by the queue worker. This page refreshes automatically.
            If "Waiting" never goes down, make sure <code>php artisan queue:work</code> is running.
        </p>
    @else
        <div style="margin-bottom:18px;"></div>
    @endif

    <div class="aem-split">
        <div class="aem-card" style="overflow:hidden;">
            <div class="aem-card-head"><p class="aem-card-title"><i class="fa fa-envelope"></i> Email content</p></div>
            <iframe sandbox title="Email content" style="border:0;width:100%;height:560px;display:block;background:#f1f5f9;"
                srcdoc="{{ '<!doctype html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:24px 14px;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;"><div style="max-width:600px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:28px 24px;font-size:14px;line-height:1.6;color:#1e293b;">'.$campaign->body.'</div></body></html>' }}"></iframe>
        </div>

        <div class="aem-card" style="overflow:hidden;">
            <div class="aem-card-head" style="flex-wrap:wrap;">
                <p class="aem-card-title"><i class="fa fa-users"></i> Recipients</p>
                <div class="aem-filter-chips">
                    @foreach([null => 'All', 'sent' => 'Delivered', 'failed' => 'Failed', 'pending' => 'Waiting'] as $key => $label)
                        <a href="{{ route('admin.email-marketing.campaigns.show', array_filter(['campaign' => $campaign->id, 'status' => $key ?: null])) }}" class="{{ $status === ($key ?: null) ? 'is-on' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table class="aem-table">
                    <thead><tr><th>Recipient</th><th>Status</th><th>Time</th></tr></thead>
                    <tbody>
                        @forelse($recipients as $r)
                            <tr>
                                <td data-label="Recipient">
                                    <div class="aem-main">{{ $r->name ?: '—' }}</div>
                                    <div class="aem-meta">{{ $r->email }}</div>
                                    @if($r->error)<div class="aem-error" title="{{ $r->error }}">{{ \Illuminate\Support\Str::limit($r->error, 140) }}</div>@endif
                                </td>
                                <td data-label="Status"><span class="aem-badge aem-badge--{{ $r->status }}">{{ $r->status === 'sent' ? 'delivered' : $r->status }}</span></td>
                                <td data-label="Time" class="aem-meta" style="white-space:nowrap;">{{ ($r->sent_at ?? $r->updated_at)?->format('d M, H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3"><div class="aem-empty" style="padding:30px 16px;"><p class="aem-empty-sub" style="margin:0;">No recipients in this view.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($recipients->hasPages())
                <div style="padding:12px 16px;border-top:1px solid var(--border);">{{ $recipients->links() }}</div>
            @endif
        </div>
    </div>
</div>

@if($campaign->status === 'sending')
<script>setTimeout(function () { window.location.reload(); }, 5000);</script>
@endif
@endsection
