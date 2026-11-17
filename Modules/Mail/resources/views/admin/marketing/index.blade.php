@extends('theme::layouts.app', ['title' => 'Email Marketing', 'heading' => 'Email Marketing'])

@section('content')
@include('mail::admin.marketing._styles')
<style>
.aem-tabs{display:flex;gap:4px;border-bottom:1px solid var(--border);margin-bottom:18px;}
.aem-tab{padding:10px 14px;font-size:13.5px;font-weight:650;color:var(--muted);text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-1px;display:inline-flex;align-items:center;gap:7px;}
.aem-tab:hover{color:var(--text);}
.aem-tab.is-active{color:var(--primary);border-bottom-color:var(--primary);}
.aem-tab-count{font-size:11px;padding:1px 7px;border-radius:999px;background:color-mix(in srgb,var(--muted) 14%,transparent);color:var(--muted);}
.aem-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;}
.aem-tpl{display:flex;flex-direction:column;overflow:hidden;transition:border-color .15s,box-shadow .15s;}
.aem-tpl:hover{border-color:color-mix(in srgb,var(--primary) 35%,var(--border));box-shadow:0 6px 18px rgba(0,0,0,.07);}
.aem-tpl-preview{height:150px;overflow:hidden;background:#f1f5f9;border-bottom:1px solid var(--border);position:relative;}
.aem-tpl-preview iframe{width:200%;height:300px;border:0;transform:scale(.5);transform-origin:0 0;pointer-events:none;}
.aem-tpl-body{padding:14px 16px;display:flex;flex-direction:column;gap:4px;flex:1;}
.aem-tpl-name{margin:0;font-size:14.5px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.aem-tpl-subject{font-size:12px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.aem-tpl-foot{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 16px;border-top:1px solid var(--border);}
</style>

<div class="aem-wrap">
    <div class="aem-header">
        <div>
            <h1 class="aem-title"><i class="fa fa-envelope-open-text" style="color:var(--primary);margin-right:8px;"></i>Email Marketing</h1>
            <p class="aem-sub">Create reusable email templates, then send them to many users at once. Users who unsubscribed are skipped automatically.</p>
        </div>
        <div class="aem-actions">
            <a href="{{ route('admin.email-marketing.templates.create') }}" class="aem-btn"><i class="fa fa-plus"></i> New template</a>
            <a href="{{ route('admin.email-marketing.compose') }}" class="aem-btn aem-btn--primary"><i class="fa fa-paper-plane"></i> Send email</a>
        </div>
    </div>

    @if(session('status'))
        <div class="aem-msg"><i class="fa fa-circle-check" style="color:#22c55e;"></i> {{ session('status') }}</div>
    @endif

    <nav class="aem-tabs">
        <a href="{{ route('admin.email-marketing.index') }}" class="aem-tab {{ $tab === 'templates' ? 'is-active' : '' }}">
            <i class="fa fa-file-lines"></i> Templates <span class="aem-tab-count">{{ $templates->count() }}</span>
        </a>
        <a href="{{ route('admin.email-marketing.index', ['tab' => 'campaigns']) }}" class="aem-tab {{ $tab === 'campaigns' ? 'is-active' : '' }}">
            <i class="fa fa-paper-plane"></i> Sent campaigns <span class="aem-tab-count">{{ $campaigns->total() }}</span>
        </a>
    </nav>

    @if($tab === 'templates')
        <div class="aem-grid">
            @forelse($templates as $t)
                <article class="aem-card aem-tpl">
                    <a href="{{ route('admin.email-marketing.templates.edit', $t) }}" class="aem-tpl-preview" title="Edit template">
                        <iframe srcdoc="{{ '<body style="margin:0;padding:20px;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;"><div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;font-size:14px;line-height:1.6;color:#1e293b;">'.$t->body.'</div></body>' }}" sandbox tabindex="-1" loading="lazy"></iframe>
                    </a>
                    <div class="aem-tpl-body">
                        <h3 class="aem-tpl-name" title="{{ $t->name }}">{{ $t->name }}</h3>
                        <div class="aem-tpl-subject" title="{{ $t->subject }}"><i class="fa fa-quote-left" style="font-size:9px;margin-right:4px;"></i>{{ $t->subject }}</div>
                        <div class="aem-meta">Updated {{ $t->updated_at?->diffForHumans() }} · used {{ $t->campaigns_count }} {{ $t->campaigns_count === 1 ? 'time' : 'times' }}</div>
                    </div>
                    <div class="aem-tpl-foot">
                        <div class="aem-actions">
                            <a href="{{ route('admin.email-marketing.templates.edit', $t) }}" class="aem-btn aem-btn--sm"><i class="fa fa-pen"></i> Edit</a>
                            <form method="POST" action="{{ route('admin.email-marketing.templates.destroy', $t) }}" onsubmit="return confirm('Delete the template &quot;{{ addslashes($t->name) }}&quot;? Sent campaigns keep their copy.');" style="margin:0;">
                                @csrf @method('DELETE')
                                <button type="submit" class="aem-btn aem-btn--sm aem-btn--danger" title="Delete"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                        <a href="{{ route('admin.email-marketing.compose', ['template' => $t->id]) }}" class="aem-btn aem-btn--sm aem-btn--primary"><i class="fa fa-paper-plane"></i> Use</a>
                    </div>
                </article>
            @empty
                <div class="aem-card" style="grid-column:1/-1;">
                    <div class="aem-empty">
                        <div class="aem-empty-icon"><i class="fa fa-file-lines"></i></div>
                        <p class="aem-empty-title">No templates yet</p>
                        <p class="aem-empty-sub">Design an email once and reuse it for every campaign.</p>
                        <a href="{{ route('admin.email-marketing.templates.create') }}" class="aem-btn aem-btn--primary"><i class="fa fa-plus"></i> Create your first template</a>
                    </div>
                </div>
            @endforelse
        </div>
    @else
        <div class="aem-card" style="overflow-x:auto;">
            <table class="aem-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Recipients</th>
                        <th>Delivery</th>
                        <th>Status</th>
                        <th>Sent</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $c)
                        @php
                            $total = max(1, $c->recipients_count);
                        @endphp
                        <tr>
                            <td data-label="Subject">
                                <div class="aem-main">{{ $c->subject }}</div>
                                <div class="aem-meta">{{ $c->template?->name ? 'Template: '.$c->template->name : 'Custom email' }}@if($c->creator) · by {{ $c->creator->name }}@endif</div>
                            </td>
                            <td data-label="Recipients">{{ number_format($c->recipients_count) }}</td>
                            <td data-label="Delivery">
                                <div class="aem-progress" title="{{ $c->sent_count }} sent · {{ $c->failed_count }} failed · {{ $c->pendingCount() }} pending">
                                    <span class="ok" style="width:{{ $c->sent_count / $total * 100 }}%"></span>
                                    <span class="bad" style="width:{{ $c->failed_count / $total * 100 }}%"></span>
                                </div>
                                <div class="aem-meta">{{ $c->sent_count }} sent @if($c->failed_count) · <span style="color:#dc2626;">{{ $c->failed_count }} failed</span>@endif</div>
                            </td>
                            <td data-label="Status"><span class="aem-badge aem-badge--{{ $c->status }}">{{ $c->status }}</span></td>
                            <td data-label="Sent" style="white-space:nowrap;" title="{{ $c->created_at?->format('d M Y, H:i') }}">
                                {{ $c->created_at?->format('d M Y') }}
                                <div class="aem-meta">{{ $c->created_at?->format('H:i') }}</div>
                            </td>
                            <td style="text-align:right;">
                                <a href="{{ route('admin.email-marketing.campaigns.show', $c) }}" class="aem-btn aem-btn--sm"><i class="fa fa-eye"></i> View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="aem-empty">
                                    <div class="aem-empty-icon"><i class="fa fa-paper-plane"></i></div>
                                    <p class="aem-empty-title">No emails sent yet</p>
                                    <p class="aem-empty-sub">Campaigns you send will be listed here with their delivery status.</p>
                                    <a href="{{ route('admin.email-marketing.compose') }}" class="aem-btn aem-btn--primary"><i class="fa fa-paper-plane"></i> Send an email</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($campaigns->hasPages())
            <div style="margin-top:16px;">{{ $campaigns->appends(['tab' => 'campaigns'])->links() }}</div>
        @endif
    @endif
</div>
@endsection
