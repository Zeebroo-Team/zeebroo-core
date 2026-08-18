<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\BusinessRole;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Purchase\Models\GrnPermissionSetting;

class PosGrnPermissionApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    /** GET /api/pos/grn-permissions */
    public function show(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        BusinessRole::seedForBusiness($business->id);

        $settings = GrnPermissionSetting::forBusiness($business->id);

        $roles = BusinessRole::query()
            ->where('business_id', $business->id)
            ->orderBy('is_system', 'desc')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (BusinessRole $r) => [
                'id'        => $r->id,
                'name'      => $r->name,
                'slug'      => $r->slug,
                'color'     => $r->color,
                'is_system' => $r->is_system,
            ]);

        return response()->json([
            'data' => [
                'approval_mode'    => $settings->approval_mode,
                'role_permissions' => $settings->role_permissions ?? (object) [],
                'roles'            => $roles,
            ],
        ]);
    }

    /** PUT /api/pos/grn-permissions */
    public function update(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'approval_mode'                    => ['required', 'string', Rule::in(['without_permission', 'approval_processing'])],
            'role_permissions'                 => ['nullable', 'array'],
            'role_permissions.*.create'        => ['nullable', 'boolean'],
            'role_permissions.*.read'          => ['nullable', 'boolean'],
            'role_permissions.*.approval'      => ['nullable', 'boolean'],
        ]);

        $settings = GrnPermissionSetting::forBusiness($business->id);
        $settings->update([
            'approval_mode'    => $validated['approval_mode'],
            'role_permissions' => $validated['role_permissions'] ?? null,
        ]);

        return response()->json([
            'message' => 'GRN permission settings saved.',
            'data'    => [
                'approval_mode'    => $settings->approval_mode,
                'role_permissions' => $settings->role_permissions ?? (object) [],
            ],
        ]);
    }
}
