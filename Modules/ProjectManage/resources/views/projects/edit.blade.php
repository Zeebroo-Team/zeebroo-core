@extends('theme::layouts.app', ['title' => 'Edit — ' . $project->name, 'heading' => $project->name])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:680px;padding:14px;">
    <h2 style="margin:0 0 16px;font-size:16px;font-weight:800;color:var(--text);">Edit project</h2>

    @if($errors->any())
        <div class="pcat-banner pcat-banner--err">
            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('pm.projects.update', $project) }}">
        @csrf
        @method('PUT')

        <div class="pcat-form-grid pcat-form-grid--2" style="margin-bottom:12px;">
            <div class="pcat-field">
                <label for="pm-edit-name">Project name *</label>
                <input type="text" id="pm-edit-name" name="name"
                       value="{{ old('name', $project->name) }}" maxlength="150" required>
                @error('name')<p style="color:#f87171;font-size:11px;margin:4px 0 0;">{{ $message }}</p>@enderror
            </div>
            <div class="pcat-field">
                <label for="pm-edit-client">Client name</label>
                <input type="text" id="pm-edit-client" name="client_name"
                       value="{{ old('client_name', $project->client_name) }}" maxlength="120">
            </div>
        </div>

        <div class="pcat-field" style="margin-bottom:12px;">
            <label for="pm-edit-desc">Description</label>
            <textarea id="pm-edit-desc" name="description" style="min-height:90px;">{{ old('description', $project->description) }}</textarea>
        </div>

        <div class="pcat-form-grid pcat-form-grid--2" style="margin-bottom:12px;">
            <div class="pcat-field">
                <label for="pm-edit-priority">Priority</label>
                <select id="pm-edit-priority" name="priority">
                    <option value="low"    @selected(old('priority', $project->priority)==='low')>Low</option>
                    <option value="normal" @selected(old('priority', $project->priority)==='normal')>Normal</option>
                    <option value="high"   @selected(old('priority', $project->priority)==='high')>High</option>
                </select>
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
            <div class="pcat-field">
                <label for="pm-edit-color">Color (hex or name)</label>
                <input type="text" id="pm-edit-color" name="color" maxlength="20"
                       value="{{ old('color', $project->color) }}" placeholder="#3b82f6">
            </div>
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
@endsection
