<?php

declare(strict_types=1);

namespace Modules\HRManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

final class AttendanceDeviceLog extends Model
{
    protected $table = 'hr_attendance_device_logs';

    protected $fillable = [
        'business_id',
        'device_id',
        'employee_code',
        'punch_time',
        'punch_type',
        'external_event_id',
        'event_uid',
        'payload',
        'processed',
        'processed_at',
        'processing_error',
        'attendance_record_id',
    ];

    protected function casts(): array
    {
        return [
            'punch_time' => 'datetime',
            'payload' => 'array',
            'processed' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }
}
