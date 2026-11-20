<?php

namespace Modules\Payment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One run of the Stripe payment check. `results` holds one entry per checked
 * subscription / checkout:
 *   business_id, business_name, payment_id, kind (subscription|checkout),
 *   outcome (ok|fixed|issue|error), local_status, stripe_status,
 *   paid_this_month (bool), amount, currency, period_end, message
 */
class PaymentCheckRun extends Model
{
    public const TRIGGER_SCHEDULED = 'scheduled';

    public const TRIGGER_MANUAL = 'manual';

    public const OUTCOME_OK = 'ok';

    public const OUTCOME_FIXED = 'fixed';

    public const OUTCOME_ISSUE = 'issue';

    public const OUTCOME_ERROR = 'error';

    protected $fillable = [
        'trigger',
        'triggered_by',
        'period',
        'status',
        'checked_count',
        'ok_count',
        'fixed_count',
        'issue_count',
        'error_count',
        'results',
        'error',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'results' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function durationSeconds(): ?int
    {
        return $this->started_at && $this->finished_at
            ? (int) $this->started_at->diffInSeconds($this->finished_at)
            : null;
    }
}
