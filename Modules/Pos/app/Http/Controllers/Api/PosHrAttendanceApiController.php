<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\HRManagement\Models\AttendanceRecord;
use Modules\HRManagement\Services\AttendanceService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosHrAttendanceApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly AttendanceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_attendance_records')) {
            return response()->json(['data' => [], 'total_count' => 0]);
        }

        $records = $this->service->listRecent($business, (string) $request->query('q', ''));

        return response()->json([
            'data'        => $records->map(fn (AttendanceRecord $r) => $this->format($r))->values(),
            'total_count' => $records->count(),
        ]);
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
