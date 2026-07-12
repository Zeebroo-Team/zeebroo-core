<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadStageLog extends Model
{
    protected $table = 'crm_lead_stage_logs';

    protected $fillable = [
        'lead_id',
        'project_id',
        'from_stage_id',
        'to_stage_id',
        'changed_by',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(LeadStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(LeadStage::class, 'to_stage_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'changed_by');
    }
}
