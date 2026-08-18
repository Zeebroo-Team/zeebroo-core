@extends('theme::layouts.app', ['title' => 'Campaigns', 'heading' => 'Advertising Agency'])

@php $modalOpen = $hasCampaigns && $errors->any(); @endphp

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('advertising-agency::partials.hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok">{{ session('status') }}</div>
    @endif

    {{-- Summary tiles --}}
    @if($hasCampaigns)
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;margin-bottom:16px;">
        <div class="pcat-info-cell" style="text-align:center;">
            <span style="font-size:22px;font-weight:700;color:var(--text);">{{ $summary['total'] }}</span>
            <span class="muted" style="font-size:11px;display:block;">Total</span>
        </div>
        <div class="pcat-info-cell" style="text-align:center;">
            <span style="font-size:22px;font-weight:700;color:#22c55e;">{{ $summary['active'] }}</span>
            <span class="muted" style="font-size:11px;display:block;">Active</span>
        </div>
        <div class="pcat-info-cell" style="text-align:center;">
            <span style="font-size:22px;font-weight:700;color:#94a3b8;">{{ $summary['draft'] }}</span>
            <span class="muted" style="font-size:11px;display:block;">Draft</span>
        </div>
        <div class="pcat-info-cell" style="text-align:center;">
            <span style="font-size:22px;font-weight:700;color:var(--accent);">{{ number_format($summary['budget'],2) }}</span>
            <span class="muted" style="font-size:11px;display:block;">Total Budget</span>
        </div>
        <div class="pcat-info-cell" style="text-align:center;">
            <span style="font-size:22px;font-weight:700;color:#f59e0b;">{{ number_format($summary['spent'],2) }}</span>
            <span class="muted" style="font-size:11px;display:block;">Total Spent</span>
        </div>
    </div>
    @endif

    <div class="pcat-toolbar">
        <form method="GET" action="{{ route('advertising-agency.campaigns.index') }}" style="display:flex;gap:8px;flex:1;flex-wrap:wrap;">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search campaigns…" class="pcat-search">
            <select name="status" class="pcat-sel" onchange="this.form.submit()">
                <option value="all" @selected($status === 'all')>All statuses</option>
                @foreach($statuses as $s)
                    <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <select name="client_id" class="pcat-sel" onchange="this.form.submit()">
                <option value="">All clients</option>
                @foreach($clients as $cl)
                    <option value="{{ $cl->id }}" @selected($clientId === $cl->id)>{{ $cl->name }}</option>
                @endforeach
            </select>
        </form>
        @if($hasCampaigns)
            <button type="button" id="cp-modal-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> New campaign
            </button>
        @endif
    </div>

    @if(!$hasCampaigns)
        <section class="pcat-inline">
            <h2>Create your first campaign</h2>
            <p class="pcat-muted">Campaigns track your advertising efforts — budget, channel, dates, creatives, and tasks all in one place.</p>
            @include('advertising-agency::campaigns.partials.form', ['clients' => $clients, 'statuses' => $statuses, 'channels' => $channels])
        </section>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Campaign</th>
                        <th>Client</th>
                        <th>Channel</th>
                        <th>Budget</th>
                        <th>Spent</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $c)
                        @php
                            $colors = ['draft'=>'#94a3b8','active'=>'#22c55e','paused'=>'#f59e0b','completed'=>'#3b82f6','cancelled'=>'#ef4444'];
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $c->name }}</strong>
                                <div class="muted" style="font-size:11px;">
                                    {{ $c->creatives_count }} creatives &middot; {{ $c->tasks_count }} tasks
                                </div>
                            </td>
                            <td><span class="muted" style="font-size:12px;">{{ $c->client->name }}</span></td>
                            <td><span class="muted" style="font-size:12px;">{{ $c->channel ? ucwords(str_replace('_',' ',$c->channel)) : '—' }}</span></td>
                            <td>{{ number_format($c->budget, 2) }}</td>
                            <td>
                                {{ number_format($c->spent, 2) }}
                                @if((float)$c->budget > 0)
                                    <div style="margin-top:3px;height:4px;border-radius:2px;background:var(--border);overflow:hidden;width:60px;">
                                        <div style="height:100%;background:{{ (float)$c->spent > (float)$c->budget ? '#ef4444' : '#22c55e' }};width:{{ min(100,$c->spentPercent()) }}%;"></div>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="muted" style="font-size:12px;">
                                    {{ $c->start_date?->format('d M') ?? '—' }} → {{ $c->end_date?->format('d M Y') ?? '—' }}
                                </span>
                            </td>
                            <td>
                                <span class="pcat-badge" style="background:{{ $colors[$c->status] ?? '#94a3b8' }}20;color:{{ $colors[$c->status] ?? '#94a3b8' }};">
                                    {{ ucfirst($c->status) }}
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <a href="{{ route('advertising-agency.campaigns.show', $c) }}" class="pcat-link">
                                    <i class="fa fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="pcat-empty">No campaigns found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div id="cp-modal" class="pcat-modal {{ $modalOpen ? 'pcat-modal--open' : '' }}">
            <div class="pcat-modal__panel">
                <div class="pcat-modal__head">
                    <span>New campaign</span>
                    <button type="button" class="pcat-modal__close" id="cp-modal-close">&times;</button>
                </div>
                @include('advertising-agency::campaigns.partials.form', ['clients' => $clients, 'statuses' => $statuses, 'channels' => $channels])
            </div>
        </div>
    @endif
</div>

<script>
(function () {
    const btn   = document.getElementById('cp-modal-open');
    const modal = document.getElementById('cp-modal');
    const close = document.getElementById('cp-modal-close');
    if (btn)   btn.addEventListener('click',  () => modal.classList.add('pcat-modal--open'));
    if (close) close.addEventListener('click', () => modal.classList.remove('pcat-modal--open'));
    if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('pcat-modal--open'); });
})();
</script>
@endsection
