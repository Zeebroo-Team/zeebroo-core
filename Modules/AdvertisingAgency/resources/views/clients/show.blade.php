@extends('theme::layouts.app', ['title' => $client->name, 'heading' => 'Advertising Agency'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('advertising-agency::partials.hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok">{{ session('status') }}</div>
    @endif

    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0 0 4px;font-size:18px;">{{ $client->name }}</h2>
            @if($client->company)
                <span class="muted" style="font-size:13px;"><i class="fa fa-building"></i> {{ $client->company }}</span>
            @endif
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('advertising-agency.clients.edit', $client) }}" class="pcat-link">
                <i class="fa fa-pencil"></i> Edit
            </a>
            <form method="POST" action="{{ route('advertising-agency.clients.destroy', $client) }}"
                  onsubmit="return confirm('Delete this client?')">
                @csrf @method('DELETE')
                <button class="pcat-link pcat-link--danger" style="background:none;border:none;cursor:pointer;padding:0;">
                    <i class="fa fa-trash"></i> Delete
                </button>
            </form>
        </div>
    </div>

    {{-- Info grid --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:24px;">
        @if($client->email)
            <div class="pcat-info-cell">
                <span class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Email</span>
                <a href="mailto:{{ $client->email }}" style="font-size:13px;">{{ $client->email }}</a>
            </div>
        @endif
        @if($client->phone)
            <div class="pcat-info-cell">
                <span class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Phone</span>
                <span style="font-size:13px;">{{ $client->phone }}</span>
            </div>
        @endif
        @if($client->industry)
            <div class="pcat-info-cell">
                <span class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Industry</span>
                <span style="font-size:13px;">{{ $client->industry }}</span>
            </div>
        @endif
        @if($client->website)
            <div class="pcat-info-cell">
                <span class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Website</span>
                <a href="{{ $client->website }}" target="_blank" rel="noopener" style="font-size:13px;">{{ $client->website }}</a>
            </div>
        @endif
    </div>

    @if($client->notes)
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px;">{{ $client->notes }}</p>
    @endif

    {{-- Campaigns --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <h3 style="margin:0;font-size:14px;font-weight:700;">Campaigns ({{ $client->campaigns->count() }})</h3>
        <a href="{{ route('advertising-agency.campaigns.index', ['client_id' => $client->id]) }}" class="pcat-link">
            <i class="fa fa-plus"></i> New campaign
        </a>
    </div>

    <div class="pcat-table-wrap">
        <table class="pcat-table">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Channel</th>
                    <th>Budget</th>
                    <th>Dates</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($client->campaigns as $c)
                    <tr>
                        <td><strong>{{ $c->name }}</strong></td>
                        <td><span class="muted" style="font-size:12px;">{{ $c->channel ? ucwords(str_replace('_',' ',$c->channel)) : '—' }}</span></td>
                        <td>{{ number_format($c->budget, 2) }}</td>
                        <td>
                            <span class="muted" style="font-size:12px;">
                                {{ $c->start_date?->format('d M Y') ?? '—' }} → {{ $c->end_date?->format('d M Y') ?? '—' }}
                            </span>
                        </td>
                        <td>
                            @php
                                $colors = ['draft'=>'#94a3b8','active'=>'#22c55e','paused'=>'#f59e0b','completed'=>'#3b82f6','cancelled'=>'#ef4444'];
                            @endphp
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
                    <tr><td colspan="6" class="pcat-empty">No campaigns yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
