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

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif

    {{-- Project team: tasks can only be assigned to these users --}}
    <div style="max-width:960px;border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
            <div>
                <div style="font-size:14px;font-weight:800;color:var(--text);"><i class="fa fa-users"></i> Project team <span style="font-size:12px;color:var(--muted);font-weight:600;">({{ $members->count() }})</span></div>
                <div style="font-size:12px;color:var(--muted);">Only team members can be assigned tasks in this project.</div>
            </div>
            <button type="button" class="linkbtn" id="pm-team-add-btn" style="padding:7px 14px;font-size:12px;"
                    @disabled($availableUsers->isEmpty()) @if($availableUsers->isEmpty()) title="All business users are already on this project" @endif>
                <i class="fa fa-user-plus"></i> Add Members
            </button>
        </div>

        @forelse($members as $m)
            <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-top:1px solid var(--border);">
                <div style="width:30px;height:30px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;">
                    {{ strtoupper(mb_substr($m['name'], 0, 1)) }}
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:13px;font-weight:700;color:var(--text);">{{ $m['name'] }} <span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted);margin-left:4px;">{{ $m['role'] }}</span></div>
                    <div style="font-size:11px;color:var(--muted);">{{ $m['email'] }} · {{ $m['open_tasks'] }} open / {{ $m['total_tasks'] }} tasks</div>
                </div>
                <form method="POST" action="{{ route('pm.projects.members.destroy', [$project, $m['id']]) }}"
                      onsubmit="return confirm(@js('Remove ' . $m['name'] . ' from this project?' . ($m['total_tasks'] ? "\nThey will be taken off their {$m['total_tasks']} task(s)." : '')))">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="linkbtn" style="padding:5px 10px;font-size:11px;background:transparent;border:1px solid #fca5a5;color:#dc2626;"><i class="fa fa-user-minus"></i> Remove</button>
                </form>
            </div>
        @empty
            <div style="font-size:12px;color:var(--muted);padding:8px 0;border-top:1px solid var(--border);">No team members yet — add users to assign them tasks.</div>
        @endforelse
    </div>

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

{{-- Add team members (multi-select with search) — same flow as the desktop app --}}
@if($availableUsers->isNotEmpty())
<style>
#pm-team-dialog{border:1px solid var(--border);border-radius:14px;padding:0;background:var(--card);color:var(--text);width:min(460px,calc(100vw - 32px));}
#pm-team-dialog::backdrop{background:rgba(0,0,0,.45);}
#pm-team-dialog .pm-td-head{display:flex;align-items:center;gap:8px;padding:14px 16px;border-bottom:1px solid var(--border);font-weight:800;}
#pm-team-dialog .pm-td-head button{margin-left:auto;background:none;border:none;color:var(--muted);cursor:pointer;font-size:16px;}
#pm-team-dialog .pm-td-body{padding:14px 16px;display:flex;flex-direction:column;gap:10px;}
#pm-team-dialog .pm-td-list{max-height:320px;overflow:auto;border:1px solid var(--border);border-radius:9px;}
#pm-team-dialog .pm-td-item{display:flex;align-items:center;gap:10px;padding:8px 12px;border-bottom:1px solid var(--border);cursor:pointer;}
#pm-team-dialog .pm-td-item:last-child{border-bottom:0;}
#pm-team-dialog .pm-td-role{font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted);border:1px solid var(--border);border-radius:999px;padding:2px 8px;}
#pm-team-dialog .pm-td-foot{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid var(--border);}
</style>
<dialog id="pm-team-dialog">
    <form method="POST" action="{{ route('pm.projects.members.store', $project) }}">
        @csrf
        <div class="pm-td-head">
            <i class="fa fa-user-plus" style="color:var(--primary);"></i>
            <span>Add Team Members</span>
            <button type="button" data-close title="Close"><i class="fa fa-xmark"></i></button>
        </div>
        <div class="pm-td-body">
            <div class="pcat-field"><input type="search" id="pm-td-search" placeholder="Search users…"></div>
            <div class="pm-td-list">
                @foreach($availableUsers as $u)
                    <label class="pm-td-item" data-q="{{ mb_strtolower($u['name'] . ' ' . $u['email']) }}">
                        <input type="checkbox" name="user_ids[]" value="{{ $u['id'] }}">
                        <span style="flex:1;min-width:0;">
                            <span style="display:block;font-size:13px;font-weight:700;">{{ $u['name'] }}</span>
                            <span style="display:block;font-size:11px;color:var(--muted);">{{ $u['email'] }}</span>
                        </span>
                        <span class="pm-td-role">{{ $u['role'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        <div class="pm-td-foot">
            <span id="pm-td-count" style="margin-right:auto;font-size:12px;color:var(--muted);"></span>
            <button type="button" class="linkbtn" data-close style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);">Cancel</button>
            <button type="submit" class="linkbtn" id="pm-td-save" style="padding:8px 16px;font-size:13px;" disabled><i class="fa fa-user-plus"></i> Add Selected</button>
        </div>
    </form>
</dialog>
<script>
(function () {
    var dialog = document.getElementById('pm-team-dialog');
    var search = document.getElementById('pm-td-search');
    var items  = Array.prototype.slice.call(dialog.querySelectorAll('.pm-td-item'));
    var save   = document.getElementById('pm-td-save');
    var count  = document.getElementById('pm-td-count');
    function updateCount() {
        var n = dialog.querySelectorAll('.pm-td-item input:checked').length;
        save.disabled = !n;
        count.textContent = n ? n + ' selected' : '';
    }
    document.getElementById('pm-team-add-btn').addEventListener('click', function () {
        dialog.querySelector('form').reset();
        search.value = '';
        items.forEach(function (l) { l.style.display = ''; });
        updateCount();
        dialog.showModal();
        search.focus();
    });
    search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        items.forEach(function (l) { l.style.display = !q || l.getAttribute('data-q').indexOf(q) !== -1 ? '' : 'none'; });
    });
    dialog.addEventListener('change', updateCount);
    dialog.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', function () { dialog.close(); }); });
    dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
})();
</script>
@endif
@endsection
