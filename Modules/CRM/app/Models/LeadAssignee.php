<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CRM\Models\Concerns\Auditable;

/** One person working a lead. Written only by LeadAssignmentService. */
class LeadAssignee extends Model
{
    use Auditable;

    protected $table = 'crm_lead_assignees';

    public $timestamps = false;

    protected $fillable = [
        'business_id',
        'lead_id',
        'user_id',
        'is_primary',
        'assigned_by',
        'assigned_at',
    ];

    protected $casts = [
        'is_primary'  => 'boolean',
        'assigned_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_by');
    }
}
