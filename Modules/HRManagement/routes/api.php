<?php

use Illuminate\Support\Facades\Route;
use Modules\HRManagement\Http\Controllers\HrAttendanceDeviceController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function (): void {
    // Register API endpoints when HR models exist (employees, payroll, leave, …).
});

Route::prefix('v1')->middleware(['throttle:api'])->group(function (): void {
    Route::post('hr/attendance/device-punches', [HrAttendanceDeviceController::class, 'ingest'])
        ->name('api.hr.attendance.device-punches.ingest');
});
