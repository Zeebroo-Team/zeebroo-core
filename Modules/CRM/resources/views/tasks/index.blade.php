@extends('theme::layouts.app', ['title' => 'Tasks', 'heading' => 'Tasks'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.crm-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Follow-ups across every lead and contact for <strong style="color:var(--text);">{{ $business->name }}</strong>.
    </p>

    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
        @foreach($filterTabs as $key => $label)
            <a href="{{ route('crm.tasks.index', ['filter' => $key]) }}"
               style="padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid var(--border);
                      {{ ($filter ?? 'open') === $key ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : 'background:transparent;color:var(--muted);' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <section class="pcat-inline" style="margin-bottom:16px;">
        <h2>New task</h2>
        <p class="pcat-muted">General follow-up not tied to a lead or contact. Use a lead or contact page to attach one there.</p>
        @include('crm::partials.task-form')
    </section>

    @if(!$hasTasks)
        <p class="muted" style="margin:24px 0;font-size:13px;">No tasks yet.</p>
    @elseif($tasks->isEmpty())
        <p class="muted" style="margin:24px 0;font-size:13px;">Nothing here for this filter.</p>
    @else
        @include('crm::partials.task-list', ['tasks' => $tasks, 'showSubject' => true])
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('dashboard') }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Overview
    </a>
</div>
@endsection
