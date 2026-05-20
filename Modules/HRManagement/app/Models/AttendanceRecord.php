<?php

declare(strict_types=1);

namespace Modules\HRManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class AttendanceRecord extends Model
{
    public const STATUS_PRESENT = 'present';
    public const STATUS_HALF_DAY = 'half_day';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_PAID_LEAVE = 'paid_leave';
    public const STATUS_UNPAID_LEAVE = 'unpaid_leave';
    public const STATUS_HOLIDAY = 'holiday';
    public const STATUS_WEEKEND = 'weekend';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_HALF_DAY,
        self::STATUS_ABSENT,
        self::STATUS_PAID_LEAVE,
        self::STATUS_UNPAID_LEAVE,
        self::STATUS_HOLIDAY,
        self::STATUS_WEEKEND,
    ];

    protected $table = 'hr_attendance_records';

    protected $fillable = [
        'business_id',
        'employee_id',
        'work_date',
        'status',
        'check_in_at',
        'check_out_at',
        'worked_minutes',
        'source',
        'notes',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'worked_minutes' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
