<?php

namespace Modules\AdvertisingAgency\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AdvertisingAgency\Http\Controllers\Api\Concerns\ResolvesBusinessForApi;
use Modules\AdvertisingAgency\Models\SalarySheet;
use Modules\AdvertisingAgency\Services\SalarySheetService;

class SalarySheetApiController extends Controller
{
    use ResolvesBusinessForApi;

    public function __construct(private readonly SalarySheetService $service) {}

    /** GET /brand-mgmt/salary-sheets/next-ref */
    public function nextRef(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        return response()->json(['ref' => $this->service->previewNextRef($business)]);
    }

    /** GET /brand-mgmt/salary-sheets */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        return response()->json(['data' => $this->service->list($business, $request->query('q') ?: null)]);
    }

    /** POST /brand-mgmt/salary-sheets */
    public function store(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $validated = $request->validate([
            'location'  => ['nullable', 'string', 'max:200'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'job_id'    => ['nullable', 'integer'],
            'notes'     => ['nullable', 'string', 'max:2000'],
        ]);
        $sheet = $this->service->create($business, $validated);
        return response()->json(['data' => $sheet], 201);
    }

    /** GET /brand-mgmt/salary-sheets/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        return response()->json(['data' => $this->service->show($business, $id)]);
    }

    /** PUT /brand-mgmt/salary-sheets/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $sheet     = SalarySheet::where('business_id', $business->id)->findOrFail($id);
        $validated = $request->validate([
            'job_id'                         => ['sometimes', 'nullable', 'integer'],
            'location'                       => ['nullable', 'string', 'max:200'],
            'date_from'                      => ['sometimes', 'nullable', 'date'],
            'date_to'                        => ['sometimes', 'nullable', 'date'],
            'status'                         => ['sometimes', 'string', 'in:draft,completed,approved,rejected,paid'],
            'notes'                          => ['nullable', 'string', 'max:2000'],
            'default_coordinator_fee'        => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'allowances'                          => ['sometimes', 'array'],
            'allowances.*.id'                     => ['nullable', 'integer'],
            'allowances.*.allowance_type'         => ['required_with:allowances', 'string', 'max:150'],
            'allowances.*.amount'                 => ['nullable', 'numeric', 'min:0'],
            'allowances.*.description'            => ['nullable', 'string', 'max:1000'],
            'position_rules'                      => ['sometimes', 'array'],
            'position_rules.*.id'                 => ['nullable', 'integer'],
            'position_rules.*.position_name'      => ['required_with:position_rules', 'string', 'max:150'],
            'position_rules.*.daily_rate'         => ['nullable', 'numeric', 'min:0'],
            'position_rules.*.transport_allowance'=> ['nullable', 'numeric', 'min:0'],
        ]);
        $this->service->update($sheet, $validated);
        return response()->json(['data' => $this->service->show($business, $id)]);
    }

    /** DELETE /brand-mgmt/salary-sheets/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $sheet    = SalarySheet::where('business_id', $business->id)->findOrFail($id);
        $this->service->delete($sheet);
        return response()->json(['message' => 'Salary sheet deleted.']);
    }

    /** POST /brand-mgmt/salary-sheets/{id}/save-rows */
    public function saveRows(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $sheet    = SalarySheet::where('business_id', $business->id)->findOrFail($id);

        $validated = $request->validate([
            'rows'                            => ['required', 'array'],
            'rows.*.id'                       => ['nullable', 'integer'],
            'rows.*.item_number'              => ['nullable', 'string', 'max:30'],
            'rows.*.location'                 => ['nullable', 'string', 'max:100'],
            'rows.*.position'                 => ['nullable', 'string', 'max:100'],
            'rows.*.promoter_id'              => ['nullable', 'integer'],
            'rows.*.promoter_name'            => ['nullable', 'string', 'max:150'],
            'rows.*.bank_name'                => ['nullable', 'string', 'max:150'],
            'rows.*.bank_branch'              => ['nullable', 'string', 'max:150'],
            'rows.*.bank_account'             => ['nullable', 'string', 'max:100'],
            'rows.*.daily_rate'               => ['nullable', 'numeric', 'min:0'],
            'rows.*.transport_allowance'      => ['nullable', 'numeric', 'min:0'],
            'rows.*.expenses'                 => ['nullable', 'numeric', 'min:0'],
            'rows.*.hold_amount'              => ['nullable', 'numeric', 'min:0'],
            'rows.*.coordinator_id'           => ['nullable', 'integer'],
            'rows.*.coordinator_name'         => ['nullable', 'string', 'max:150'],
            'rows.*.coordination_fee'         => ['nullable', 'numeric', 'min:0'],
            'rows.*.coordinator_bank_details' => ['nullable', 'string', 'max:250'],
            'rows.*.attendances'              => ['nullable', 'array'],
            'rows.*.attendances.*.date'       => ['required', 'date'],
            'rows.*.attendances.*.status'     => ['required', 'in:P,A'],
        ]);

        $this->service->saveRows($sheet, $validated['rows']);
        return response()->json(['data' => $this->service->show($business, $id)]);
    }
}
