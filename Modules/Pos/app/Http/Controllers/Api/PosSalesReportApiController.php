<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\SalesReportService;

/**
 * Finance → Sales Reports (desktop). Logic lives in SalesReportService.
 *   show       — sales summary (every income stream, breakdowns, transactions)
 *   products   — product-wise sales, day by day (within the last 30 days only)
 *   customers  — customer-wise sales
 */
class PosSalesReportApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    private const MAX_RANGE_DAYS = 1096;

    /** The product report has one column per day — kept to today and the 29 days before it. */
    private const MAX_PRODUCT_RANGE_DAYS = 30;

    public function __construct(private readonly SalesReportService $reports) {}

    public function show(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_btn_sales');

        $data = $request->validate([
            ...$this->scopeRules(),
            'group_by'   => ['nullable', 'in:auto,day,week,month'],
            'source'     => ['nullable', 'in:all,'.implode(',', SalesReportService::SOURCES)],
            'status'     => ['nullable', 'string', 'max:30'],
            'q'          => ['nullable', 'string', 'max:120'],
            'page'       => ['nullable', 'integer', 'min:1'],
            'per_page'   => ['nullable', 'integer', 'min:5', 'max:100'],
            'export'     => ['nullable', 'boolean'],
        ]);

        $export = (bool) ($data['export'] ?? false);

        $report = $this->reports->build($business, [
            ...$this->scope($data, self::MAX_RANGE_DAYS),
            'group_by'   => ($data['group_by'] ?? 'auto') === 'auto' ? null : $data['group_by'],
            'txn_source' => ($data['source'] ?? 'all') === 'all' ? null : $data['source'],
            'txn_status' => $data['status'] ?? null,
            'q'          => $data['q'] ?? null,
            'page'       => $export ? 1 : (int) ($data['page'] ?? 1),
            'per_page'   => $export ? 5000 : (int) ($data['per_page'] ?? 25),
        ]);

        return response()->json(['data' => $report]);
    }

    public function products(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_btn_sales');

        $data = $request->validate([
            ...$this->scopeRules(),
            'source' => ['nullable', 'in:all,pos,invoices'],
        ]);

        // Only today and the 29 days before it — dates outside that window are pulled in
        $scope    = $this->scope($data, self::MAX_PRODUCT_RANGE_DAYS);
        $maxDate  = now()->startOfDay();
        $minDate  = $maxDate->copy()->subDays(self::MAX_PRODUCT_RANGE_DAYS - 1);
        $scope['to']   = $scope['to']->copy()->max($minDate)->min($maxDate);
        $scope['from'] = $scope['from']->copy()->max($minDate)->min($scope['to']);

        $report = $this->reports->products($business, [
            ...$scope,
            'source' => ($data['source'] ?? 'all') === 'all' ? null : $data['source'],
        ]);
        $report['range']['max_days'] = self::MAX_PRODUCT_RANGE_DAYS;
        $report['range']['min_date'] = $minDate->toDateString();
        $report['range']['max_date'] = $maxDate->toDateString();

        return response()->json(['data' => $report]);
    }

    public function customers(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_btn_sales');

        $data = $request->validate($this->scopeRules());

        $report = $this->reports->customers($business, $this->scope($data, self::MAX_RANGE_DAYS));
        $report['range']['max_days'] = self::MAX_RANGE_DAYS;

        return response()->json(['data' => $report]);
    }

    private function scopeRules(): array
    {
        return [
            'date_from'  => ['nullable', 'date_format:Y-m-d'],
            'date_to'    => ['nullable', 'date_format:Y-m-d'],
            'branch_id'  => ['nullable', 'string', 'max:20'],
            'counter_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Date range (defaults to the last 30 days, start moved forward past $maxDays),
     * branch and counter shared by every report.
     *
     * @return array{from: Carbon, to: Carbon, branch: int|string|null, counter: int|null}
     */
    private function scope(array $data, int $maxDays): array
    {
        $to   = isset($data['date_to'])   ? Carbon::parse($data['date_to'])   : now();
        $from = isset($data['date_from']) ? Carbon::parse($data['date_from']) : $to->copy()->subDays(29);
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) >= $maxDays) {
            $from = $to->copy()->subDays($maxDays - 1);
        }

        // branch_id: numeric id, 'none' = sales with no branch, empty/'all' = every branch
        $branch = $data['branch_id'] ?? null;
        $branch = match (true) {
            $branch === null, $branch === '', $branch === 'all' => null,
            $branch === 'none'                                  => 'none',
            ctype_digit($branch)                                => (int) $branch,
            default                                             => null,
        };

        return [
            'from'    => $from,
            'to'      => $to,
            'branch'  => $branch,
            'counter' => isset($data['counter_id']) ? (int) $data['counter_id'] : null,
        ];
    }
}
