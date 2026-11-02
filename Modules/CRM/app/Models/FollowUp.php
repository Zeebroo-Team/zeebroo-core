<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Business\Models\Business;
use Modules\CRM\Services\CrmSettingsService;
use Modules\Sales\Models\Quotation;

/**
 * A planned contact with the lead's customer, usually after a quotation went out
 * (FollowUpService schedules them at the department's followup_days). Logging one marks it
 * done with an outcome and can plan the next.
 *
 * Overdue = still pending after the end of the day it was due, by the business's own
 * timezone (CrmSettingsService::startOfToday). The desktop, the board and
 * crm:process-follow-ups all use isOverdue() / scopeOverdue() so they agree.
 */
class FollowUp extends Model
{
    protected $table = 'crm_follow_ups';

    const STATUS_PENDING   = 'pending';
    const STATUS_DONE      = 'done';
    const STATUS_CANCELLED = 'cancelled';

    /** How the customer was contacted: key => label. */
    const CHANNELS = [
        'call'     => 'Phone call',
        'whatsapp' => 'WhatsApp',
        'email'    => 'Email',
        'sms'      => 'SMS',
        'visit'    => 'Visit',
        'other'    => 'Other',
    ];

    /** What came of it: key => label. */
    const OUTCOMES = [
        'reached'        => 'Reached — still deciding',
        'interested'     => 'Interested',
        'negotiating'    => 'Negotiating',
        'call_back'      => 'Asked to call back',
        'no_answer'      => 'No answer',
        'not_interested' => 'Not interested',
        'accepted'       => 'Ready to accept',
    ];

    protected $fillable = [
        'business_id',
        'lead_id',
        'quotation_id',
        'user_id',
        'status',
        'due_at',
        'done_at',
        'cancelled_at',
        'channel',
        'outcome',
        'notes',
        'next_follow_up_at',
        'due_notified_at',
        'overdue_at',
        'created_by',
    ];

    protected $casts = [
        'due_at'            => 'datetime',
        'done_at'           => 'datetime',
        'cancelled_at'      => 'datetime',
        'next_follow_up_at' => 'datetime',
        'due_notified_at'   => 'datetime',
        'overdue_at'        => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('crm_follow_ups.status', self::STATUS_PENDING);
    }

    /** Pending and due before today — the business's today (its own timezone). */
    public function scopeOverdue(Builder $query, int $businessId): Builder
    {
        return $query->pending()->where('crm_follow_ups.due_at', '<', app(CrmSettingsService::class)->startOfToday($businessId));
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isOverdue(): bool
    {
        return $this->isPending() && $this->due_at !== null && $this->due_at->lt($this->startOfToday());
    }

    public function isDueToday(): bool
    {
        $start = $this->startOfToday();

        return $this->isPending() && $this->due_at !== null && $this->due_at->gte($start) && $this->due_at->lt($start->copy()->addDay());
    }

    private function startOfToday(): Carbon
    {
        return app(CrmSettingsService::class)->startOfToday((int) $this->business_id);
    }

    public static function channelLabel(?string $key): ?string
    {
        return $key !== null ? (self::CHANNELS[$key] ?? ucfirst($key)) : null;
    }

    public static function outcomeLabel(?string $key): ?string
    {
        return $key !== null ? (self::OUTCOMES[$key] ?? ucfirst(str_replace('_', ' ', $key))) : null;
    }
}
