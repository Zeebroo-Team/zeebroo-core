@extends('theme::layouts.app', ['title' => 'Roles & Permissions', 'heading' => 'Roles & Permissions'])

@section('content')
<style>
.bur-wrap{max-width:100%;}
.bur-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;flex-wrap:wrap;}
.bur-title{margin:0;font-size:22px;font-weight:800;letter-spacing:-.025em;}
.bur-sub{margin:4px 0 0;font-size:13px;color:var(--muted);}
.bur-add-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:11px;
    border:1px solid color-mix(in srgb,var(--btn-bg) 55%,var(--border));background:var(--btn-bg);
    color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s ease;}
.bur-add-btn:hover{background:var(--btn-hover);color:#111827;}
.bur-card{border:1px solid var(--border);border-radius:16px;overflow:hidden;background:var(--card);}
.bur-table{width:100%;border-collapse:collapse;}
.bur-table th{padding:11px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);background:color-mix(in srgb,var(--card) 88%,var(--border));text-align:left;border-bottom:1px solid var(--border);}
.bur-table td{padding:13px 16px;font-size:13.5px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);vertical-align:middle;}
.bur-table tr:last-child td{border-bottom:none;}
.bur-table tr:hover td{background:color-mix(in srgb,var(--primary) 3%,transparent);}
.bur-role-cell{display:flex;align-items:center;gap:10px;}
.bur-swatch{width:14px;height:14px;border-radius:50%;flex-shrink:0;}
.bur-role-name{font-weight:650;color:var(--text);}
.bur-role-desc{font-size:12px;color:var(--muted);margin-top:1px;}
.bur-system-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;background:color-mix(in srgb,#f59e0b 13%,transparent);color:#d97706;margin-left:8px;}
.bur-act-btn{padding:5px 10px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;}
.bur-act-btn:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);}
.bur-act-btn--danger:hover{border-color:color-mix(in srgb,#ef4444 45%,var(--border));background:color-mix(in srgb,#ef4444 7%,transparent);color:#b91c1c;}
.bur-act-btn:disabled{opacity:.4;cursor:not-allowed;}
.bur-msg{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:12px;margin-bottom:20px;font-size:13px;font-weight:600;
    border:1px solid color-mix(in srgb,#16a34a 38%,var(--border));background:color-mix(in srgb,#16a34a 9%,var(--card));}
.bur-msg-err{border-color:color-mix(in srgb,#ef4444 38%,var(--border));background:color-mix(in srgb,#ef4444 9%,var(--card));color:#b91c1c;}
.bur-modal-overlay{position:fixed;inset:0;z-index:340;display:none;align-items:center;justify-content:center;padding:20px;box-sizing:border-box;}
.bur-modal-overlay.is-open{display:flex;}
.bur-modal-backdrop{position:absolute;inset:0;background:rgba(2,6,23,.55);backdrop-filter:blur(4px);cursor:pointer;}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .bur-modal-backdrop{background:rgba(17,24,39,.42);}
.bur-modal-shell{position:relative;z-index:1;width:100%;max-width:520px;border-radius:18px;border:1px solid var(--border);background:var(--card);box-shadow:0 24px 56px rgba(0,0,0,.28);display:flex;flex-direction:column;max-height:calc(100vh - 40px);}
.bur-modal-head{padding:20px 22px 16px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;justify-content:space-between;gap:12px;}
.bur-modal-title{margin:0 0 3px;font-size:18px;font-weight:800;letter-spacing:-.02em;}
.bur-modal-sub{margin:0;font-size:13px;color:var(--muted);}
.bur-modal-close{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;display:grid;place-items:center;font-size:18px;line-height:1;padding:0;flex-shrink:0;}
.bur-modal-close:hover{background:color-mix(in srgb,#ef4444 8%,transparent);border-color:color-mix(in srgb,#ef4444 35%,var(--border));}
.bur-modal-body{padding:20px 22px;overflow-y:auto;flex:1;min-height:0;}
.bur-modal-foot{padding:14px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;flex-shrink:0;}
.bur-field{margin-bottom:16px;}
.bur-field:last-child{margin-bottom:0;}
.bur-field label{display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:6px;}
.bur-field input,.bur-field textarea{width:100%;box-sizing:border-box;padding:10px 13px;border-radius:11px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);font-size:14px;font-family:inherit;}
.bur-field input:focus,.bur-field textarea:focus{outline:none;border-color:color-mix(in srgb,var(--primary) 55%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
.bur-field-hint{margin:5px 0 0;font-size:11.5px;color:var(--muted);}
.bur-color-row{display:flex;align-items:center;gap:10px;}
.bur-color-row input[type=color]{width:44px;height:38px;padding:2px;border-radius:9px;flex-shrink:0;}
.bur-perms-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:8px;}
.bur-perm-group-title{grid-column:1/-1;display:flex;align-items:center;gap:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin:10px 0 2px;}
.bur-perm-group-title:first-child{margin-top:0;}
.bur-perm-check{display:flex;align-items:center;gap:8px;padding:8px 11px;border-radius:10px;border:1px solid var(--border);cursor:pointer;transition:.15s ease;font-size:13px;font-weight:500;}
.bur-perm-check:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 5%,transparent);}
.bur-perm-check input[type=checkbox]{accent-color:var(--primary);width:14px;height:14px;flex-shrink:0;}
.bur-perms-note{font-size:12px;color:var(--muted);margin:6px 0 0;font-style:italic;}
.bur-cancel-btn{padding:9px 18px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;}
.bur-cancel-btn:hover{border-color:var(--primary);}
</style>

@php
    $permissionLabels = collect($permissions)->flatMap(fn ($group) => collect($group['items'] ?? [])->pluck('label', 'key'))->all();
@endphp

<div class="bur-wrap">
    @if(session('status'))
        <div class="bur-msg">
            <i class="fa fa-circle-check" style="color:#22c55e;font-size:15px;"></i>
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bur-msg bur-msg-err">
            <i class="fa fa-circle-exclamation" style="font-size:15px;"></i>
            {{ $errors->first() }}
        </div>
    @endif

    <div class="bur-header">
        <div>
            <h1 class="bur-title"><i class="fa fa-user-shield" style="color:var(--primary);margin-right:8px;"></i>Roles &amp; Permissions</h1>
            <p class="bur-sub">Define custom roles and permission sets for <strong>{{ $business->name }}</strong></p>
        </div>
        <button type="button" class="bur-add-btn" id="burOpenAdd">
            <i class="fa fa-plus"></i> Add Role
        </button>
    </div>

    <div class="bur-card">
        <table class="bur-table">
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Members</th>
                    <th>Permissions</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)
                    <tr>
                        <td>
                            <div class="bur-role-cell">
                                <span class="bur-swatch" style="background:{{ $role->color }};"></span>
                                <div>
                                    <div class="bur-role-name">
                                        {{ $role->name }}
                                        @if($role->is_system)
                                            <span class="bur-system-badge"><i class="fa fa-lock"></i> System</span>
                                        @endif
                                    </div>
                                    @if($role->description)
                                        <div class="bur-role-desc">{{ $role->description }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>{{ $memberCounts[$role->slug] ?? 0 }}</td>
                        <td>
                            @if($role->permissions === null)
                                <span class="muted" style="font-size:12px;">Full access</span>
                            @else
                                <span class="muted" style="font-size:12px;">{{ count($role->permissions) }} permission(s)</span>
                            @endif
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;justify-content:flex-end;">
                                <button type="button" class="bur-act-btn bur-edit-btn"
                                    data-role-id="{{ $role->id }}"
                                    data-role-name="{{ $role->name }}"
                                    data-role-color="{{ $role->color }}"
                                    data-role-description="{{ $role->description }}"
                                    data-role-system="{{ $role->is_system ? '1' : '0' }}"
                                    data-role-perms="{{ json_encode($role->permissions) }}">
                                    <i class="fa fa-pen"></i> Edit
                                </button>
                                @if(!$role->is_system)
                                    <form method="POST" action="{{ route('business.roles.destroy', $role) }}" onsubmit="return confirm('Delete role {{ $role->name }}? Members using it will be moved to Staff.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bur-act-btn bur-act-btn--danger">
                                            <i class="fa fa-trash"></i> Delete
                                        </button>
                                    </form>
                                @else
                                    <button type="button" class="bur-act-btn" disabled title="System roles cannot be deleted"><i class="fa fa-trash"></i> Delete</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <div style="padding:48px 24px;text-align:center;">
                                <p class="muted">No roles yet.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Add / Edit modal --}}
<div id="burModal" class="bur-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="burModalTitle">
    <div class="bur-modal-backdrop" id="burModalBackdrop"></div>
    <div class="bur-modal-shell">
        <div class="bur-modal-head">
            <div>
                <h2 class="bur-modal-title" id="burModalTitle">Add Role</h2>
                <p class="bur-modal-sub" id="burModalSub">Create a new custom role</p>
            </div>
            <button type="button" class="bur-modal-close" id="burModalClose" aria-label="Close">&times;</button>
        </div>
        <form id="burForm" method="POST" action="{{ route('business.roles.store') }}">
            @csrf
            <input type="hidden" name="_method" id="burFormMethod" value="POST">
            <input type="hidden" name="permissions_editable" id="burPermsEditableFlag" value="1">
            <div class="bur-modal-body">
                <div class="bur-field" id="burNameField">
                    <label for="burName">Role name</label>
                    <input type="text" id="burName" name="name" placeholder="e.g. Cashier Supervisor" value="{{ old('name') }}">
                    <p class="bur-field-hint" id="burNameHint">System role names cannot be changed.</p>
                    @error('name')
                        <p class="bur-field-err" style="color:#ef4444;font-size:12px;margin-top:5px;">{{ $message }}</p>
                    @enderror
                </div>

                <div class="bur-field">
                    <label for="burColor">Color</label>
                    <div class="bur-color-row">
                        <input type="color" id="burColor" name="color" value="#64748b">
                    </div>
                </div>

                <div class="bur-field">
                    <label for="burDescription">Description</label>
                    <textarea id="burDescription" name="description" rows="2" placeholder="What is this role for?"></textarea>
                </div>

                <div class="bur-field" id="burPermsField">
                    <label>Module permissions</label>
                    <p class="bur-perms-note" id="burAllAccessNote" style="display:none;"><i class="fa fa-circle-info"></i> This role has full access to all modules.</p>
                    <div class="bur-perms-grid" id="burPermsGrid">
                        @foreach($permissions as $group)
                            <div class="bur-perm-group-title">
                                <i class="fa {{ $group['icon'] ?? 'fa-shapes' }}"></i> {{ $group['label'] }}
                            </div>
                            @foreach(($group['items'] ?? []) as $item)
                                <label class="bur-perm-check">
                                    <input type="checkbox" name="permissions[]" value="{{ $item['key'] }}">
                                    {{ $item['label'] }}
                                </label>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="bur-modal-foot">
                <button type="button" class="bur-cancel-btn" id="burModalCancel">Cancel</button>
                <button type="submit" class="bur-add-btn" id="burSubmitBtn">
                    <i class="fa fa-plus"></i> <span id="burSubmitLabel">Add Role</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal    = document.getElementById('burModal');
    var backdrop = document.getElementById('burModalBackdrop');
    var closeBtn = document.getElementById('burModalClose');
    var cancelBtn= document.getElementById('burModalCancel');
    var openBtn  = document.getElementById('burOpenAdd');
    var form     = document.getElementById('burForm');
    var methodEl = document.getElementById('burFormMethod');
    var title    = document.getElementById('burModalTitle');
    var sub      = document.getElementById('burModalSub');
    var submitLabel = document.getElementById('burSubmitLabel');
    var submitIcon  = document.getElementById('burSubmitBtn').querySelector('i');
    var nameEl   = document.getElementById('burName');
    var nameHint = document.getElementById('burNameHint');
    var colorEl  = document.getElementById('burColor');
    var descEl   = document.getElementById('burDescription');
    var grid     = document.getElementById('burPermsGrid');
    var note     = document.getElementById('burAllAccessNote');
    var editableFlag = document.getElementById('burPermsEditableFlag');

    function openModal() {
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    function setPermsLocked(locked) {
        grid.style.opacity = locked ? '0.4' : '1';
        grid.style.pointerEvents = locked ? 'none' : '';
        note.style.display = locked ? 'block' : 'none';
        editableFlag.value = locked ? '0' : '1';
        grid.querySelectorAll('input[name="permissions[]"]').forEach(function (cb) {
            cb.disabled = locked;
        });
    }

    function setAddMode() {
        methodEl.value = 'POST';
        form.action = '{{ route('business.roles.store') }}';
        title.textContent = 'Add Role';
        sub.textContent = 'Create a new custom role for {{ addslashes($business->name) }}';
        submitLabel.textContent = 'Add Role';
        submitIcon.className = 'fa fa-plus';
        nameEl.value = '';
        nameEl.disabled = false;
        nameHint.style.display = 'none';
        colorEl.value = '#64748b';
        descEl.value = '';
        form.querySelectorAll('input[name="permissions[]"]').forEach(function (cb) { cb.checked = false; });
        setPermsLocked(false);
    }

    openBtn.addEventListener('click', function () {
        setAddMode();
        openModal();
    });
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    document.querySelectorAll('.bur-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var roleId = btn.getAttribute('data-role-id');
            var isSystem = btn.getAttribute('data-role-system') === '1';
            var perms = JSON.parse(btn.getAttribute('data-role-perms') || 'null');

            methodEl.value = 'PUT';
            form.action = '/business/roles/' + roleId;
            title.textContent = 'Edit Role';
            sub.textContent = 'Update ' + btn.getAttribute('data-role-name');
            submitLabel.textContent = 'Save Changes';
            submitIcon.className = 'fa fa-floppy-disk';

            nameEl.value = btn.getAttribute('data-role-name');
            nameEl.disabled = isSystem;
            nameHint.style.display = isSystem ? 'block' : 'none';
            colorEl.value = btn.getAttribute('data-role-color') || '#64748b';
            descEl.value = btn.getAttribute('data-role-description') || '';

            var fullAccess = perms === null;
            form.querySelectorAll('input[name="permissions[]"]').forEach(function (cb) {
                cb.checked = fullAccess ? true : (perms || []).indexOf(cb.value) !== -1;
            });
            setPermsLocked(fullAccess);

            openModal();
        });
    });
})();
</script>
@endsection
