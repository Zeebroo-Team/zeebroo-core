<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;
use Modules\FileManager\Models\FileManagerFile;

class SaleCampaign extends Model
{
    protected $fillable = [
        'business_id',
        'file_manager_file_id',
        'name',
        'description',
        'mode',
        'discount_type',
        'discount_value',
        'is_long_term',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'is_long_term'   => 'boolean',
            'starts_at'      => 'date',
            'ends_at'        => 'date',
            'is_active'      => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(FileManagerFile::class, 'file_manager_file_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleCampaignItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        return $this->imageFile?->publicUrl();
    }

    /** Whether this campaign is currently in its validity window. */
    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        $today = now()->startOfDay();
        if ($this->starts_at && $this->starts_at->gt($today)) {
            return false;
        }
        if (! $this->is_long_term && $this->ends_at && $this->ends_at->lt($today)) {
            return false;
        }
        return true;
    }

    /** Whether this campaign's validity window has passed (and it isn't long term). */
    public function isExpired(): bool
    {
        if ($this->is_long_term) {
            return false;
        }
        $today = now()->startOfDay();
        return (bool) ($this->ends_at && $this->ends_at->lt($today));
    }
}
