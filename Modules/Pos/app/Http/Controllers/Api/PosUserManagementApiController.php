<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\BusinessInvitation;
use Modules\Business\Models\BusinessMember;
use Modules\Business\Models\BusinessRole;
use Modules\Business\Services\BusinessInvitationService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\PosCashier;

class PosUserManagementApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function me(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $user     = $request->user();

        // Cashier — POS-only access (UI will restrict via cashierMode flag)
        if ($user instanceof PosCashier) {
            return response()->json([
                'data' => [
                    'role'        => 'cashier',
                    'permissions' => ['pos_session', 'pos_checkout'],
                    'is_owner'    => false,
                    'is_cashier'  => true,
                    'cashier_id'  => $user->id,
                    'cashier_name'=> $user->name,
                ],
            ]);
        }

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

    public function index(Request $request, BusinessInvitationService $invitations): JsonResponse
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

        $pending = BusinessInvitation::query()
            ->where('business_id', $business->id)
            ->where('status', BusinessInvitation::STATUS_PENDING)
            ->with('invitedBy')
            ->latest()
            ->get();

        return response()->json([
            'data'        => $users,
            'invitations' => $pending->map(fn (BusinessInvitation $i) => $invitations->payload($i))->values(),
            'permissions' => BusinessMember::availablePermissions(),
        ]);
    }

    public function store(Request $request, BusinessInvitationService $invitations): JsonResponse
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

        // Registered or not, the person gets an invitation email; membership is created on accept.
        [$invitation, $sent] = $invitations->invite(
            $business,
            $validated['email'],
            $validated['role'],
            $validated['permissions'] ?? null,
            $request->user(),
        );

        return response()->json([
            'data'       => $invitations->payload($invitation),
            'email_sent' => $sent,
            'message'    => $sent
                ? "Invitation sent to {$invitation->email}."
                : 'Invitation saved, but the email could not be sent. Use "Resend" to try again.',
        ], 201);
    }

    public function resendInvitation(Request $request, int $invitationId, BusinessInvitationService $invitations): JsonResponse
    {
        $invitation = $this->pendingInvitationOrFail($request, $invitationId);

        if (! $invitations->resend($invitation)) {
            return response()->json(['message' => 'The email could not be sent. Please try again in a few minutes.'], 503);
        }

        return response()->json([
            'data'    => $invitations->payload($invitation->refresh()),
            'message' => "Invitation resent to {$invitation->email}.",
        ]);
    }

    public function cancelInvitation(Request $request, int $invitationId, BusinessInvitationService $invitations): JsonResponse
    {
        $invitations->cancel($this->pendingInvitationOrFail($request, $invitationId));

        return response()->json(['message' => 'Invitation cancelled.']);
    }

    private function pendingInvitationOrFail(Request $request, int $invitationId): BusinessInvitation
    {
        $business = $this->businessOrAbort($request);

        return BusinessInvitation::query()
            ->where('business_id', $business->id)
            ->where('id', $invitationId)
            ->where('status', BusinessInvitation::STATUS_PENDING)
            ->firstOrFail();
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
