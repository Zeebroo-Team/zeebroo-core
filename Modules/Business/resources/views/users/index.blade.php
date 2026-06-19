@extends('theme::layouts.app', ['title' => 'User Management', 'heading' => 'User Management'])

@section('content')
<style>
.bum-wrap{max-width:900px;margin:0 auto;}
.bum-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;flex-wrap:wrap;}
.bum-title{margin:0;font-size:22px;font-weight:800;letter-spacing:-.025em;}
.bum-sub{margin:4px 0 0;font-size:13px;color:var(--muted);}
.bum-add-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:11px;
    border:1px solid color-mix(in srgb,var(--btn-bg) 55%,var(--border));background:var(--btn-bg);
    color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s ease;}
.bum-add-btn:hover{background:var(--btn-hover);color:#111827;}
/* Members table */
.bum-card{border:1px solid var(--border);border-radius:16px;overflow:hidden;background:var(--card);}
.bum-table{width:100%;border-collapse:collapse;}
.bum-table th{padding:11px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);background:color-mix(in srgb,var(--card) 88%,var(--border));text-align:left;border-bottom:1px solid var(--border);}
.bum-table td{padding:13px 16px;font-size:13.5px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);vertical-align:middle;}
.bum-table tr:last-child td{border-bottom:none;}
.bum-table tr:hover td{background:color-mix(in srgb,var(--primary) 3%,transparent);}
.bum-avatar{width:36px;height:36px;border-radius:50%;background:color-mix(in srgb,var(--primary) 14%,transparent);
    display:grid;place-items:center;font-size:14px;font-weight:700;color:var(--primary);flex-shrink:0;}
.bum-user-cell{display:flex;align-items:center;gap:10px;}
.bum-user-name{font-weight:650;color:var(--text);}
.bum-user-email{font-size:12px;color:var(--muted);margin-top:1px;}
/* Role badge */
.bum-role{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;}
.bum-role--admin{background:color-mix(in srgb,#6366f1 13%,transparent);color:#6366f1;}
.bum-role--manager{background:color-mix(in srgb,#0ea5e9 13%,transparent);color:#0ea5e9;}
.bum-role--staff{background:color-mix(in srgb,#64748b 13%,transparent);color:#64748b;}
.bum-role--owner{background:color-mix(in srgb,#f59e0b 13%,transparent);color:#d97706;}
/* Actions */
.bum-act-btn{padding:5px 10px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;transition:.15s ease;}
.bum-act-btn:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);}
.bum-act-btn--danger:hover{border-color:color-mix(in srgb,#ef4444 45%,var(--border));background:color-mix(in srgb,#ef4444 7%,transparent);color:#b91c1c;}
/* Empty state */
.bum-empty{padding:48px 24px;text-align:center;}
.bum-empty-icon{width:52px;height:52px;border-radius:14px;margin:0 auto 14px;display:grid;place-items:center;font-size:22px;background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);}
.bum-empty-title{margin:0 0 6px;font-size:16px;font-weight:700;}
.bum-empty-sub{margin:0;font-size:13px;color:var(--muted);}
/* Owner row */
.bum-owner-row td{background:color-mix(in srgb,#f59e0b 4%,transparent);}
/* Status message */
.bum-msg{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:12px;margin-bottom:20px;font-size:13px;font-weight:600;
    border:1px solid color-mix(in srgb,#16a34a 38%,var(--border));background:color-mix(in srgb,#16a34a 9%,var(--card));}
.bum-msg-err{border-color:color-mix(in srgb,#ef4444 38%,var(--border));background:color-mix(in srgb,#ef4444 9%,var(--card));color:#b91c1c;}
/* Permissions pills */
.bum-perms{display:flex;flex-wrap:wrap;gap:4px;}
.bum-perm-pill{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:650;
    background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);}
/* Invite modal */
.bum-modal-overlay{position:fixed;inset:0;z-index:340;display:none;align-items:center;justify-content:center;padding:20px;box-sizing:border-box;}
.bum-modal-overlay.is-open{display:flex;}
.bum-modal-backdrop{position:absolute;inset:0;background:rgba(2,6,23,.55);backdrop-filter:blur(4px);cursor:pointer;}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .bum-modal-backdrop{background:rgba(17,24,39,.42);}
.bum-modal-shell{position:relative;z-index:1;width:100%;max-width:520px;border-radius:18px;border:1px solid var(--border);background:var(--card);box-shadow:0 24px 56px rgba(0,0,0,.28);display:flex;flex-direction:column;max-height:calc(100vh - 40px);}
.bum-modal-head{padding:20px 22px 16px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;justify-content:space-between;gap:12px;}
.bum-modal-title{margin:0 0 3px;font-size:18px;font-weight:800;letter-spacing:-.02em;}
.bum-modal-sub{margin:0;font-size:13px;color:var(--muted);}
.bum-modal-close{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;display:grid;place-items:center;font-size:18px;flex-shrink:0;}
.bum-modal-close:hover{background:color-mix(in srgb,#ef4444 8%,transparent);border-color:color-mix(in srgb,#ef4444 35%,var(--border));}
.bum-modal-body{padding:20px 22px;overflow-y:auto;flex:1;min-height:0;}
.bum-modal-foot{padding:14px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;flex-shrink:0;}
/* Form fields */
.bum-field{margin-bottom:16px;}
.bum-field:last-child{margin-bottom:0;}
.bum-field label{display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:6px;}
.bum-field input,.bum-field select{width:100%;box-sizing:border-box;padding:10px 13px;border-radius:11px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);font-size:14px;font-family:inherit;}
.bum-field input:focus,.bum-field select:focus{outline:none;border-color:color-mix(in srgb,var(--primary) 55%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
.bum-field-hint{margin:5px 0 0;font-size:11.5px;color:var(--muted);}
.bum-field-err{margin:5px 0 0;font-size:12px;font-weight:600;color:#ef4444;}
/* Permissions grid */
.bum-perms-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:8px;}
.bum-perm-check{display:flex;align-items:center;gap:8px;padding:8px 11px;border-radius:10px;border:1px solid var(--border);cursor:pointer;transition:.15s ease;font-size:13px;font-weight:500;}
.bum-perm-check:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 5%,transparent);}
.bum-perm-check input[type=checkbox]{accent-color:var(--primary);width:14px;height:14px;flex-shrink:0;}
.bum-perms-note{font-size:12px;color:var(--muted);margin:6px 0 0;font-style:italic;}
/* Cancel btn */
.bum-cancel-btn{padding:9px 18px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;}
.bum-cancel-btn:hover{border-color:var(--primary);}
</style>

<div class="bum-wrap">
    @if(session('status'))
        <div class="bum-msg">
            <i class="fa fa-circle-check" style="color:#22c55e;font-size:15px;"></i>
            {{ session('status') }}
        </div>
    @endif

    @if($errors->has('email'))
        <div class="bum-msg bum-msg-err">
            <i class="fa fa-circle-exclamation" style="font-size:15px;"></i>
            {{ $errors->first('email') }}
        </div>
    @endif

    <div class="bum-header">
        <div>
            <h1 class="bum-title"><i class="fa fa-users" style="color:var(--primary);margin-right:8px;"></i>User Management</h1>
            <p class="bum-sub">Manage who has access to <strong>{{ $business->name }}</strong></p>
        </div>
        <button type="button" class="bum-add-btn" id="bumOpenInvite">
            <i class="fa fa-user-plus"></i> Add Member
        </button>
    </div>

    <div class="bum-card">
        <table class="bum-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Permissions</th>
                    <th>Added</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                {{-- Owner row --}}
                <tr class="bum-owner-row">
                    <td>
                        <div class="bum-user-cell">
                            <div class="bum-avatar">{{ strtoupper(substr($business->user->name ?? 'O', 0, 1)) }}</div>
                            <div>
                                <div class="bum-user-name">{{ $business->user->name ?? '—' }}</div>
                                <div class="bum-user-email">{{ $business->user->email ?? '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td><span class="bum-role bum-role--owner"><i class="fa fa-crown"></i> Owner</span></td>
                    <td><span class="muted" style="font-size:12px;">Full access</span></td>
                    <td style="font-size:12px;color:var(--muted);">{{ $business->created_at?->format('d M Y') }}</td>
                    <td></td>
                </tr>

                @forelse($members as $member)
                    <tr>
                        <td>
                            <div class="bum-user-cell">
                                <div class="bum-avatar">{{ strtoupper(substr($member->user?->name ?? '?', 0, 1)) }}</div>
                                <div>
                                    <div class="bum-user-name">{{ $member->user?->name ?? 'Unknown' }}</div>
                                    <div class="bum-user-email">{{ $member->user?->email ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="bum-role bum-role--{{ $member->role }}">
                                {{ $member->roleLabel() }}
                            </span>
                        </td>
                        <td>
                            @if($member->role === 'admin')
                                <span class="muted" style="font-size:12px;">Full access</span>
                            @elseif(!empty($member->permissions))
                                <div class="bum-perms">
                                    @foreach($member->permissions as $perm)
                                        <span class="bum-perm-pill">{{ $permissions[$perm] ?? $perm }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span class="muted" style="font-size:12px;">No permissions</span>
                            @endif
                        </td>
                        <td style="font-size:12px;color:var(--muted);">{{ $member->created_at?->format('d M Y') }}</td>
                        <td>
                            <div style="display:flex;gap:6px;justify-content:flex-end;">
                                <button type="button" class="bum-act-btn bum-edit-btn"
                                    data-member-id="{{ $member->id }}"
                                    data-member-name="{{ $member->user?->name }}"
                                    data-member-role="{{ $member->role }}"
                                    data-member-perms="{{ json_encode($member->permissions ?? []) }}">
                                    <i class="fa fa-pen"></i> Edit
                                </button>
                                <form method="POST" action="{{ route('business.users.destroy', $member) }}" onsubmit="return confirm('Remove {{ $member->user?->name }} from this business?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="bum-act-btn bum-act-btn--danger">
                                        <i class="fa fa-trash"></i> Remove
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                @endforelse

                @if($members->isEmpty())
                    <tr>
                        <td colspan="5">
                            <div class="bum-empty">
                                <div class="bum-empty-icon"><i class="fa fa-users"></i></div>
                                <p class="bum-empty-title">No members yet</p>
                                <p class="bum-empty-sub">Add team members to let them access this business.</p>
                            </div>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

{{-- Invite / Edit modal --}}
<div id="bumModal" class="bum-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="bumModalTitle">
    <div class="bum-modal-backdrop" id="bumModalBackdrop"></div>
    <div class="bum-modal-shell">
        <div class="bum-modal-head">
            <div>
                <h2 class="bum-modal-title" id="bumModalTitle">Add Member</h2>
                <p class="bum-modal-sub" id="bumModalSub">Invite a user to access this business</p>
            </div>
            <button type="button" class="bum-modal-close" id="bumModalClose" aria-label="Close">&times;</button>
        </div>
        <form id="bumForm" method="POST" action="{{ route('business.users.store') }}">
            @csrf
            <input type="hidden" name="_method" id="bumFormMethod" value="POST">
            <input type="hidden" name="_member_id" id="bumMemberId" value="">
            <div class="bum-modal-body">
                <div class="bum-field" id="bumEmailField">
                    <label for="bumEmail">User email</label>
                    <input type="email" id="bumEmail" name="email" placeholder="user@example.com" value="{{ old('email') }}" autocomplete="off">
                    <p class="bum-field-hint">The user must already have an account on this platform.</p>
                    @error('email')
                        <p class="bum-field-err">{{ $message }}</p>
                    @enderror
                </div>

                <div class="bum-field">
                    <label for="bumRole">Role</label>
                    <select id="bumRole" name="role" onchange="bumRoleChange(this.value)">
                        <option value="admin">Admin — full access, can manage members</option>
                        <option value="manager" selected>Manager — operational access</option>
                        <option value="staff">Staff — limited access by permissions</option>
                    </select>
                </div>

                <div class="bum-field" id="bumPermsField">
                    <label>Module permissions</label>
                    <p class="bum-perms-note" id="bumAdminNote" style="display:none;"><i class="fa fa-circle-info"></i> Admins have access to all modules automatically.</p>
                    <div class="bum-perms-grid" id="bumPermsGrid">
                        @foreach($permissions as $key => $label)
                            <label class="bum-perm-check">
                                <input type="checkbox" name="permissions[]" value="{{ $key }}" checked>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="bum-modal-foot">
                <button type="button" class="bum-cancel-btn" id="bumModalCancel">Cancel</button>
                <button type="submit" class="bum-add-btn" id="bumSubmitBtn">
                    <i class="fa fa-user-plus"></i> <span id="bumSubmitLabel">Add Member</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal    = document.getElementById('bumModal');
    var backdrop = document.getElementById('bumModalBackdrop');
    var closeBtn = document.getElementById('bumModalClose');
    var cancelBtn= document.getElementById('bumModalCancel');
    var openBtn  = document.getElementById('bumOpenInvite');
    var form     = document.getElementById('bumForm');
    var methodEl = document.getElementById('bumFormMethod');
    var memberIdEl = document.getElementById('bumMemberId');
    var emailField = document.getElementById('bumEmailField');
    var title    = document.getElementById('bumModalTitle');
    var sub      = document.getElementById('bumModalSub');
    var submitLabel = document.getElementById('bumSubmitLabel');
    var submitIcon  = document.getElementById('bumSubmitBtn').querySelector('i');
    var roleEl   = document.getElementById('bumRole');

    function openModal() {
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    function setInviteMode() {
        methodEl.value = 'POST';
        memberIdEl.value = '';
        form.action = '{{ route('business.users.store') }}';
        emailField.style.display = 'block';
        title.textContent = 'Add Member';
        sub.textContent   = 'Invite a user to access {{ addslashes($business->name) }}';
        submitLabel.textContent = 'Add Member';
        submitIcon.className = 'fa fa-user-plus';
        // reset fields
        document.getElementById('bumEmail').value = '';
        roleEl.value = 'manager';
        bumRoleChange('manager');
        // check all permissions
        form.querySelectorAll('input[name="permissions[]"]').forEach(function (cb) { cb.checked = true; });
    }

    openBtn.addEventListener('click', function () {
        setInviteMode();
        openModal();
    });
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    // Edit buttons
    document.querySelectorAll('.bum-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var memberId = btn.getAttribute('data-member-id');
            var role     = btn.getAttribute('data-member-role');
            var perms    = JSON.parse(btn.getAttribute('data-member-perms') || '[]');
            var name     = btn.getAttribute('data-member-name');

            methodEl.value = 'PUT';
            memberIdEl.value = memberId;
            form.action = '/business/users/' + memberId;
            emailField.style.display = 'none';
            title.textContent = 'Edit Member';
            sub.textContent   = 'Update role and permissions for ' + name;
            submitLabel.textContent = 'Save Changes';
            submitIcon.className = 'fa fa-floppy-disk';

            roleEl.value = role;
            bumRoleChange(role);

            form.querySelectorAll('input[name="permissions[]"]').forEach(function (cb) {
                cb.checked = perms.indexOf(cb.value) !== -1;
            });

            openModal();
        });
    });
})();

window.bumRoleChange = function (role) {
    var grid     = document.getElementById('bumPermsGrid');
    var note     = document.getElementById('bumAdminNote');
    if (role === 'admin') {
        grid.style.opacity = '0.4';
        grid.style.pointerEvents = 'none';
        note.style.display = 'block';
    } else {
        grid.style.opacity = '1';
        grid.style.pointerEvents = '';
        note.style.display = 'none';
    }
};
</script>
@endsection
