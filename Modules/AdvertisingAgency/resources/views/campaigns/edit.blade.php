@extends('theme::layouts.app', ['title' => 'Edit Campaign', 'heading' => 'Advertising Agency'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:780px;padding:14px;">
    @include('advertising-agency::partials.hub-nav')

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
        <div>
            <h2 style="margin:0 0 2px;font-size:16px;">Edit: {{ $campaign->name }}</h2>
            <span class="muted" style="font-size:12px;">Client: {{ $campaign->client->name }}</span>
        </div>
        <a href="{{ route('advertising-agency.campaigns.show', $campaign) }}" class="pcat-link">
            <i class="fa fa-arrow-left"></i> Back to campaign
        </a>
    </div>

    @include('advertising-agency::campaigns.partials.form', [
        'campaign' => $campaign,
        'clients'  => $clients,
        'statuses' => $statuses,
        'channels' => $channels,
    ])
</div>
@endsection
