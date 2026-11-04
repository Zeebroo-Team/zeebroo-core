<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A move into a stage that needs approval (LeadStage::needs_approval), waiting for the lead's
 * head. context holds what was asked for on the move (reason, follow-up date, …) and is used
 * for the move itself once approved. One pending request per lead — a newer one replaces it.
 */
class StageMoveRequest extends Model
{
    protected $table = 'crm_stage_move_requests';

    const STATUS_PENDING   = 'pending';
    const STATUS_APPROVED  = 'approved';
    const STATUS_REJECTED  = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'business_id',
        'lead_id',
        'from_stage_id',
        'to_stage_id',
        'requested_by',
        'context',
        'status',
        'decided_by',
        'decided_at',
        'note',
    ];

    protected $casts = [
        'context'    => 'array',
        'decided_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(LeadStage::class, 'to_stage_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
