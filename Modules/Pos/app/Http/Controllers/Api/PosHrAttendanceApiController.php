<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\HRManagement\Models\AttendanceRecord;
use Modules\HRManagement\Services\AttendanceGapService;
use Modules\HRManagement\Services\AttendanceReportService;
use Modules\HRManagement\Services\AttendanceService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosHrAttendanceApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly AttendanceService $service,
        private readonly AttendanceGapService $gaps,
        private readonly AttendanceReportService $report,
    ) {}

    /** Working days in a range where employees have no attendance record and no leave. */
    public function gaps(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $v = $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
            'q'    => ['nullable', 'string', 'max:100'],
        ]);

        $from = CarbonImmutable::parse($v['from'])->startOfDay();
        $to   = CarbonImmutable::parse($v['to'])->startOfDay();
        if ($from->diffInDays($to) >= AttendanceGapService::MAX_RANGE_DAYS) {
            return response()->json([
                'message' => 'Choose a range of '.AttendanceGapService::MAX_RANGE_DAYS.' days or less.',
                'errors'  => ['to' => ['Choose a range of '.AttendanceGapService::MAX_RANGE_DAYS.' days or less.']],
            ], 422);
        }

        $rows = $this->gaps->find($business, $from, $to, (string) ($v['q'] ?? ''));

        return response()->json([
            'data'       => $rows,
            'total_days' => array_sum(array_map(fn ($r) => count($r['days']), $rows)),
        ]);
    }

    /** Apply HR's choice per missing day: deduct leave, unpaid leave, absent, or skip. */
    public function resolveGaps(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'hr_employees');

        $v = $request->validate([
            'items'               => ['required', 'array', 'min:1', 'max:500'],
            'items.*.employee_id' => ['required', 'integer'],
            'items.*.date'        => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'items.*.action'      => ['required', Rule::in(AttendanceGapService::ACTIONS)],
            'allow_override'      => ['boolean'],
        ]);

        $result = $this->gaps->resolve($business, $v['items'], $request->user(), (bool) ($v['allow_override'] ?? false));

        return response()->json($result);
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_attendance_records')) {
            return response()->json(['data' => [], 'total_count' => 0]);
        }

        // Legacy call (search only, no range): latest records, as before.
        if (! $request->filled('from') && ! $request->filled('to')) {
            $search  = (string) $request->query('q', '');
            $records = $this->service->listRecent($business, $search);

            return response()->json([
                'data'        => $records->map(fn (AttendanceRecord $r) => $this->format($r))->values(),
                'total_count' => $this->service->countRecent($business, $search),
            ]);
        }

        $v = $request->validate([
            'from'          => ['required', 'date'],
            'to'            => ['required', 'date', 'after_or_equal:from'],
            'q'             => ['nullable', 'string', 'max:100'],
            'status'        => ['nullable', Rule::in(AttendanceReportService::FILTERS)],
            'department_id' => ['nullable', 'integer'],
            'employee'      => ['nullable', 'integer'],
        ]);

        $from = CarbonImmutable::parse($v['from'])->startOfDay();
        $to   = CarbonImmutable::parse($v['to'])->startOfDay();
        if ($from->diffInDays($to) >= AttendanceReportService::MAX_RANGE_DAYS) {
            $msg = 'Choose a range of '.AttendanceReportService::MAX_RANGE_DAYS.' days or less.';

            return response()->json(['message' => $msg, 'errors' => ['to' => [$msg]]], 422);
        }

        $report = $this->report->report($business, [
            'from'          => $from,
            'to'            => $to,
            'q'             => (string) ($v['q'] ?? ''),
            'status'        => (string) ($v['status'] ?? ''),
            'department_id' => isset($v['department_id']) ? (int) $v['department_id'] : null,
            'employee'      => isset($v['employee']) ? (int) $v['employee'] : null,
        ]);

        $report['departments'] = $business->departments()->orderBy('name')->get(['id', 'name']);

        return response()->json($report);
    }

    public function import(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_attendance_records')) {
            return response()->json(['message' => 'HR module is not set up yet.'], 422);
        }

        $validated = $request->validate([
            'rows'                => ['required', 'array', 'min:1', 'max:1000'],
            'rows.*.employee_id'  => ['required', 'string', 'max:64'],
            'rows.*.check_in'     => ['nullable', 'string', 'max:40'],
            'rows.*.check_out'    => ['nullable', 'string', 'max:40'],
        ]);

        $result = $this->service->importFromRows($business, $validated['rows'], (int) $request->user()->id);

        return response()->json($result);
    }

    private function format(AttendanceRecord $r): array
    {
        return [
            'id'             => $r->id,
            'employee_id'    => $r->employee?->employee_id,
            'employee_name'  => $r->employee?->full_name,
            'work_date'      => $r->work_date?->format('Y-m-d'),
            'check_in_at'    => $r->check_in_at?->format('Y-m-d H:i'),
            'check_out_at'   => $r->check_out_at?->format('Y-m-d H:i'),
            'worked_minutes' => $r->worked_minutes,
            'status'         => $r->status,
            'source'         => $r->source,
        ];
    }
}
