<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CrmPermissionService;
use Modules\CRM\Services\CrmReportExportService;
use Modules\CRM\Services\CrmReportService;
use Modules\CRM\Services\CrmSalesAnalysisService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Symfony\Component\HttpFoundation\Response;

/**
 * The desktop CRM dashboard (view only): admins, the CEO and "All Departments" see the whole
 * business, heads only what they head. Each endpoint takes the same filters
 * (from, to, department_id, division_id, user_id, source).
 */
class PosCrmReportApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly CrmPermissionService $crmPermissions,
        private readonly CrmReportService $reports,
        private readonly CrmReportExportService $exports,
        private readonly CrmSalesAnalysisService $salesAnalysis,
    ) {}

    /** GET crm/reports/dashboard */
    public function dashboard(Request $request): JsonResponse
    {
        [$business, $access] = $this->context($request);

        return response()->json(['data' => $this->reports->dashboard($business, $access, $this->filters($request))]);
    }

    /** GET crm/reports/dashboard/pdf */
    public function pdf(Request $request): Response
    {
        [$business, $access] = $this->context($request);

        return $this->exports->pdf($business, $this->reports->dashboard($business, $access, $this->filters($request)));
    }

    /** GET crm/reports/dashboard/excel */
    public function excel(Request $request): Response
    {
        [$business, $access] = $this->context($request);

        return $this->exports->excel($this->reports->dashboard($business, $access, $this->filters($request)));
    }

    /** GET crm/reports/sales-analysis — the ribbon "Report" table, grouped by department, customer, product… */
    public function salesAnalysis(Request $request): JsonResponse
    {
        [$business, $access] = $this->context($request);

        return response()->json(['data' => $this->salesAnalysis->report($business, $access, $this->salesFilters($request))]);
    }

    /** GET crm/reports/sales-analysis/pdf */
    public function salesAnalysisPdf(Request $request): Response
    {
        [$business, $access] = $this->context($request);

        return $this->exports->salesAnalysisPdf($business, $this->salesAnalysis->report($business, $access, $this->salesFilters($request)));
    }

    /** GET crm/reports/sales-analysis/csv */
    public function salesAnalysisCsv(Request $request): Response
    {
        [$business, $access] = $this->context($request);

        return $this->exports->salesAnalysisCsv($this->salesAnalysis->report($business, $access, $this->salesFilters($request)));
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'from'          => ['nullable', 'date'],
            'to'            => ['nullable', 'date'],
            'department_id' => ['nullable', 'integer'],
            'division_id'   => ['nullable', 'integer'],
            'user_id'       => ['nullable', 'integer'],
            'source'        => ['nullable', 'string', 'max:40'],
        ]);
    }

    private function salesFilters(Request $request): array
    {
        return [
            ...$this->filters($request),
            ...$request->validate([
                'group_by' => ['nullable', Rule::in(array_keys(CrmSalesAnalysisService::GROUPS))],
                'status'   => ['nullable', Rule::in(array_keys(CrmSalesAnalysisService::STATUSES))],
                'search'   => ['nullable', 'string', 'max:100'],
            ]),
        ];
    }

    /** @return array{0: Business, 1: CrmAccess} */
    private function context(Request $request): array
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->crmPermissions->accessFor($business, $request->user());
        if (! $this->reports->canView($access)) {
            throw new HttpResponseException(
                response()->json(['message' => 'The CRM dashboard is for admins, the CEO and department heads.'], 403)
            );
        }

        return [$business, $access];
    }
}
