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

    /** @var list<string> */
    public const LEAVE_TYPES = [
        'annual',
        'casual',
        'sick',
        'unpaid',
        'other',
    ];

    protected $fillable = [
        'business_id',
        'employee_id',
        'leave_type',
        'starts_on',
        'ends_on',
        'note',
        'status',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
