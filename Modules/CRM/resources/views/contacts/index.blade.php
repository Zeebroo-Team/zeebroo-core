@extends('theme::layouts.app', ['title' => 'Contacts', 'heading' => 'Contacts'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.crm-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Every customer for <strong style="color:var(--text);">{{ $business->name }}</strong>, with their CRM activity and open tasks.
        New contacts are added from <a href="{{ route('pos.customers.index') }}" class="pcat-link">Customers</a>.
    </p>

    <form method="GET" action="{{ route('crm.contacts.index') }}" style="display:flex;gap:6px;margin-bottom:12px;">
        <input type="text" name="q" value="{{ $search }}" placeholder="Search contacts…"
               style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);font-size:12px;background:var(--card);color:var(--text);width:220px;">
        <button type="submit" class="linkbtn" style="padding:6px 14px;font-size:12px;">Search</button>
        @if(filled($search))
            <a href="{{ route('crm.contacts.index') }}" class="linkbtn" style="padding:6px 14px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--muted);">Clear</a>
        @endif
    </form>

    @if($customers->isEmpty())
        <p class="muted" style="margin:24px 0;font-size:13px;">
            @if(filled($search)) No contacts match your search. @else No customers yet. @endif
        </p>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Open tasks</th>
                        <th>Last activity</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $c)
                        <tr>
                            <td><strong style="color:var(--text);">{{ $c->name }}</strong></td>
                            <td>{{ $c->phone ?? '—' }}</td>
                            <td>{{ $c->email ?? '—' }}</td>
                            <td>
                                @php $openTasks = (int) ($openTaskCounts[$c->id] ?? 0); @endphp
                                @if($openTasks > 0)
                                    <span class="pcat-badge pcat-badge--on">{{ $openTasks }}</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>
                                @php $last = $lastActivityAt[$c->id] ?? null; @endphp
                                {{ $last ? \Illuminate\Support\Carbon::parse($last)->format('M j, Y') : '—' }}
                            </td>
                            <td style="text-align:right;">
                                <a href="{{ route('crm.contacts.show', $c) }}" class="pcat-link">
                                    <i class="fa fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('dashboard') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Overview
    </a>
</div>
@endsection
