<?php

namespace Modules\AdvertisingAgency\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class Campaign extends Model
{
    protected $table = 'aa_campaigns';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_PAUSED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const CHANNELS = [
        'social_media',
        'search',
        'display',
        'email',
        'video',
        'print',
        'outdoor',
        'radio',
        'tv',
        'other',
    ];

    protected $fillable = [
        'business_id',
        'client_id',
        'name',
        'description',
        'channel',
        'budget',
        'spent',
        'start_date',
        'end_date',
        'status',
        'goal',
        'target_audience',
        'notes',
    ];

    protected $casts = [
        'budget'     => 'decimal:2',
        'spent'      => 'decimal:2',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(AdCreative::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(AgencyTask::class);
    }

    public function remainingBudget(): float
    {
        return max(0.0, (float) $this->budget - (float) $this->spent);
    }

    public function spentPercent(): float
    {
        if ((float) $this->budget <= 0) {
            return 0.0;
        }

        return min(100.0, round((float) $this->spent / (float) $this->budget * 100, 1));
    }

    public function statusLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
