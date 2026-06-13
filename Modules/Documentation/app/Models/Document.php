<?php

namespace Modules\Documentation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class Document extends Model
{
    const STATUS_DRAFT     = 'draft';
    const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'business_id',
        'created_by',
        'document_category_id',
        'title',
        'slug',
        'content',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT     => 'Draft',
            self::STATUS_PUBLISHED => 'Published',
            default                => ucfirst($this->status),
        };
    }

    public function categoryLabel(): string
    {
        return $this->category?->name ?? '—';
    }
}
