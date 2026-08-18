<?php

namespace Modules\AdvertisingAgency\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdCreative extends Model
{
    protected $table = 'aa_ad_creatives';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_REVIEW    = 'review';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_PUBLISHED,
    ];

    public const FORMATS = [
        'image',
        'video',
        'gif',
        'html5',
        'copy',
        'audio',
        'other',
    ];

    protected $fillable = [
        'campaign_id',
        'title',
        'format',
        'headline',
        'body_copy',
        'call_to_action',
        'file_url',
        'file_name',
        'dimensions',
        'status',
        'notes',
        'approved_at',
        'published_at',
    ];

    protected $casts = [
        'approved_at'  => 'datetime',
        'published_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function statusLabel(): string
    {
        return ucfirst($this->status);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
