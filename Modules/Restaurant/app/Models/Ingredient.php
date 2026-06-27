<?php

namespace Modules\Restaurant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class Ingredient extends Model
{
    protected $table = 'restaurant_ingredients';

    protected $fillable = [
        'business_id',
        'name',
        'unit',
        'quantity',
        'low_stock_threshold',
        'cost_per_unit',
    ];

    protected $casts = [
        'quantity'            => 'decimal:3',
        'low_stock_threshold' => 'decimal:3',
        'cost_per_unit'       => 'decimal:4',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'restaurant_recipe_ingredients', 'ingredient_id', 'menu_item_id')
            ->withPivot('quantity_required')
            ->withTimestamps();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class);
    }

    public function isLowStock(): bool
    {
        if ($this->low_stock_threshold === null) {
            return false;
        }
        return (float) $this->quantity <= (float) $this->low_stock_threshold;
    }

    public static function units(): array
    {
        return [
            'g'    => 'Grams (g)',
            'kg'   => 'Kilograms (kg)',
            'ml'   => 'Millilitres (ml)',
            'l'    => 'Litres (l)',
            'pcs'  => 'Pieces (pcs)',
            'tbsp' => 'Tablespoon (tbsp)',
            'tsp'  => 'Teaspoon (tsp)',
            'cup'  => 'Cup',
        ];
    }
}
