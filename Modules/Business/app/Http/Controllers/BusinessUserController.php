<?php

namespace Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessMember;

class BusinessUserController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business instanceof Business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select a business first.']);
        }

        abort_unless((int) $business->user_id === (int) $request->user()->id, 403);

        $members = $business->members()
            ->with(['user', 'invitedBy'])
            ->latest()
            ->get();

        return view('business::users.index', [
            'business'    => $business,
            'members'     => $members,
            'permissions' => BusinessMember::availablePermissions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business instanceof Business) {
            return redirect()->route('dashboard');
        }
        abort_unless((int) $business->user_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'email'       => ['required', 'email', 'max:255'],
            'role'        => ['required', 'in:admin,manager,staff'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', array_keys(BusinessMember::availablePermissions()))],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return back()->withErrors(['email' => 'No user account found with that email address.'])->withInput();
        }

        if ((int) $user->id === (int) $request->user()->id) {
            return back()->withErrors(['email' => 'You are already the owner of this business.'])->withInput();
        }

        $existing = $business->members()->where('user_id', $user->id)->first();
        if ($existing) {
            return back()->withErrors(['email' => 'This user is already a member of this business.'])->withInput();
        }

        $permissions = $validated['role'] === 'admin' ? null : ($validated['permissions'] ?? []);

        $business->members()->create([
            'user_id'     => $user->id,
            'role'        => $validated['role'],
            'permissions' => $permissions,
            'status'      => 'active',
            'invited_by'  => $request->user()->id,
        ]);

        return redirect()->route('business.users.index')
            ->with('status', "{$user->name} has been added as {$validated['role']}.");
    }

    public function update(Request $request, BusinessMember $member): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business instanceof Business) {
            return redirect()->route('dashboard');
        }
        abort_unless((int) $business->user_id === (int) $request->user()->id, 403);
        abort_unless((int) $member->business_id === (int) $business->id, 404);

        $validated = $request->validate([
            'role'        => ['required', 'in:admin,manager,staff'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', array_keys(BusinessMember::availablePermissions()))],
        ]);

        $permissions = $validated['role'] === 'admin' ? null : ($validated['permissions'] ?? []);

        $member->update([
            'role'        => $validated['role'],
            'permissions' => $permissions,
        ]);

        return redirect()->route('business.users.index')
            ->with('status', 'Member updated successfully.');
    }

    public function destroy(Request $request, BusinessMember $member): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business instanceof Business) {
            return redirect()->route('dashboard');
        }
        abort_unless((int) $business->user_id === (int) $request->user()->id, 403);
        abort_unless((int) $member->business_id === (int) $business->id, 404);

        $name = $member->user?->name ?? 'Member';
        $member->delete();

        return redirect()->route('business.users.index')
            ->with('status', "{$name} has been removed from this business.");
    }
}
