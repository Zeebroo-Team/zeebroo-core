<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Business\Models\BusinessInvitation;
use Modules\Business\Models\BusinessMember;
use Modules\Business\Models\BusinessRole;
use Modules\Business\Services\BusinessInvitationService;
use Modules\HRManagement\Services\EmployeeSystemAccessService;
use Modules\HRManagement\Services\UserEmployeeLinkService;
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

    public function index(Request $request, BusinessInvitationService $invitations, EmployeeSystemAccessService $employeeAccess): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        BusinessRole::seedForBusiness($business->id);

        // Mark users who are also HR employees of this business
        $hasHr            = Schema::hasTable('hr_employees');
        $employeesByUser  = $hasHr ? $employeeAccess->employeesByUserId($business) : collect();
        $employeesByEmail = $hasHr ? $employeeAccess->employeesByEmail($business) : collect();

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
            'employee'    => $employeeAccess->employeeTag($employeesByUser->get($owner->id)),
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
                'employee'    => $employeeAccess->employeeTag($employeesByUser->get($member->user_id)),
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
            'invitations' => $pending->map(fn (BusinessInvitation $i) => $invitations->payload($i) + [
                'employee' => $employeeAccess->employeeTag($employeesByEmail->get($i->email)),
            ])->values(),
            'permissions' => BusinessMember::availablePermissions(),
        ]);
    }

    public function store(Request $request, BusinessInvitationService $invitations, UserEmployeeLinkService $employees): JsonResponse
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
            'name'          => ['nullable', 'string', 'max:255'],
            'role'          => ['required', 'string', 'in:' . implode(',', $validSlugs)],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', BusinessMember::permissionKeys())],
        ] + $this->placementRules($business->id));

        // Registered or not, the person gets an invitation email; membership is created on accept.
        [$invitation, $sent] = $invitations->invite(
            $business,
            $validated['email'],
            $validated['role'],
            $validated['permissions'] ?? null,
            $request->user(),
        );

        // Department / position: an incomplete employee profile, linked to the login on accept.
        // Without a placement an existing HR profile with this email is left as it is.
        $departmentId = $this->intOrNull($validated['department_id'] ?? null);
        $jobTitleId   = $this->intOrNull($validated['job_title_id'] ?? null);
        if ($departmentId !== null || $jobTitleId !== null) {
            $employees->assign(
                $business,
                $invitation->email,
                null,
                trim((string) ($validated['name'] ?? '')) ?: Str::before($invitation->email, '@'),
                $departmentId,
                $jobTitleId,
            );
        }

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

    public function cancelInvitation(Request $request, int $invitationId, BusinessInvitationService $invitations, UserEmployeeLinkService $employees): JsonResponse
    {
        $invitation = $this->pendingInvitationOrFail($request, $invitationId);
        $invitations->cancel($invitation);
        $employees->removeIncompleteInvite($this->businessOrAbort($request), $invitation->email);

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

    public function update(Request $request, int $memberId, UserEmployeeLinkService $employees, EmployeeSystemAccessService $employeeAccess): JsonResponse
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
        ] + $this->placementRules($business->id));

        $member->update([
            'role'        => $validated['role'],
            'permissions' => $validated['permissions'] ?? [],
        ]);

        // Only when the client sent a placement — older clients leave the employee profile alone
        $employee = null;
        if ($request->has('department_id') || $request->has('job_title_id')) {
            $employee = $employees->assign(
                $business,
                (string) $member->user->email,
                (int) $member->user_id,
                (string) $member->user->name,
                $this->intOrNull($validated['department_id'] ?? null),
                $this->intOrNull($validated['job_title_id'] ?? null),
            );
            $employee?->load(['department:id,name', 'jobTitle:id,name']);
        }

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
                'employee'    => $employeeAccess->employeeTag($employee),
            ],
        ]);
    }

    /** Optional department / position of a member, both of this business. */
    private function placementRules(int $businessId): array
    {
        $ofBusiness = fn (string $table) => Rule::exists($table, 'id')->where(fn ($q) => $q->where('business_id', $businessId));

        return [
            'department_id' => ['nullable', 'integer', $ofBusiness('hr_departments')],
            'job_title_id'  => ['nullable', 'integer', $ofBusiness('hr_job_titles')],
        ];
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    public function destroy(Request $request, int $memberId, UserEmployeeLinkService $employees): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $member = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('id', $memberId)
            ->firstOrFail();

        $member->delete();
        $employees->removeIncomplete($business, (int) $member->user_id);

        return response()->json(['message' => 'User removed from this business.']);
    }
}
