<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Project;
use Modules\CRM\Services\CrmOrgService;
use Modules\CRM\Services\CrmPermissionService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

/** CRM org structure (departments, divisions, pipeline link, department settings) — admin only. */
class PosCrmOrgApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly CrmOrgService $org,
        private readonly CrmPermissionService $permissions,
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
                'users'       => $this->org->businessUsers($business),
                'marketing_intake_department_id' => $this->org->marketingIntakeDepartment($business)?->id,
            ],
        ]);
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

    /** POST /api/v1/pos/crm/org/departments/{department}/pipeline — link one, or create one when project_id is empty. */
    public function linkPipeline(Request $request, int $departmentId): JsonResponse
    {
        $business   = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);
        $department = $this->org->departmentForBusiness($business, $departmentId);

        $validated = $request->validate(['project_id' => ['nullable', 'integer']]);

        $project = $this->org->linkPipeline($business, $department, $validated['project_id'] ?? null);

        return response()->json(['message' => 'Pipeline linked.', 'data' => $project]);
    }

    /** DELETE /api/v1/pos/crm/org/departments/{department}/pipeline */
    public function unlinkPipeline(Request $request, int $departmentId): JsonResponse
    {
        $business   = $this->businessOrAbort($request);
        $this->abortUnlessCrmAdmin($request, $business);
        $department = $this->org->departmentForBusiness($business, $departmentId);

        $this->org->unlinkPipeline($business, $department);

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
