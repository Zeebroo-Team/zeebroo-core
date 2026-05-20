<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\AttendanceDeviceLog;
use Modules\HRManagement\Models\AttendanceRecord;
use Modules\HRManagement\Models\EmployeeBiometricMapping;

final class AttendanceIngestionService
{
    /**
     * @param  array<int, array<string, mixed>>  $punches
     * @return array{accepted: int, processed: int, duplicates: int, errors: int}
     */
    public function ingest(Business $business, array $punches): array
    {
        $accepted = 0;
        $processed = 0;
        $duplicates = 0;
        $errors = 0;

        foreach ($punches as $punch) {
            $eventUid = $this->resolveEventUid($business, $punch);

            $existing = AttendanceDeviceLog::query()->where('event_uid', $eventUid)->first();
            if ($existing) {
                $duplicates++;

                continue;
            }

            $accepted++;

            try {
                DB::transaction(function () use (&$processed, $business, $punch, $eventUid): void {
                    $log = AttendanceDeviceLog::query()->create([
                        'business_id' => $business->id,
                        'device_id' => trim((string) Arr::get($punch, 'device_id')),
                        'employee_code' => trim((string) Arr::get($punch, 'employee_code')),
                        'punch_time' => Carbon::parse((string) Arr::get($punch, 'punch_time')),
                        'punch_type' => (string) Arr::get($punch, 'punch_type', 'auto'),
                        'external_event_id' => Arr::get($punch, 'external_event_id'),
                        'event_uid' => $eventUid,
                        'payload' => Arr::get($punch, 'payload'),
                    ]);

                    $this->processLog($business, $log);
                    $processed++;
                });
            } catch (\Throwable $exception) {
                $errors++;
                AttendanceDeviceLog::query()->updateOrCreate(
                    ['event_uid' => $eventUid],
                    [
                        'business_id' => $business->id,
                        'device_id' => trim((string) Arr::get($punch, 'device_id')),
                        'employee_code' => trim((string) Arr::get($punch, 'employee_code')),
                        'punch_time' => Carbon::parse((string) Arr::get($punch, 'punch_time')),
                        'punch_type' => (string) Arr::get($punch, 'punch_type', 'auto'),
                        'external_event_id' => Arr::get($punch, 'external_event_id'),
                        'payload' => Arr::get($punch, 'payload'),
                        'processed' => false,
                        'processing_error' => mb_substr($exception->getMessage(), 0, 1000),
                    ]
                );
            }
        }

        return [
            'accepted' => $accepted,
            'processed' => $processed,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ];
    }

    private function processLog(Business $business, AttendanceDeviceLog $log): void
    {
        $mapping = EmployeeBiometricMapping::query()
            ->where('business_id', $business->id)
            ->where('device_id', $log->device_id)
            ->where('device_employee_code', $log->employee_code)
            ->where('is_active', true)
            ->first();

        if (! $mapping) {
            $log->forceFill([
                'processed' => false,
                'processing_error' => 'No active biometric mapping found for device employee code.',
            ])->save();

            return;
        }

        $punchTime = Carbon::parse((string) $log->punch_time);
        $workDate = $punchTime->copy()->toDateString();
        $existing = AttendanceRecord::query()->firstOrNew([
            'business_id' => $business->id,
            'employee_id' => $mapping->employee_id,
            'work_date' => $workDate,
        ]);

        $checkIn = $existing->check_in_at ? Carbon::parse((string) $existing->check_in_at) : null;
        $checkOut = $existing->check_out_at ? Carbon::parse((string) $existing->check_out_at) : null;
        $punchType = strtolower((string) $log->punch_type);

        if (in_array($punchType, ['in', 'check_in'], true)) {
            if (! $checkIn || $punchTime->lt($checkIn)) {
                $checkIn = $punchTime->copy();
            }
        } elseif (in_array($punchType, ['out', 'check_out'], true)) {
            if (! $checkOut || $punchTime->gt($checkOut)) {
                $checkOut = $punchTime->copy();
            }
        } else {
            if (! $checkIn || $punchTime->lt($checkIn)) {
                $checkIn = $punchTime->copy();
            }
            if (! $checkOut || $punchTime->gt($checkOut)) {
                $checkOut = $punchTime->copy();
            }
        }

        $workedMinutes = null;
        if ($checkIn && $checkOut && $checkOut->greaterThanOrEqualTo($checkIn)) {
            $workedMinutes = $checkOut->diffInMinutes($checkIn);
        }

        $existing->fill([
            'status' => AttendanceRecord::STATUS_PRESENT,
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'worked_minutes' => $workedMinutes,
            'source' => 'biometric',
            'recorded_by_user_id' => null,
        ]);
        $existing->save();

        $log->forceFill([
            'processed' => true,
            'processed_at' => now(),
            'processing_error' => null,
            'attendance_record_id' => $existing->id,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $punch
     */
    private function resolveEventUid(Business $business, array $punch): string
    {
        $externalEventId = trim((string) ($punch['external_event_id'] ?? ''));
        if ($externalEventId !== '') {
            return hash('sha256', $business->id.'|'.$externalEventId);
        }

        $uniqueBits = [
            $business->id,
            trim((string) ($punch['device_id'] ?? '')),
            trim((string) ($punch['employee_code'] ?? '')),
            trim((string) ($punch['punch_time'] ?? '')),
            strtolower(trim((string) ($punch['punch_type'] ?? 'auto'))),
        ];

        return hash('sha256', implode('|', $uniqueBits));
    }
}
