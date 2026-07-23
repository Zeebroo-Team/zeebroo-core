@extends('theme::layouts.app', ['title' => 'User Accounts', 'heading' => 'User Accounts'])

@section('content')
<style>
.adu-wrap{max-width:1000px;margin:0 auto;}
.adu-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;flex-wrap:wrap;}
.adu-title{margin:0;font-size:22px;font-weight:800;letter-spacing:-.025em;}
.adu-sub{margin:4px 0 0;font-size:13px;color:var(--muted);}
.adu-add-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:11px;
    border:1px solid color-mix(in srgb,var(--btn-bg) 55%,var(--border));background:var(--btn-bg);
    color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s ease;}
.adu-add-btn:hover{background:var(--btn-hover);color:#111827;}
.adu-card{border:1px solid var(--border);border-radius:16px;overflow:hidden;background:var(--card);}
.adu-table{width:100%;border-collapse:collapse;}
.adu-table th{padding:11px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);background:color-mix(in srgb,var(--card) 88%,var(--border));text-align:left;border-bottom:1px solid var(--border);}
.adu-table td{padding:13px 16px;font-size:13.5px;border-bottom:1px solid color-mix(in srgb,var(--border) 60%,transparent);vertical-align:middle;}
.adu-table tr:last-child td{border-bottom:none;}
.adu-table tr:hover td{background:color-mix(in srgb,var(--primary) 3%,transparent);}
.adu-avatar{width:36px;height:36px;border-radius:50%;background:color-mix(in srgb,var(--primary) 14%,transparent);
    display:grid;place-items:center;font-size:14px;font-weight:700;color:var(--primary);flex-shrink:0;}
.adu-user-cell{display:flex;align-items:center;gap:10px;}
.adu-user-name{font-weight:650;color:var(--text);}
.adu-user-email{font-size:12px;color:var(--muted);margin-top:1px;}
.adu-role{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;}
.adu-role--admin{background:color-mix(in srgb,#6366f1 13%,transparent);color:#6366f1;}
.adu-role--user{background:color-mix(in srgb,#64748b 13%,transparent);color:#64748b;}
.adu-owns{font-size:12px;color:var(--muted);}
.adu-act-btn{padding:5px 10px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;}
.adu-act-btn:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 7%,transparent);}
.adu-act-btn--danger:hover{border-color:color-mix(in srgb,#ef4444 45%,var(--border));background:color-mix(in srgb,#ef4444 7%,transparent);color:#b91c1c;}
.adu-protected{font-size:11px;color:var(--muted);font-style:italic;}
.adu-empty{padding:48px 24px;text-align:center;}
.adu-empty-icon{width:52px;height:52px;border-radius:14px;margin:0 auto 14px;display:grid;place-items:center;font-size:22px;background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);}
.adu-empty-title{margin:0 0 6px;font-size:16px;font-weight:700;}
.adu-empty-sub{margin:0;font-size:13px;color:var(--muted);}
.adu-msg{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:12px;margin-bottom:20px;font-size:13px;font-weight:600;
    border:1px solid color-mix(in srgb,#16a34a 38%,var(--border));background:color-mix(in srgb,#16a34a 9%,var(--card));}
.adu-msg-err{border-color:color-mix(in srgb,#ef4444 38%,var(--border));background:color-mix(in srgb,#ef4444 9%,var(--card));color:#b91c1c;}
.adu-modal-overlay{position:fixed;inset:0;z-index:340;display:none;align-items:center;justify-content:center;padding:20px;box-sizing:border-box;}
.adu-modal-overlay.is-open{display:flex;}
.adu-modal-backdrop{position:absolute;inset:0;background:rgba(2,6,23,.55);backdrop-filter:blur(4px);cursor:pointer;}
:is(html[data-theme="light"],html[data-theme="light_blue"]) .adu-modal-backdrop{background:rgba(17,24,39,.42);}
.adu-modal-shell{position:relative;z-index:1;width:100%;max-width:460px;border-radius:18px;border:1px solid var(--border);background:var(--card);box-shadow:0 24px 56px rgba(0,0,0,.28);display:flex;flex-direction:column;max-height:calc(100vh - 40px);}
.adu-modal-head{padding:20px 22px 16px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;justify-content:space-between;gap:12px;}
.adu-modal-title{margin:0 0 3px;font-size:18px;font-weight:800;letter-spacing:-.02em;}
.adu-modal-sub{margin:0;font-size:13px;color:var(--muted);}
.adu-modal-close{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;display:grid;place-items:center;font-size:18px;flex-shrink:0;}
.adu-modal-close:hover{background:color-mix(in srgb,#ef4444 8%,transparent);border-color:color-mix(in srgb,#ef4444 35%,var(--border));}
.adu-modal-body{padding:20px 22px;overflow-y:auto;flex:1;min-height:0;}
.adu-modal-foot{padding:14px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;flex-shrink:0;}
.adu-field{margin-bottom:16px;}
.adu-field:last-child{margin-bottom:0;}
.adu-field label{display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:6px;}
.adu-field input,.adu-field select{width:100%;box-sizing:border-box;padding:10px 13px;border-radius:11px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 94%,transparent);color:var(--text);font-size:14px;font-family:inherit;}
.adu-field input:focus,.adu-field select:focus{outline:none;border-color:color-mix(in srgb,var(--primary) 55%,var(--border));box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 14%,transparent);}
.adu-field-hint{margin:5px 0 0;font-size:11.5px;color:var(--muted);}
.adu-field-err{margin:5px 0 0;font-size:12px;font-weight:600;color:#ef4444;}
.adu-cancel-btn{padding:9px 18px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;}
.adu-cancel-btn:hover{border-color:var(--primary);}
</style>

<div class="adu-wrap">
    @if(session('status'))
        <div class="adu-msg"><i class="fa fa-circle-check" style="color:#22c55e;font-size:15px;"></i> {{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="adu-msg adu-msg-err"><i class="fa fa-circle-exclamation" style="font-size:15px;"></i> {{ $errors->first() }}</div>
    @endif

    <div class="adu-header">
        <div>
            <h1 class="adu-title"><i class="fa fa-users-gear" style="color:var(--primary);margin-right:8px;"></i>User Accounts</h1>
            <p class="adu-sub">Every login on the platform — create accounts, change roles, or remove access.</p>
        </div>
        <button type="button" class="adu-add-btn" id="aduOpenCreate"><i class="fa fa-user-plus"></i> Add User</button>
    </div>

    <div class="adu-card">
        <table class="adu-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Owns</th>
                    <th>Joined</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                    @php
                        $roleName = $u->roles->first()->name ?? 'user';
                        $isSelf = (int) $u->id === (int) auth()->id();
                        $deletable = ! $isSelf && (int) $u->businesses_count === 0 && (int) $u->accounts_count === 0
                            && ! ($roleName === 'admin' && \App\Models\User::role('admin')->count() <= 1);
                    @endphp
                    <tr>
                        <td>
                            <div class="adu-user-cell">
                                <div class="adu-avatar">{{ strtoupper(substr($u->name ?: '?', 0, 1)) }}</div>
                                <div>
                                    <div class="adu-user-name">{{ $u->name }} @if($isSelf)<span class="adu-protected">(you)</span>@endif</div>
                                    <div class="adu-user-email">{{ $u->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="adu-role adu-role--{{ $roleName }}">{{ ucfirst($roleName) }}</span></td>
                        <td>
                            @if($u->businesses_count || $u->accounts_count)
                                <span class="adu-owns">{{ $u->businesses_count }} business{{ $u->businesses_count === 1 ? '' : 'es' }}, {{ $u->accounts_count }} account{{ $u->accounts_count === 1 ? '' : 's' }}</span>
                            @else
                                <span class="adu-owns">—</span>
                            @endif
                        </td>
                        <td style="font-size:12px;color:var(--muted);">{{ $u->created_at?->format('d M Y') }}</td>
                        <td>
                            <div style="display:flex;gap:6px;justify-content:flex-end;">
                                <button type="button" class="adu-act-btn adu-edit-btn"
                                    data-user-id="{{ $u->id }}"
                                    data-user-name="{{ $u->name }}"
                                    data-user-email="{{ $u->email }}"
                                    data-user-role="{{ $roleName }}"
                                    data-is-self="{{ $isSelf ? '1' : '0' }}">
                                    <i class="fa fa-pen"></i> Edit
                                </button>
                                @if($deletable)
                                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}" style="margin:0;" onsubmit="return confirm('Delete {{ $u->name }}? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="adu-act-btn adu-act-btn--danger"><i class="fa fa-trash"></i> Delete</button>
                                    </form>
                                @else
                                    <span class="adu-protected">protected</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="adu-empty">
                                <div class="adu-empty-icon"><i class="fa fa-users"></i></div>
                                <p class="adu-empty-title">No users yet</p>
                                <p class="adu-empty-sub">Add the first account to get started.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div style="margin-top:16px;">{{ $users->links() }}</div>
    @endif
</div>

<div id="aduModal" class="adu-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="aduModalTitle">
    <div class="adu-modal-backdrop" id="aduModalBackdrop"></div>
    <div class="adu-modal-shell">
        <div class="adu-modal-head">
            <div>
                <h2 class="adu-modal-title" id="aduModalTitle">Add User</h2>
                <p class="adu-modal-sub" id="aduModalSub">Create a new login for the platform</p>
            </div>
            <button type="button" class="adu-modal-close" id="aduModalClose" aria-label="Close">&times;</button>
        </div>
        <form id="aduForm" method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <input type="hidden" name="_method" id="aduFormMethod" value="POST">
            <div class="adu-modal-body">
                <div class="adu-field">
                    <label for="aduName">Full name</label>
                    <input type="text" id="aduName" name="name" required maxlength="255" value="{{ old('name') }}">
                    @error('name')<p class="adu-field-err">{{ $message }}</p>@enderror
                </div>
                <div class="adu-field">
                    <label for="aduEmail">Email</label>
                    <input type="email" id="aduEmail" name="email" required maxlength="255" value="{{ old('email') }}">
                    @error('email')<p class="adu-field-err">{{ $message }}</p>@enderror
                </div>
                <div class="adu-field">
                    <label for="aduPassword" id="aduPasswordLabel">Password</label>
                    <input type="password" id="aduPassword" name="password" autocomplete="new-password">
                    <p class="adu-field-hint" id="aduPasswordHint">At least 8 characters.</p>
                    @error('password')<p class="adu-field-err">{{ $message }}</p>@enderror
                </div>
                <div class="adu-field">
                    <label for="aduPasswordConfirm">Confirm password</label>
                    <input type="password" id="aduPasswordConfirm" name="password_confirmation" autocomplete="new-password">
                </div>
                <div class="adu-field">
                    <label for="aduRole">Role</label>
                    <select id="aduRole" name="role">
                        @foreach($roles as $roleOption)
                            <option value="{{ $roleOption }}" {{ $roleOption === 'user' ? 'selected' : '' }}>{{ ucfirst($roleOption) }}</option>
                        @endforeach
                    </select>
                    <p class="adu-field-hint" id="aduSelfRoleHint" style="display:none;">You cannot remove your own admin role.</p>
                    @error('role')<p class="adu-field-err">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="adu-modal-foot">
                <button type="button" class="adu-cancel-btn" id="aduModalCancel">Cancel</button>
                <button type="submit" class="adu-add-btn" id="aduSubmitBtn"><i class="fa fa-user-plus"></i> <span id="aduSubmitLabel">Add User</span></button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal    = document.getElementById('aduModal');
    var backdrop = document.getElementById('aduModalBackdrop');
    var closeBtn = document.getElementById('aduModalClose');
    var cancelBtn= document.getElementById('aduModalCancel');
    var openBtn  = document.getElementById('aduOpenCreate');
    var form     = document.getElementById('aduForm');
    var methodEl = document.getElementById('aduFormMethod');
    var title    = document.getElementById('aduModalTitle');
    var sub      = document.getElementById('aduModalSub');
    var submitLabel = document.getElementById('aduSubmitLabel');
    var submitIcon  = document.getElementById('aduSubmitBtn').querySelector('i');
    var nameEl   = document.getElementById('aduName');
    var emailEl  = document.getElementById('aduEmail');
    var roleEl   = document.getElementById('aduRole');
    var passwordEl = document.getElementById('aduPassword');
    var passwordLabel = document.getElementById('aduPasswordLabel');
    var passwordHint = document.getElementById('aduPasswordHint');
    var selfRoleHint = document.getElementById('aduSelfRoleHint');
    var editingSelf = false;

    roleEl.addEventListener('change', function () {
        if (editingSelf && roleEl.value !== 'admin') {
            roleEl.value = 'admin';
        }
    });

    function openModal() { modal.classList.add('is-open'); document.body.style.overflow = 'hidden'; }
    function closeModal() { modal.classList.remove('is-open'); document.body.style.overflow = ''; }

    function setCreateMode() {
        methodEl.value = 'POST';
        form.action = '{{ route('admin.users.store') }}';
        title.textContent = 'Add User';
        sub.textContent   = 'Create a new login for the platform';
        submitLabel.textContent = 'Add User';
        submitIcon.className = 'fa fa-user-plus';
        passwordEl.required = true;
        passwordLabel.textContent = 'Password';
        passwordHint.textContent = 'At least 8 characters.';
        selfRoleHint.style.display = 'none';
        editingSelf = false;
        nameEl.value = '';
        emailEl.value = '';
        passwordEl.value = '';
        document.getElementById('aduPasswordConfirm').value = '';
        roleEl.value = 'user';
    }

    openBtn.addEventListener('click', function () { setCreateMode(); openModal(); });
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    document.querySelectorAll('.adu-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var userId = btn.getAttribute('data-user-id');
            var isSelf = btn.getAttribute('data-is-self') === '1';

            methodEl.value = 'PUT';
            form.action = '/admin/users/' + userId;
            title.textContent = 'Edit User';
            sub.textContent   = 'Update ' + btn.getAttribute('data-user-name');
            submitLabel.textContent = 'Save Changes';
            submitIcon.className = 'fa fa-floppy-disk';
            passwordEl.required = false;
            passwordLabel.textContent = 'New password';
            passwordHint.textContent = 'Leave blank to keep the current password.';

            nameEl.value = btn.getAttribute('data-user-name');
            emailEl.value = btn.getAttribute('data-user-email');
            passwordEl.value = '';
            document.getElementById('aduPasswordConfirm').value = '';
            roleEl.value = btn.getAttribute('data-user-role');
            editingSelf = isSelf;
            selfRoleHint.style.display = isSelf ? 'block' : 'none';

            openModal();
        });
    });
})();
</script>
@endsection
