<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class GiftCardGroup extends Model
{
    protected $table = 'pos_gift_card_groups';

    protected $fillable = [
        'business_id',
        'name',
        'initial_value',
        'valid_from',
        'expires_at',
        'is_active',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'initial_value' => 'decimal:2',
            'valid_from'    => 'date',
            'expires_at'    => 'date',
            'is_active'     => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(GiftCard::class, 'pos_gift_card_group_id')->orderBy('id');
    }
}
