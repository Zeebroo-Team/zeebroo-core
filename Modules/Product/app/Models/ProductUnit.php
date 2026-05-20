<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class ProductUnit extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'abbreviation',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_unit_id');
    }

    public function displayLabel(): string
    {
        $abbr = trim((string) $this->abbreviation);

        return $abbr !== '' ? "{$this->name} ({$abbr})" : $this->name;
    }
}
