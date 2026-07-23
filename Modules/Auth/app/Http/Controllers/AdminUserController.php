<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Auth\Services\UserManagementService;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    public function __construct(private readonly UserManagementService $users) {}

    public function index(): View
    {
        return view('auth::admin.users.index', [
            'users' => $this->users->paginate(),
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        $this->users->create($validated);

        return redirect()->route('admin.users.index')->with('status', __('User created.'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        if ((int) $user->id === (int) $request->user()->id && $validated['role'] !== 'admin') {
            return redirect()->route('admin.users.index')->withErrors([
                'role' => __('You cannot remove your own admin role.'),
            ]);
        }

        if ($this->users->wouldRemoveLastAdmin($user, $validated['role'])) {
            return redirect()->route('admin.users.index')->withErrors([
                'role' => __('Cannot remove the last remaining admin.'),
            ]);
        }

        $this->users->update($user, $validated);

        return redirect()->route('admin.users.index')->with('status', __('User updated.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ((int) $user->id === (int) $request->user()->id) {
            return redirect()->route('admin.users.index')->withErrors([
                'delete' => __('You cannot delete your own account.'),
            ]);
        }

        if ($reason = $this->users->undeletableReason($user)) {
            return redirect()->route('admin.users.index')->withErrors(['delete' => $reason]);
        }

        $this->users->delete($user);

        return redirect()->route('admin.users.index')->with('status', __('User deleted.'));
    }
}
