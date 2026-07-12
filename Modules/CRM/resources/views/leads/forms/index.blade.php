@extends('theme::layouts.app', ['title' => 'Public forms', 'heading' => $project->name])

@php $hasForms = $forms->isNotEmpty(); @endphp

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.project-nav', ['project' => $project])

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">{{ $errors->first() }}</div>
    @endif

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Build a public web page for <strong style="color:var(--text);">{{ $project->name }}</strong> that visitors can fill out — submissions become leads here automatically. Each project has a single lead form.
    </p>

    <div class="pcat-toolbar">
        <span class="muted" style="margin:0;font-size:13px;">
            @if(!$hasForms) Create your <strong style="color:var(--text);">first form</strong> below. @endif
        </span>
    </div>

    @if(!$hasForms)
        <section class="pcat-inline">
            <h2>New form</h2>
            <p class="pcat-muted">e.g. "Contact us" or "Get a quote" — you'll design it with the drag-and-drop builder next.</p>
            <form method="POST" action="{{ route('crm.projects.forms.store', $project) }}" class="pcat-form-grid" style="margin-top:14px;">
                @csrf
                <div class="pcat-field">
                    <label for="lf-name">Form name</label>
                    <input id="lf-name" name="name" maxlength="150" required placeholder="e.g. Contact us" autofocus>
                    @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                </div>
                @include('crm::leads.forms.partials.template-picker', ['templates' => $templates])
                <div style="display:flex;justify-content:flex-end;">
                    <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Create &amp; open builder</button>
                </div>
            </form>
        </section>
    @else
        <div class="pcat-table-wrap">
            <table class="pcat-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Public link</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($forms as $f)
                        <tr>
                            <td><strong style="color:var(--text);">{{ $f->name }}</strong></td>
                            <td>
                                @if($f->is_published)
                                    <span class="pcat-badge pcat-badge--on">Published</span>
                                @else
                                    <span class="pcat-badge pcat-badge--off">Draft</span>
                                @endif
                            </td>
                            <td>
                                @if($f->is_published)
                                    <a href="{{ $f->publicUrl() }}" target="_blank" rel="noopener" class="pcat-link">{{ $f->publicUrl() }}</a>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <a href="{{ route('crm.projects.forms.builder', [$project, $f]) }}" class="pcat-link">
                                    <i class="fa fa-pen"></i> Edit
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
    <a href="{{ route('crm.projects.leads.index', $project) }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Leads
    </a>
</div>

@endsection
