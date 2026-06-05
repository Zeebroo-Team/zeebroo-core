<?php

namespace Modules\HRManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class JobTitle extends Model
{
    /** Portal feature keys that can be toggled per designation. */
    public const PORTAL_FEATURES = ['leaves', 'complaints', 'salary', 'pos_online'];

    protected $table = 'hr_job_titles';

    protected $fillable = ['name', 'portal_features'];

    protected $casts = ['portal_features' => 'array'];

    /** null = all features enabled (legacy/default); array = explicit allow-list. */
    public function hasPortalFeature(string $feature): bool
    {
        if ($this->portal_features === null) {
            return true;
        }

        return in_array($feature, $this->portal_features, true);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'job_title_id');
    }
}
