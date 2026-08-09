@extends('theme::layouts.app', ['title' => 'Clients', 'heading' => 'Advertising Agency'])

@php $modalOpen = $hasClients && $errors->any(); @endphp

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('advertising-agency::partials.hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok">{{ session('status') }}</div>
    @endif

    <div class="pcat-toolbar">
        <form method="GET" action="{{ route('advertising-agency.clients.index') }}" style="display:flex;gap:8px;flex:1;">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search clients…" class="pcat-search">
            <select name="status" class="pcat-sel" onchange="this.form.submit()">
                <option value="all" @selected($status === 'all')>All statuses</option>
                <option value="active" @selected($status === 'active')>Active</option>
                <option value="inactive" @selected($status === 'inactive')>Inactive</option>
            </select>
        </form>
        @if($hasClients)
            <button type="button" id="cl-modal-open" class="linkbtn" style="padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fa fa-plus"></i> New client
            </button>
        @endif
    </div>

    @if(!$hasClients)
        <section class="pcat-inline">
            <h2>Add your first client</h2>
            <p class="pcat-muted">Clients are the businesses or people your agency works for. Each client can have multiple campaigns.</p>
            @include('advertising-agency::clients.partials.form')
        </section>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Company</th>
                        <th>Industry</th>
                        <th>Campaigns</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clients as $client)
                        <tr>
                            <td><strong>{{ $client->name }}</strong></td>
                            <td><span class="muted" style="font-size:12px;">{{ $client->company ?: '—' }}</span></td>
                            <td><span class="muted" style="font-size:12px;">{{ $client->industry ?: '—' }}</span></td>
                            <td>{{ $client->campaigns_count ?? 0 }}</td>
                            <td>
                                @if($client->status === 'active')
                                    <span class="pcat-badge pcat-badge--on">Active</span>
                                @else
                                    <span class="pcat-badge pcat-badge--off">Inactive</span>
                                @endif
                            </td>
                            <td style="text-align:right;display:flex;gap:8px;justify-content:flex-end;">
                                <a href="{{ route('advertising-agency.clients.show', $client) }}" class="pcat-link">
                                    <i class="fa fa-eye"></i> View
                                </a>
                                <a href="{{ route('advertising-agency.clients.edit', $client) }}" class="pcat-link">
                                    <i class="fa fa-pencil"></i> Edit
                                </a>
                                <form method="POST" action="{{ route('advertising-agency.clients.destroy', $client) }}"
                                      onsubmit="return confirm('Delete this client and all their campaigns?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="pcat-link pcat-link--danger" style="background:none;border:none;cursor:pointer;padding:0;">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="pcat-empty">No clients found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Create modal --}}
        <div id="cl-modal" class="pcat-modal {{ $modalOpen ? 'pcat-modal--open' : '' }}">
            <div class="pcat-modal-box">
                <div class="pcat-modal-head">
                    <span>New client</span>
                    <button type="button" class="pcat-modal-close" id="cl-modal-close">&times;</button>
                </div>
                @include('advertising-agency::clients.partials.form')
            </div>
        </div>
    @endif
</div>

<script>
(function () {
    const btn   = document.getElementById('cl-modal-open');
    const modal = document.getElementById('cl-modal');
    const close = document.getElementById('cl-modal-close');
    if (btn)   btn.addEventListener('click',  () => modal.classList.add('pcat-modal--open'));
    if (close) close.addEventListener('click', () => modal.classList.remove('pcat-modal--open'));
    if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('pcat-modal--open'); });
})();
</script>
@endsection
