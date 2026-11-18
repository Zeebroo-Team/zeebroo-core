<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to change a lead quotation that can no longer be edited (see
 * QuotationRevisionService in the CRM): who asked and why, who approved or rejected it, the
 * version it produced (revision_id) and who made the changes in that version.
 */
class QuotationRevisionRequest extends Model
{
    const STATUS_PENDING   = 'pending';
    const STATUS_APPROVED  = 'approved';
    const STATUS_REJECTED  = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    /** status => [label, Font Awesome icon, colour] */
    const STATUSES = [
        self::STATUS_PENDING   => ['Revision requested', 'fa-code-pull-request', '#a855f7'],
        self::STATUS_APPROVED  => ['Revision approved',  'fa-code-branch',       '#0d9488'],
        self::STATUS_REJECTED  => ['Revision rejected',  'fa-ban',               '#ef4444'],
        self::STATUS_CANCELLED => ['Revision request cancelled', 'fa-xmark',     '#64748b'],
    ];

    protected $fillable = [
        'business_id',
        'quotation_id',
        'version',
        'status',
        'reason',
        'requested_by',
        'decided_by',
        'decided_at',
        'decision_comment',
        'revision_id',
        'revised_by',
        'revised_at',
    ];

    protected $casts = [
        'version'    => 'integer',
        'decided_at' => 'datetime',
        'revised_at' => 'datetime',
    ];

    /** The version the change was asked for. */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** The new version made when the request was approved. */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'revision_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'decided_by');
    }

    public function revisedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'revised_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function label(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }
}
