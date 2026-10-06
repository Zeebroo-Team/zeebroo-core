<?php

namespace Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessMember;
use Modules\Business\Models\BusinessRole;

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
            'roles'       => $this->roleOptions($business, $members->pluck('role')->all()),
            'permissions' => BusinessMember::availablePermissions(),
        ]);
    }

    /**
     * Role options for the member modal: the business's roles plus any role slug
     * already held by a member (e.g. 'officer', 'reporter') so the select can show it.
     *
     * @param  array<int, string>  $extraSlugs
     * @return array<string, string> slug => name
     */
    private function roleOptions(Business $business, array $extraSlugs = []): array
    {
        BusinessRole::seedForBusiness($business->id);

        $options = BusinessRole::query()
            ->where('business_id', $business->id)
            ->orderBy('is_system', 'desc')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->all();

        foreach ($extraSlugs as $slug) {
            if ($slug !== null && $slug !== '' && ! isset($options[$slug])) {
                $options[$slug] = ucfirst($slug);
            }
        }

        return $options;
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
            'role'        => ['required', Rule::in(array_keys($this->roleOptions($business)))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(BusinessMember::permissionKeys())],
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
            'role'        => ['required', Rule::in(array_keys($this->roleOptions($business, [$member->role])))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(BusinessMember::permissionKeys())],
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
