@php
    /** @var array $systemAccess  EmployeeSystemAccessService::status() */
    $accessStatus = $systemAccess['status'] ?? 'none';
    $accessHead = [
        'none'    => ['icon' => 'fa-lock', 'color' => '#64748b', 'title' => __('No system access'),
                      'text' => __('This employee cannot sign in to the business yet. Give access to email them a team invitation — they choose their own password when they accept.')],
        'invited' => ['icon' => 'fa-envelope', 'color' => '#d97706',
                      'title' => ($systemAccess['invitation_expired'] ?? false) ? __('Invitation expired') : __('Invitation sent'),
                      'text' => ($systemAccess['invitation_expired'] ?? false) ? __('The invitation link has expired. Resend it to give them a new link.') : __('Waiting for the employee to accept the invitation in their email.')],
        'active'  => ['icon' => 'fa-circle-check', 'color' => '#16a34a', 'title' => __('Has system access'),
                      'text' => __('This employee signs in to the business with their own account. What they can open is controlled by their role.')],
    ][$accessStatus];
    $selectedRole = $systemAccess['role'] ?? 'staff';
@endphp

<p class="emp-show-pane-head">{{ __('System access') }}</p>

@foreach (['role', 'invitation', 'access'] as $accessErrKey)
    @if($errors->has($accessErrKey))
        <p class="emp-show__err" role="alert">{{ $errors->first($accessErrKey) }}</p>
    @endif
@endforeach

<section class="emp-show__block" aria-label="{{ __('System access') }}">
    <div style="display:flex;gap:14px;align-items:flex-start;padding:16px;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,{{ $accessHead['color'] }} 6%,transparent);">
        <span style="width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:color-mix(in srgb,{{ $accessHead['color'] }} 14%,transparent);color:{{ $accessHead['color'] }};">
            <i class="fa {{ $accessHead['icon'] }}" aria-hidden="true"></i>
        </span>
        <div>
            <p style="margin:0;font-weight:700;color:{{ $accessHead['color'] }};">{{ $accessHead['title'] }}</p>
            <p class="emp-show-prose" style="margin:4px 0 0;">{{ $accessHead['text'] }}</p>
        </div>
    </div>

    <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin:16px 0 0;">
        <div><dt class="emp-docs-table__meta">{{ __('Sign-in email') }}</dt><dd style="margin:2px 0 0;font-weight:600;">{{ $systemAccess['email'] ?: '—' }}</dd></div>
        <div><dt class="emp-docs-table__meta">{{ __('Role') }}</dt><dd style="margin:2px 0 0;font-weight:600;">{{ $systemAccess['role_name'] ?? '—' }}</dd></div>
        @if($accessStatus === 'invited')
            <div><dt class="emp-docs-table__meta">{{ __('Invitation sent') }}</dt><dd style="margin:2px 0 0;font-weight:600;">{{ $systemAccess['sent_at'] ? \Illuminate\Support\Carbon::parse($systemAccess['sent_at'])->format('Y-m-d H:i') : '—' }}</dd></div>
        @endif
    </dl>

    @if($canManageAccess)
        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-top:20px;">
            <form method="post" action="{{ route('hr.employees.system-access.store', $employee) }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin:0;">
                @csrf
                <label style="display:flex;flex-direction:column;gap:4px;font-size:12px;font-weight:600;">
                    {{ __('Role') }}
                    <select name="role" required style="min-width:200px;padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);">
                        @foreach($accessRoles as $r)
                            <option value="{{ $r->slug }}" @selected($r->slug === $selectedRole)>{{ $r->name }}@if($r->permissions === null) — {{ __('full access') }}@endif</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="emp-show__btn-upload">
                    {{ ['none' => __('Give access'), 'invited' => __('Update & resend'), 'active' => __('Change role')][$accessStatus] }}
                </button>
            </form>

            @if($accessStatus === 'invited')
                <form method="post" action="{{ route('hr.employees.system-access.resend', $employee) }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="emp-show__nav-btn">{{ __('Resend') }}</button>
                </form>
            @endif

            @if($accessStatus !== 'none')
                <form method="post" action="{{ route('hr.employees.system-access.destroy', $employee) }}" style="margin:0 0 0 auto;"
                      onsubmit="return confirm(@json($accessStatus === 'active' ? __('Remove this employee\'s access to the business? Their HR record and self-service portal stay as they are.') : __('Cancel the pending invitation?')))">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="emp-show__btn-remove">{{ $accessStatus === 'active' ? __('Remove access') : __('Cancel invitation') }}</button>
                </form>
            @endif
        </div>
    @else
        <p class="emp-show-prose" style="margin-top:18px;"><i class="fa fa-circle-info" aria-hidden="true"></i> {{ __('Only the business owner or an admin can change system access.') }}</p>
    @endif
</section>
