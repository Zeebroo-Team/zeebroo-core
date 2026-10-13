<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\SalesReportService;

/** Finance → Sales Reports (desktop). Logic lives in SalesReportService. */
class PosSalesReportApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    private const MAX_RANGE_DAYS = 1096;

    public function __construct(private readonly SalesReportService $reports) {}

    public function show(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_btn_sales');

        $data = $request->validate([
            'date_from'  => ['nullable', 'date_format:Y-m-d'],
            'date_to'    => ['nullable', 'date_format:Y-m-d'],
            'branch_id'  => ['nullable', 'string', 'max:20'],
            'counter_id' => ['nullable', 'integer', 'min:1'],
            'group_by'   => ['nullable', 'in:auto,day,week,month'],
            'source'     => ['nullable', 'in:all,'.implode(',', SalesReportService::SOURCES)],
            'status'     => ['nullable', 'string', 'max:30'],
            'q'          => ['nullable', 'string', 'max:120'],
            'page'       => ['nullable', 'integer', 'min:1'],
            'per_page'   => ['nullable', 'integer', 'min:5', 'max:100'],
            'export'     => ['nullable', 'boolean'],
        ]);

        $to   = isset($data['date_to'])   ? Carbon::parse($data['date_to'])   : now();
        $from = isset($data['date_from']) ? Carbon::parse($data['date_from']) : $to->copy()->subDays(29);
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) >= self::MAX_RANGE_DAYS) {
            $from = $to->copy()->subDays(self::MAX_RANGE_DAYS - 1);
        }

        // branch_id: numeric id, 'none' = sales with no branch, empty/'all' = every branch
        $branch = $data['branch_id'] ?? null;
        $branch = match (true) {
            $branch === null, $branch === '', $branch === 'all' => null,
            $branch === 'none'                                  => 'none',
            ctype_digit($branch)                                => (int) $branch,
            default                                             => null,
        };

        $export = (bool) ($data['export'] ?? false);

        $report = $this->reports->build($business, [
            'from'       => $from,
            'to'         => $to,
            'branch'     => $branch,
            'counter'    => isset($data['counter_id']) ? (int) $data['counter_id'] : null,
            'group_by'   => ($data['group_by'] ?? 'auto') === 'auto' ? null : $data['group_by'],
            'txn_source' => ($data['source'] ?? 'all') === 'all' ? null : $data['source'],
            'txn_status' => $data['status'] ?? null,
            'q'          => $data['q'] ?? null,
            'page'       => $export ? 1 : (int) ($data['page'] ?? 1),
            'per_page'   => $export ? 5000 : (int) ($data['per_page'] ?? 25),
        ]);

        return response()->json(['data' => $report]);
    }
}
