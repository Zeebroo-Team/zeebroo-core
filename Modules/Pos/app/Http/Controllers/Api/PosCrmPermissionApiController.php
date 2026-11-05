<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessMember;
use Modules\Business\Models\BusinessRole;
use Modules\CRM\Services\CrmPermissionService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosCrmPermissionApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly CrmPermissionService $permissions) {}

    /** GET /api/pos/crm/permissions/me — the signed-in user's effective CRM access. */
    public function me(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        return response()->json([
            'data' => $this->permissions->accessFor($business, $request->user())->toArray(),
        ]);
    }

    /** GET /api/pos/crm/permissions — everything the permissions modal needs. */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);

        return response()->json([
            'data' => [
                'functions' => CrmPermissionService::functions(),
                'users'     => $this->permissions->usersForBusiness($business),
                'roles'     => $this->permissions->rolesForBusiness($business),
                'relations' => $this->permissions->relationsWithStages($business),
            ],
        ]);
    }

    /** PUT /api/pos/crm/permissions/users/{userId} */
    public function update(Request $request, int $userId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);
        $this->abortUnlessEditableMember($business, $userId);

        $validated = $request->validate([
            'permissions'    => ['nullable', 'array'],
            'permissions.*'  => ['boolean'],
            'stage_access'   => ['nullable', 'array'],
            'stage_access.*' => ['array'],
            'stage_access.*.*' => ['integer'],
        ]);

        $row = $this->permissions->saveForUser(
            $business,
            $userId,
            $validated['permissions'] ?? [],
            $validated['stage_access'] ?? [],
        );

        return response()->json([
            'message' => 'CRM permissions saved.',
            'data'    => [
                'user_id'      => $userId,
                'customized'   => true,
                'permissions'  => (object) ($row->permissions ?? []),
                'stage_access' => (object) ($row->stage_access ?? []),
            ],
        ]);
    }

    /** DELETE /api/pos/crm/permissions/users/{userId} — back to what their role allows. */
    public function reset(Request $request, int $userId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);
        $this->abortUnlessEditableMember($business, $userId);

        $this->permissions->resetForUser($business, $userId);

        return response()->json(['message' => 'CRM permissions reset to the role defaults.']);
    }

    /** PUT /api/pos/crm/permissions/roles/{roleId} — the CRM template every member of the role follows. */
    public function updateRole(Request $request, int $roleId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);
        $role = $this->editableRoleOrAbort($business, $roleId);

        $validated = $request->validate([
            'permissions'      => ['nullable', 'array'],
            'permissions.*'    => ['boolean'],
            'stage_access'     => ['nullable', 'array'],
            'stage_access.*'   => ['array'],
            'stage_access.*.*' => ['integer'],
        ]);

        $role = $this->permissions->saveForRole(
            $business,
            $role,
            $validated['permissions'] ?? [],
            $validated['stage_access'] ?? [],
        );

        return response()->json([
            'message' => 'Role CRM permissions saved.',
            'data'    => [
                'id'           => (int) $role->id,
                'customized'   => ! empty($role->crm_permissions) || ! empty($role->crm_stage_access),
                'permissions'  => (object) ($role->crm_permissions ?? []),
                'stage_access' => (object) ($role->crm_stage_access ?? []),
            ],
        ]);
    }

    /** DELETE /api/pos/crm/permissions/roles/{roleId} — the role grants full CRM access again. */
    public function resetRole(Request $request, int $roleId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);

        $this->permissions->resetForRole($this->editableRoleOrAbort($business, $roleId));

        return response()->json(['message' => 'Role CRM permissions reset to full access.']);
    }

    private function editableRoleOrAbort(Business $business, int $roleId): BusinessRole
    {
        $role = BusinessRole::query()
            ->where('business_id', $business->id)
            ->where('id', $roleId)
            ->first();

        if ($role === null) {
            throw new HttpResponseException(response()->json(['message' => 'Role not found.'], 404));
        }

        if (CrmPermissionService::roleIsAdmin($role)) {
            throw new HttpResponseException(
                response()->json(['message' => 'Admin roles always have full CRM access.'], 422)
            );
        }

        return $role;
    }

    private function abortUnlessCrmAdmin(Request $request, Business $business): void
    {
        if (! $this->permissions->accessFor($business, $request->user())->isAdmin) {
            throw new HttpResponseException(
                response()->json(['message' => 'Only the business owner or an admin can manage CRM permissions.'], 403)
            );
        }
    }

    private function abortUnlessEditableMember(Business $business, int $userId): void
    {
        $isMember = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('user_id', $userId)
            ->exists();

        if (! $isMember) {
            throw new HttpResponseException(response()->json(['message' => 'User is not a member of this business.'], 404));
        }

        if ($this->permissions->isAdmin($business, $userId)) {
            throw new HttpResponseException(
                response()->json(['message' => 'Owners and admins always have full CRM access.'], 422)
            );
        }
    }
}
