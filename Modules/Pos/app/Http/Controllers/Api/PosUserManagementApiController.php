<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\BusinessMember;
use Modules\Business\Models\BusinessRole;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosUserManagementApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function me(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $user     = $request->user();

        // Owner always has full access
        if ((int) $business->user_id === (int) $user->id) {
            return response()->json([
                'data' => [
                    'role'        => 'owner',
                    'permissions' => null,  // null = full access
                    'is_owner'    => true,
                ],
                'permissions' => BusinessMember::availablePermissions(),
            ]);
        }

        $member = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->first();

        if ($member === null) {
            return response()->json(['message' => 'You are not a member of this business.'], 403);
        }

        // Find the role template to determine if full access (null permissions)
        BusinessRole::seedForBusiness($business->id);
        $roleObj = BusinessRole::query()
            ->where('business_id', $business->id)
            ->where('slug', $member->role)
            ->first();

        // null role permissions = full access (admin-style role like admin)
        // Otherwise: use member's own permissions if set, else fall back to role defaults
        if ($roleObj?->permissions === null) {
            $permissions = null; // full access
        } elseif ($member->permissions !== null) {
            $permissions = $member->permissions; // member has explicit overrides
        } else {
            $permissions = $roleObj->permissions ?? []; // inherit role defaults
        }

        return response()->json([
            'data' => [
                'role'        => $member->role,
                'permissions' => $permissions,
                'is_owner'    => false,
            ],
            'permissions' => BusinessMember::availablePermissions(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        BusinessRole::seedForBusiness($business->id);

        $owner = $business->user;
        $users = [[
            'id'          => 0,
            'user_id'     => (int) $owner->id,
            'name'        => $owner->name,
            'email'       => $owner->email,
            'role'        => 'owner',
            'permissions' => [],
            'status'      => 'active',
            'is_owner'    => true,
            'joined_at'   => $business->created_at?->toIso8601String(),
        ]];

        $members = BusinessMember::query()
            ->where('business_id', $business->id)
            ->with('user')
            ->orderBy('created_at')
            ->get();

        foreach ($members as $member) {
            if ($member->user === null) {
                continue;
            }
            $users[] = [
                'id'          => (int) $member->id,
                'user_id'     => (int) $member->user_id,
                'name'        => $member->user->name,
                'email'       => $member->user->email,
                'role'        => $member->role,
                'permissions' => $member->permissions ?? [],
                'status'      => $member->status,
                'is_owner'    => false,
                'joined_at'   => $member->created_at?->toIso8601String(),
            ];
        }

        return response()->json([
            'data'        => $users,
            'permissions' => BusinessMember::availablePermissions(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        BusinessRole::seedForBusiness($business->id);

        // Collect valid role slugs for this business (system + custom)
        $validSlugs = BusinessRole::query()
            ->where('business_id', $business->id)
            ->pluck('slug')
            ->toArray();

        $validated = $request->validate([
            'email'         => ['required', 'email'],
            'role'          => ['required', 'string', 'in:' . implode(',', $validSlugs)],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', BusinessMember::permissionKeys())],
        ]);

        if ($business->user->email === $validated['email']) {
            return response()->json(['message' => 'This user is the business owner and already has full access.'], 422);
        }

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null) {
            return response()->json([
                'message' => 'No account found with that email address. The person must create an account first.',
            ], 404);
        }

        $exists = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'This user is already a member of your business.'], 422);
        }

        // If no permissions explicitly set, inherit from role template
        $permissions = $validated['permissions'] ?? null;
        if ($permissions === null) {
            $roleTemplate = BusinessRole::query()
                ->where('business_id', $business->id)
                ->where('slug', $validated['role'])
                ->first();
            $permissions = $roleTemplate?->permissions ?? [];
        }

        $member = BusinessMember::create([
            'business_id' => $business->id,
            'user_id'     => $user->id,
            'role'        => $validated['role'],
            'permissions' => $permissions,
            'status'      => 'active',
            'invited_by'  => $request->user()->id,
        ]);

        return response()->json([
            'data' => [
                'id'          => (int) $member->id,
                'user_id'     => (int) $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'role'        => $member->role,
                'permissions' => $member->permissions ?? [],
                'status'      => $member->status,
                'is_owner'    => false,
                'joined_at'   => $member->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function update(Request $request, int $memberId): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        BusinessRole::seedForBusiness($business->id);

        $validSlugs = BusinessRole::query()
            ->where('business_id', $business->id)
            ->pluck('slug')
            ->toArray();

        $member = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('id', $memberId)
            ->with('user')
            ->firstOrFail();

        $validated = $request->validate([
            'role'          => ['required', 'string', 'in:' . implode(',', $validSlugs)],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', BusinessMember::permissionKeys())],
        ]);

        $member->update([
            'role'        => $validated['role'],
            'permissions' => $validated['permissions'] ?? [],
        ]);

        return response()->json([
            'data' => [
                'id'          => (int) $member->id,
                'user_id'     => (int) $member->user_id,
                'name'        => $member->user->name,
                'email'       => $member->user->email,
                'role'        => $member->role,
                'permissions' => $member->permissions ?? [],
                'status'      => $member->status,
                'is_owner'    => false,
                'joined_at'   => $member->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function destroy(Request $request, int $memberId): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $member = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('id', $memberId)
            ->firstOrFail();

        $member->delete();

        return response()->json(['message' => 'User removed from this business.']);
    }
}
