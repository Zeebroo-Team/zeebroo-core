@extends('theme::layouts.app', ['title' => $customer->name, 'heading' => $customer->name])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.crm-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <div style="margin-bottom:14px;">
        <h2 style="margin:0 0 4px;font-size:18px;font-weight:800;">{{ $customer->name }}</h2>
        <div class="muted" style="font-size:13px;">
            @if($customer->phone) {{ $customer->phone }} @endif
            @if($customer->email) · {{ $customer->email }} @endif
            @if($customer->address) · {{ $customer->address }} @endif
        </div>
        <div style="margin-top:8px;">
            <a href="{{ route('pos.customers.index') }}" class="pcat-link">
                <i class="fa fa-pen"></i> Edit customer details
            </a>
        </div>
    </div>

    @if($customer->notes)
        <div class="pcat-inline" style="margin-bottom:16px;">
            <h2 style="font-size:13px;">Notes</h2>
            <p class="pcat-muted" style="white-space:pre-line;">{{ $customer->notes }}</p>
        </div>
    @endif

    <div style="display:grid;gap:20px;grid-template-columns:1fr;">
        <section>
            <h2 style="font-size:14px;font-weight:800;margin:0 0 8px;">Tasks</h2>
            @include('crm::partials.task-form', ['subjectType' => 'customer', 'subjectId' => $customer->id])
            <div style="margin-top:12px;">
                @include('crm::partials.task-list', ['tasks' => $customer->tasks])
            </div>
        </section>

        <section>
            <h2 style="font-size:14px;font-weight:800;margin:0 0 8px;">Activity</h2>
            @include('crm::partials.activity-form', ['subjectType' => 'customer', 'subjectId' => $customer->id])
            <div style="margin-top:12px;">
                @include('crm::partials.activity-timeline', ['activities' => $customer->activities])
            </div>
        </section>
    </div>
</div>

<div style="margin-top:14px;">
    <a href="{{ route('crm.contacts.index') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> All contacts
    </a>
</div>
@endsection
