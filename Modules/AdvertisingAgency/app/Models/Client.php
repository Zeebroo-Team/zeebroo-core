<?php

namespace Modules\AdvertisingAgency\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class Client extends Model
{
    protected $table = 'aa_clients';

    protected $fillable = [
        'business_id',
        'name',
        'company',
        'email',
        'phone',
        'address',
        'industry',
        'website',
        'notes',
        'status',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function activeCampaignsCount(): int
    {
        return $this->campaigns()->where('status', Campaign::STATUS_ACTIVE)->count();
    }

    public function totalBudget(): float
    {
        return (float) $this->campaigns()->sum('budget');
    }
}
