<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadStageAutomation extends Model
{
    protected $table = 'crm_lead_stage_automations';

    const RECIPIENT_LEAD          = 'lead';
    const RECIPIENT_ASSIGNED_USER = 'assigned_user';
    const RECIPIENT_CUSTOM        = 'custom';

    protected $fillable = [
        'project_id',
        'stage_id',
        'is_active',
        'recipient_type',
        'recipient_email',
        'subject',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(LeadStage::class, 'stage_id');
    }

    public static function recipientTypes(): array
    {
        return [
            self::RECIPIENT_LEAD          => "Lead's email",
            self::RECIPIENT_ASSIGNED_USER => 'Assigned team member',
            self::RECIPIENT_CUSTOM        => 'Custom email address',
        ];
    }

    public function recipientLabel(): string
    {
        return match ($this->recipient_type) {
            self::RECIPIENT_ASSIGNED_USER => 'Assigned team member',
            self::RECIPIENT_CUSTOM        => $this->recipient_email ?: 'Custom email',
            default                       => "Lead's email",
        };
    }
}
