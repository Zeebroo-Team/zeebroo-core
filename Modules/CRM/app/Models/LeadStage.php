<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadStage extends Model
{
    protected $table = 'crm_lead_stages';

    protected $fillable = [
        'project_id',
        'name',
        'color',
        'is_won',
        'is_lost',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_won'     => 'boolean',
            'is_lost'    => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'stage_id');
    }

    public function automations(): HasMany
    {
        return $this->hasMany(LeadStageAutomation::class, 'stage_id')->orderBy('id');
    }

    public function isTerminal(): bool
    {
        return $this->is_won || $this->is_lost;
    }
}
