@php
    use Modules\CRM\Models\Project;
    $editing = isset($project) && $project instanceof Project;
@endphp

@if($errors->any())
    <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
@endif

<form method="POST"
      action="{{ $editing ? route('crm.projects.update', $project) : route('crm.projects.store') }}"
      class="pcat-form-grid">
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="pcat-field">
        <label for="pj-name">Project name <span style="color:#ef4444;">*</span></label>
        <input id="pj-name" type="text" name="name" maxlength="150" required
               value="{{ old('name', $editing ? $project->name : '') }}">
        @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="pj-desc">Description</label>
        <textarea id="pj-desc" name="description" maxlength="2000"
                  placeholder="What this project covers…">{{ old('description', $editing ? $project->description : '') }}</textarea>
        @error('description')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div style="display:flex;justify-content:flex-end;gap:8px;">
        @if($editing)
            <a href="{{ route('crm.projects.index') }}"
               class="linkbtn" style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                Cancel
            </a>
        @endif
        <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">
            {{ $editing ? 'Save changes' : 'Create project' }}
        </button>
    </div>
</form>
