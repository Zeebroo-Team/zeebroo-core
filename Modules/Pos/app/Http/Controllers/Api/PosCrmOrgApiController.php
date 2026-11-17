<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\CRM\Models\DepartmentMember;
use Modules\CRM\Models\Project;
use Modules\CRM\Services\CrmOrgService;
use Modules\CRM\Services\CrmPermissionService;
use Modules\CRM\Services\CrmSettingsService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

/** CRM org structure (departments, members and heads, divisions, pipeline link, department settings) — admin only. */
class PosCrmOrgApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly CrmOrgService $org,
        private readonly CrmPermissionService $permissions,
        private readonly CrmSettingsService $settings,
    ) {}

    /** GET /api/v1/pos/crm/org — everything the org-structure modal needs. */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);

        return response()->json([
            'data' => [
                'departments' => $this->org->departments($business),
                'pipelines'   => Project::query()
                    ->where('business_id', $business->id)
                    ->orderBy('name')
                    ->get(['id', 'name', 'status', 'department_id']),
                'users'       => $this->org->businessUsersWithPlaces($business),
                'marketing_intake_department_id' => $this->org->marketingIntakeDepartment($business)?->id,
                'crm_settings' => $this->settings->present($business),
            ],
        ]);
    }

    /** GET /api/v1/pos/crm/settings — business-wide CRM settings. */
    public function crmSettings(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);

        return response()->json(['data' => $this->settings->present($business)]);
    }

    /**
     * PUT /api/v1/pos/crm/settings — phone_country_code (digits, e.g. "94"; empty = the default),
     * quotation_stock_check (warn | block | off). A setting left out keeps its value.
     */
    public function saveCrmSettings(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);

        $validated = $request->validate([
            'phone_country_code'    => ['sometimes', 'nullable', 'string', 'max:6', 'regex:/^\+?\d{0,4}$/'],
            'quotation_stock_check' => ['sometimes', 'required', 'in:' . implode(',', \Modules\CRM\Models\CrmSetting::STOCK_CHECKS)],
        ]);
        $this->settings->save($business, $validated);

        return response()->json(['message' => 'CRM settings saved.', 'data' => $this->settings->present($business)]);
    }

    /** POST /api/v1/pos/crm/org/departments/{department}/divisions */
    public function storeDivision(Request $request, int $departmentId): JsonResponse
    {
        $business   = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);
        $department = $this->org->departmentForBusiness($business, $departmentId);

        $division = $this->org->createDivision($business, $department, $this->validatedDivision($request));

        return response()->json(['message' => 'Division added.', 'data' => $division], 201);
    }

    /** PUT /api/v1/pos/crm/org/divisions/{division} */
    public function updateDivision(Request $request, int $divisionId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);
        $division = $this->org->divisionForBusiness($business, $divisionId);

        $division = $this->org->updateDivision($business, $division, $this->validatedDivision($request));

        return response()->json(['message' => 'Division updated.', 'data' => $division]);
    }

    /** DELETE /api/v1/pos/crm/org/divisions/{division} */
    public function destroyDivision(Request $request, int $divisionId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);

        $this->org->deleteDivision($this->org->divisionForBusiness($business, $divisionId));

        return response()->json(['message' => 'Division deleted.']);
    }

    /**
     * POST /api/v1/pos/crm/org/departments/{department}/members — { user_id, role: member|head }.
     * Places a user in the department on top of HR; placing them again changes their role.
     */
    public function storeMember(Request $request, int $departmentId): JsonResponse
    {
        $business   = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);
        $department = $this->org->departmentForBusiness($business, $departmentId);

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'role'    => ['required', Rule::in(DepartmentMember::ROLES)],
        ]);

        $member = $this->org->saveMember($business, $department, (int) $validated['user_id'], $validated['role']);

        return response()->json([
            'message' => $member->role === DepartmentMember::ROLE_HEAD ? 'Head added.' : 'Member added.',
            'data'    => $member,
        ], $member->wasRecentlyCreated ? 201 : 200);
    }

    /** PUT /api/v1/pos/crm/org/members/{member} — { role: member|head } */
    public function updateMember(Request $request, int $memberId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);
        $member   = $this->org->memberForBusiness($business, $memberId);

        $validated = $request->validate(['role' => ['required', Rule::in(DepartmentMember::ROLES)]]);

        $member = $this->org->saveMember($business, $member->department, (int) $member->user_id, $validated['role']);

        return response()->json(['message' => 'Role updated.', 'data' => $member]);
    }

    /** DELETE /api/v1/pos/crm/org/members/{member} — the user keeps their HR department. */
    public function destroyMember(Request $request, int $memberId): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);

        $this->org->removeMember($this->org->memberForBusiness($business, $memberId));

        return response()->json(['message' => 'Removed from the department.']);
    }

    /** PUT /api/v1/pos/crm/org/departments/{department}/settings */
    public function updateSettings(Request $request, int $departmentId): JsonResponse
    {
        $business   = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);
        $department = $this->org->departmentForBusiness($business, $departmentId);

        $validated = $request->validate([
            'is_marketing_intake'             => ['boolean'],
            'discount_approval_limit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'followup_days'                   => ['nullable', 'array', 'max:10'],
            'followup_days.*'                 => ['integer', 'min:1', 'max:365'],
        ]);

        $setting = $this->org->saveSettings($business, $department, $validated);

        return response()->json([
            'message' => 'Department settings saved.',
            'data'    => [
                'department_id'                   => $department->id,
                'is_marketing_intake'             => $setting->is_marketing_intake,
                'discount_approval_limit_percent' => $setting->discount_approval_limit_percent !== null ? (float) $setting->discount_approval_limit_percent : null,
                'followup_days'                   => $setting->followupDays(),
            ],
        ]);
    }

    /** POST /api/v1/pos/crm/org/departments/{department}/pipeline — add one, or create one when project_id is empty. */
    public function linkPipeline(Request $request, int $departmentId): JsonResponse
    {
        $business   = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);
        $department = $this->org->departmentForBusiness($business, $departmentId);

        $validated = $request->validate(['project_id' => ['nullable', 'integer']]);

        $project = $this->org->linkPipeline($business, $department, $validated['project_id'] ?? null);

        return response()->json(['message' => 'Pipeline linked.', 'data' => $project]);
    }

    /**
     * DELETE /api/v1/pos/crm/org/departments/{department}/pipelines/{project} — unlink that one;
     * DELETE /api/v1/pos/crm/org/departments/{department}/pipeline — unlink them all.
     */
    public function unlinkPipeline(Request $request, int $departmentId, ?int $projectId = null): JsonResponse
    {
        $business   = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);
        $department = $this->org->departmentForBusiness($business, $departmentId);

        $this->org->unlinkPipeline($business, $department, $projectId);

        return response()->json(['message' => 'Pipeline unlinked.']);
    }

    private function validatedDivision(Request $request): array
    {
        return $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'head_user_id' => ['nullable', 'integer'],
            'is_active'    => ['boolean'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
        ]);
    }

    protected function abortUnlessCrmAdmin(Request $request, Business $business): void
    {
        if (! $this->permissions->accessFor($business, $request->user())->isAdmin) {
            throw new HttpResponseException(
                response()->json(['message' => 'Only the business owner or an admin can manage the CRM org structure.'], 403)
            );
        }
    }
}
