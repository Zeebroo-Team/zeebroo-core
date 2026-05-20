<?php

declare(strict_types=1);

namespace Modules\HRManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Business\Models\Business;
use Modules\HRManagement\Services\AttendanceIngestionService;

final class HrAttendanceDeviceController extends Controller
{
    public function __construct(
        private readonly AttendanceIngestionService $attendanceIngestionService,
    ) {}

    public function ingest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'business_id' => ['required', 'integer', 'exists:businesses,id'],
            'device_id' => ['required', 'string', 'max:100'],
            'punches' => ['required', 'array', 'min:1', 'max:1000'],
            'punches.*.employee_code' => ['required', 'string', 'max:120'],
            'punches.*.punch_time' => ['required', 'date'],
            'punches.*.punch_type' => ['nullable', 'string', 'in:in,out,check_in,check_out,auto'],
            'punches.*.external_event_id' => ['nullable', 'string', 'max:191'],
            'punches.*.payload' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => __('Invalid biometric attendance payload.'),
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();
        $business = Business::query()->findOrFail((int) $validated['business_id']);

        $incomingKey = trim((string) $request->header('X-Attendance-Key', ''));
        $expectedKey = (string) get_settings('hr.attendance.device_ingestion_key', '', $business);
        if ($expectedKey === '' || ! hash_equals($expectedKey, $incomingKey)) {
            return response()->json([
                'message' => __('Unauthorized attendance device request.'),
            ], 401);
        }

        $deviceId = trim((string) $validated['device_id']);
        $normalizedPunches = collect($validated['punches'])
            ->map(fn (array $punch): array => array_merge($punch, ['device_id' => $deviceId]))
            ->values()
            ->all();

        $result = $this->attendanceIngestionService->ingest($business, $normalizedPunches);

        return response()->json([
            'message' => __('Attendance punches ingested.'),
            'data' => $result,
        ]);
    }
}
