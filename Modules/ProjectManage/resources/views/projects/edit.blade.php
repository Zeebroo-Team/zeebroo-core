@extends('theme::layouts.app', ['title' => 'Edit — ' . $project->name, 'heading' => $project->name])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('projectmanage::partials.pm-hub-nav')

    {{-- Breadcrumb --}}
    <div style="font-size:12px;color:var(--muted);margin-bottom:10px;">
        <a href="{{ route('pm.projects.show', $project) }}" class="pcat-link">{{ $project->name }}</a>
        <span style="margin:0 4px;">/</span>
        <span>Assignment</span>
    </div>

    @include('projectmanage::partials.pm-detail-nav')

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
        <h2 style="margin:0;font-size:16px;font-weight:800;color:var(--text);">Edit project</h2>
        <a href="{{ route('pm.projects.show', $project) }}" class="linkbtn"
           style="padding:6px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <i class="fa fa-arrow-left"></i> Back to project
        </a>
    </div>

    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">
            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('pm.projects.update', $project) }}" enctype="multipart/form-data" style="max-width:960px;">
        @csrf
        @method('PUT')

        <div class="pcat-field" style="margin-bottom:12px;">
            <label for="pm-edit-name">Project name *</label>
            <input type="text" id="pm-edit-name" name="name"
                   value="{{ old('name', $project->name) }}" maxlength="150" required>
            @error('name')<p style="color:#f87171;font-size:11px;margin:4px 0 0;">{{ $message }}</p>@enderror
        </div>

        @include('projectmanage::projects.partials.form-fields', ['project' => $project, 'assignableTargets' => $assignableTargets, 'customers' => $customers])

        <div class="pcat-form-grid pcat-form-grid--2" style="margin-bottom:12px;">
            <div class="pcat-field">
                <label for="pm-edit-client">Client name</label>
                <input type="text" id="pm-edit-client" name="client_name"
                       value="{{ old('client_name', $project->client_name) }}" maxlength="120">
            </div>
            <div class="pcat-field">
                <label for="pm-edit-status">Status</label>
                <select id="pm-edit-status" name="status">
                    <option value="active"    @selected(old('status', $project->status)==='active')>Active</option>
                    <option value="on_hold"   @selected(old('status', $project->status)==='on_hold')>On hold</option>
                    <option value="completed" @selected(old('status', $project->status)==='completed')>Completed</option>
                    <option value="archived"  @selected(old('status', $project->status)==='archived')>Archived</option>
                </select>
            </div>
        </div>

        <div class="pcat-form-grid pcat-form-grid--2" style="margin-bottom:12px;">
            <div class="pcat-field">
                <label for="pm-edit-start">Start date</label>
                <input type="date" id="pm-edit-start" name="start_date"
                       value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}">
            </div>
            <div class="pcat-field">
                <label for="pm-edit-due">Due date</label>
                <input type="date" id="pm-edit-due" name="due_date"
                       value="{{ old('due_date', $project->due_date?->format('Y-m-d')) }}">
            </div>
        </div>

        <div class="pcat-form-grid pcat-form-grid--2" style="margin-bottom:16px;">
            <div class="pcat-field">
                <label for="pm-edit-budget">Budget</label>
                <input type="number" id="pm-edit-budget" name="budget" step="0.01" min="0"
                       value="{{ old('budget', $project->budget) }}" placeholder="0.00">
            </div>
        </div>

        <div class="pcat-field" style="margin-bottom:16px;">
            <label for="pm-edit-desc">Description</label>
            <textarea id="pm-edit-desc" name="description" style="min-height:90px;">{{ old('description', $project->description) }}</textarea>
        </div>

        <div style="display:flex;gap:8px;align-items:center;">
            <button type="submit" class="linkbtn" style="padding:9px 20px;font-size:13px;">
                <i class="fa fa-check"></i> Save changes
            </button>
            <a href="{{ route('pm.projects.show', $project) }}" class="linkbtn"
               style="padding:9px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                Cancel
            </a>
        </div>
    </form>
</div>

<div style="margin-top:14px;">
    <a href="{{ route('pm.projects.show', $project) }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Back to project
    </a>
</div>
@endsection
