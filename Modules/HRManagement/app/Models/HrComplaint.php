<?php

declare(strict_types=1);

namespace Modules\HRManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class HrComplaint extends Model
{
    protected $table = 'hr_complaints';

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_RESOLVED,
        self::STATUS_DISMISSED,
    ];

    protected $fillable = [
        'business_id',
        'employee_id',
        'subject',
        'body',
        'status',
        'recorded_by_user_id',
    ];

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

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
