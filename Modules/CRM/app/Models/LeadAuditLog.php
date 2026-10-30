<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only lead audit trail. Rows are never updated — see LeadAuditService.
 */
class LeadAuditLog extends Model
{
    protected $table = 'crm_lead_audit_logs';

    const UPDATED_AT = null;

    const EVENT_CREATED       = 'created';
    const EVENT_UPDATED       = 'updated';
    const EVENT_STAGE_CHANGED = 'stage_changed';
    const EVENT_CONVERTED     = 'converted';
    const EVENT_LOST          = 'lost';
    const EVENT_REOPENED      = 'reopened';
    const EVENT_DELETED       = 'deleted';
    const EVENT_ENQUIRY       = 'enquiry';
    const EVENT_VERIFIED      = 'verified';
    const EVENT_REVIEWED      = 'reviewed';
    const EVENT_FORWARDED     = 'forwarded';
    const EVENT_MERGED        = 'merged';
    const EVENT_ASSIGNED      = 'assigned';

    /** Events that move a lead into, between or out of stages — the History flow view's edges. */
    const STAGE_EVENTS = [
        self::EVENT_CREATED, self::EVENT_STAGE_CHANGED, self::EVENT_CONVERTED,
        self::EVENT_LOST, self::EVENT_REOPENED, self::EVENT_DELETED, self::EVENT_FORWARDED,
    ];

    const SOURCE_DESKTOP = 'desktop';
    const SOURCE_WEB     = 'web';
    const SOURCE_SYSTEM  = 'system';

    protected $fillable = [
        'business_id',
        'project_id',
        'lead_id',
        'lead_name',
        'event',
        'user_id',
        'user_name',
        'source',
        'ip_address',
        'from_stage_id',
        'from_stage_name',
        'to_stage_id',
        'to_stage_name',
        'stage_duration_seconds',
        'changes',
        'meta',
    ];

    protected $casts = [
        'changes'                => 'array',
        'meta'                   => 'array',
        'stage_duration_seconds' => 'integer',
        'created_at'             => 'datetime',
    ];

    public static function events(): array
    {
        return [
            self::EVENT_CREATED       => 'Lead created',
            self::EVENT_UPDATED       => 'Details updated',
            self::EVENT_STAGE_CHANGED => 'Stage changed',
            self::EVENT_CONVERTED     => 'Converted to customer',
            self::EVENT_LOST          => 'Marked as lost',
            self::EVENT_REOPENED      => 'Reopened',
            self::EVENT_DELETED       => 'Lead deleted',
            self::EVENT_ENQUIRY       => 'Repeat enquiry',
            self::EVENT_VERIFIED      => 'Verified',
            self::EVENT_REVIEWED      => 'Review cleared',
            self::EVENT_FORWARDED     => 'Forwarded',
            self::EVENT_MERGED        => 'Merged',
            self::EVENT_ASSIGNED      => 'Assignees changed',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(LeadStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(LeadStage::class, 'to_stage_id');
    }
}
