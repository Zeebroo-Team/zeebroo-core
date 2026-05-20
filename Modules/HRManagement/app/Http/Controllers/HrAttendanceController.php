<?php

declare(strict_types=1);

namespace Modules\HRManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\AttendanceRecord;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Services\AttendanceExcelImportService;
use Modules\HRManagement\Services\AttendanceService;
use Modules\HRManagement\Services\HrPayrollSettingsService;

final class HrAttendanceController extends Controller
{
    public function __construct(
        private readonly HrPayrollSettingsService $hrPayrollSettings,
        private readonly AttendanceService $attendanceService,
        private readonly AttendanceExcelImportService $attendanceExcelImportService,
    ) {}

    public function index(Request $request): RedirectResponse|View
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);

        $month = trim((string) $request->query('month', now()->format('Y-m')));
        $monthDate = Carbon::createFromFormat('Y-m', preg_match('/^\d{4}-\d{2}$/', $month) ? $month : now()->format('Y-m'))->startOfMonth();

        $recentRecords = AttendanceRecord::query()
            ->where('business_id', $business->id)
            ->with(['employee:id,full_name,employee_id'])
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(80)
            ->get();

        return view('hrmanagement::attendance.index', [
            'business' => $business,
            'employees' => $business->employees()->get(),
            'attendanceSummaryRows' => $this->attendanceService->monthlyEmployeeSummary($business, $monthDate),
            'attendanceStatusOptions' => $this->attendanceService->statusOptions(),
            'attendanceMonth' => $monthDate->format('Y-m'),
            'recentAttendanceRecords' => $recentRecords,
        ]);
    }

    public function upsert(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);

        $validated = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('hr_employees', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'work_date' => ['required', 'date'],
            'status' => ['required', 'string', Rule::in(AttendanceRecord::STATUSES)],
            'worked_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $employee = Employee::query()
            ->where('business_id', $business->id)
            ->whereKey((int) $validated['employee_id'])
            ->firstOrFail();

        AttendanceRecord::query()->updateOrCreate(
            [
                'business_id' => $business->id,
                'employee_id' => $employee->id,
                'work_date' => $validated['work_date'],
            ],
            [
                'status' => $validated['status'],
                'worked_minutes' => $validated['worked_minutes'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'source' => 'manual',
                'recorded_by_user_id' => $request->user()->id,
            ]
        );

        return redirect()
            ->route('hr.attendance.index', ['month' => Carbon::parse((string) $validated['work_date'])->format('Y-m')])
            ->with('status', __('Attendance record saved.'));
    }

    public function importExcel(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);

        $validated = $request->validate([
            'attendance_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $result = $this->attendanceExcelImportService->import(
            $business,
            $validated['attendance_file'],
            (int) $request->user()->id
        );

        $message = __('Attendance import completed. Imported: :imported, Skipped: :skipped.', [
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
        ]);

        if (! empty($result['errors'])) {
            return redirect()
                ->route('hr.attendance.index')
                ->with('status', $message)
                ->withErrors($result['errors']);
        }

        return redirect()
            ->route('hr.attendance.index')
            ->with('status', $message);
    }
}
