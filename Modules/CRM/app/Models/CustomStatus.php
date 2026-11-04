<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

/**
 * A status the business made itself ("Site Visit", "Demo Booked", …), offered next to the
 * standard ones as a stage template in every pipeline. It counts as its base_key standard status
 * in reports and workflows; its colour, icon, note, reason flag and entry actions pre-fill the
 * stages made from it. Entry actions here never name people — those differ per department.
 */
class CustomStatus extends Model
{
    protected $table = 'crm_custom_statuses';

    protected $fillable = [
        'business_id',
        'name',
        'color',
        'icon',
        'description',
        'base_key',
        'requires_reason',
        'entry_rules',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_reason' => 'boolean',
            'entry_rules'     => 'array',
            'sort_order'      => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(LeadStage::class, 'custom_status_id');
    }
}
