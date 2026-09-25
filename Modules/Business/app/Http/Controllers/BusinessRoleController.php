<?php

namespace Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessMember;
use Modules\Business\Models\BusinessRole;

class BusinessRoleController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business instanceof Business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select a business first.']);
        }

        abort_unless((int) $business->user_id === (int) $request->user()->id, 403);

        BusinessRole::seedForBusiness($business->id);

        $roles = BusinessRole::query()
            ->where('business_id', $business->id)
            ->orderBy('is_system', 'desc')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $memberCounts = BusinessMember::query()
            ->where('business_id', $business->id)
            ->selectRaw('role, COUNT(*) as cnt')
            ->groupBy('role')
            ->pluck('cnt', 'role');

        return view('business::roles.index', [
            'business' => $business,
            'roles' => $roles,
            'memberCounts' => $memberCounts,
            'permissions' => BusinessRole::availablePermissions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->ownedBusiness($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', BusinessMember::permissionKeys())],
        ]);

        $slug = Str::slug($validated['name'], '_');
        $base = $slug;
        $i = 2;
        while (BusinessRole::query()->where('business_id', $business->id)->where('slug', $slug)->exists()) {
            $slug = $base . '_' . $i++;
        }

        BusinessRole::create([
            'business_id' => $business->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'color' => $validated['color'] ?? '#64748b',
            'description' => $validated['description'] ?? null,
            'permissions' => $validated['permissions'] ?? [],
            'is_system' => false,
            'sort_order' => 99,
        ]);

        return redirect()->route('business.roles.index')->with('status', 'Role created.');
    }

    public function update(Request $request, BusinessRole $role): RedirectResponse
    {
        $business = $this->ownedBusiness($request);
        abort_unless((int) $role->business_id === (int) $business->id, 404);

        $rules = [
            'color' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', BusinessMember::permissionKeys())],
        ];

        if (! $role->is_system) {
            $rules['name'] = ['required', 'string', 'max:80'];
        }

        $validated = $request->validate($rules);

        $updateData = [
            'color' => $validated['color'] ?? $role->color,
            'description' => $validated['description'] ?? $role->description,
            // The permission checklist is disabled client-side for full-access (permissions === null)
            // roles, so its checkboxes never reach the request; `permissions_editable` distinguishes
            // that "left untouched" case from "user unchecked every box" (which must clear to []).
            'permissions' => $request->boolean('permissions_editable', true)
                ? ($validated['permissions'] ?? [])
                : $role->permissions,
        ];

        if (! $role->is_system && isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }

        $role->update($updateData);

        return redirect()->route('business.roles.index')->with('status', 'Role updated.');
    }

    public function destroy(Request $request, BusinessRole $role): RedirectResponse
    {
        $business = $this->ownedBusiness($request);
        abort_unless((int) $role->business_id === (int) $business->id, 404);

        if ($role->is_system) {
            return back()->withErrors(['role' => 'System roles cannot be deleted.']);
        }

        BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('role', $role->slug)
            ->update(['role' => 'staff']);

        $role->delete();

        return redirect()->route('business.roles.index')->with('status', 'Role deleted.');
    }

    private function ownedBusiness(Request $request): Business
    {
        $business = Business::currentForNavbar($request->user());
        abort_unless($business instanceof Business, 404);
        abort_unless((int) $business->user_id === (int) $request->user()->id, 403);

        return $business;
    }
}
