<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Business\Models\BusinessMember;
use Modules\Business\Models\BusinessRole;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosRoleManagementApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        // Seed system roles on first access
        BusinessRole::seedForBusiness($business->id);

        $roles = BusinessRole::query()
            ->where('business_id', $business->id)
            ->orderBy('is_system', 'desc')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Count members per role slug
        $memberCounts = BusinessMember::query()
            ->where('business_id', $business->id)
            ->selectRaw('role, COUNT(*) as cnt')
            ->groupBy('role')
            ->pluck('cnt', 'role');

        $data = $roles->map(fn (BusinessRole $r) => $this->formatRole($r, $memberCounts[$r->slug] ?? 0));

        return response()->json([
            'data'        => $data,
            'permissions' => BusinessRole::availablePermissions(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:80'],
            'color'         => ['nullable', 'string', 'max:20'],
            'description'   => ['nullable', 'string', 'max:255'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', BusinessMember::permissionKeys())],
        ]);

        $slug = Str::slug($validated['name'], '_');

        // Ensure slug is unique within the business
        $base = $slug;
        $i    = 2;
        while (BusinessRole::query()->where('business_id', $business->id)->where('slug', $slug)->exists()) {
            $slug = $base . '_' . $i++;
        }

        $role = BusinessRole::create([
            'business_id' => $business->id,
            'name'        => $validated['name'],
            'slug'        => $slug,
            'color'       => $validated['color'] ?? '#64748b',
            'description' => $validated['description'] ?? null,
            'permissions' => $validated['permissions'] ?? [],
            'is_system'   => false,
            'sort_order'  => 99,
        ]);

        return response()->json(['data' => $this->formatRole($role, 0)], 201);
    }

    public function update(Request $request, int $roleId): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $role = BusinessRole::query()
            ->where('business_id', $business->id)
            ->where('id', $roleId)
            ->firstOrFail();

        $rules = [
            'color'         => ['nullable', 'string', 'max:20'],
            'description'   => ['nullable', 'string', 'max:255'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', BusinessMember::permissionKeys())],
        ];

        // System roles cannot be renamed
        if (! $role->is_system) {
            $rules['name'] = ['required', 'string', 'max:80'];
        }

        $validated = $request->validate($rules);

        $updateData = [
            'color'       => $validated['color'] ?? $role->color,
            'description' => $validated['description'] ?? $role->description,
            'permissions' => $validated['permissions'] ?? $role->permissions,
        ];

        if (! $role->is_system && isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }

        $role->update($updateData);

        // Sync permissions on all members using this role (optional, respects per-user overrides)
        // We do NOT auto-update members — permissions on members are their own setting

        $memberCount = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('role', $role->slug)
            ->count();

        return response()->json(['data' => $this->formatRole($role, $memberCount)]);
    }

    public function destroy(Request $request, int $roleId): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $role = BusinessRole::query()
            ->where('business_id', $business->id)
            ->where('id', $roleId)
            ->firstOrFail();

        if ($role->is_system) {
            return response()->json(['message' => 'System roles cannot be deleted.'], 422);
        }

        // Move members using this role to 'staff'
        BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('role', $role->slug)
            ->update(['role' => 'staff']);

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }

    private function formatRole(BusinessRole $role, int $memberCount): array
    {
        return [
            'id'           => (int) $role->id,
            'name'         => $role->name,
            'slug'         => $role->slug,
            'color'        => $role->color,
            'description'  => $role->description,
            'permissions'  => $role->permissions, // null = all
            'is_system'    => $role->is_system,
            'sort_order'   => $role->sort_order,
            'member_count' => $memberCount,
        ];
    }
}
