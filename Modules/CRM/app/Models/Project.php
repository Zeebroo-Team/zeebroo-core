<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class Project extends Model
{
    protected $table = 'crm_projects';

    const STATUS_ACTIVE   = 'active';
    const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'status',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(LeadStage::class)->orderBy('sort_order');
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(LeadCustomField::class)->orderBy('sort_order');
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }
}
