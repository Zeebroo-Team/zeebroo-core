<?php

declare(strict_types=1);

namespace Modules\HRManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class LeaveRequest extends Model
{
    protected $table = 'hr_leave_requests';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    /** @var list<string> day-based leave types (balances, web form, payslip) */
    public const LEAVE_TYPES = [
        'annual',
        'casual',
        'sick',
        'unpaid',
        'other',
    ];

    /** A few hours off on one day (short_leave_start + short_leave_minutes); never charged as days. */
    public const TYPE_SHORT = 'short';

    /** Types that may be taken as a half day. */
    public const HALF_DAY_TYPES = ['annual', 'casual', 'sick'];

    protected $fillable = [
        'business_id',
        'employee_id',
        'leave_type',
        'starts_on',
        'ends_on',
        'days',
        'is_half_day',
        'half_day_period',
        'short_leave_start',
        'short_leave_minutes',
        'note',
        'status',
        'recorded_by_user_id',
        'decided_by_user_id',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'days' => 'float',
            'is_half_day' => 'boolean',
            'short_leave_minutes' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function isShort(): bool
    {
        return $this->leave_type === self::TYPE_SHORT;
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
