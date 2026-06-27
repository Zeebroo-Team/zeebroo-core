<?php

namespace Modules\Restaurant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;
use Modules\FileManager\Models\FileManagerFile;

class MenuItem extends Model
{
    protected $table = 'restaurant_menu_items';

    protected $fillable = [
        'business_id',
        'menu_category_id',
        'name',
        'description',
        'price',
        'image_path',
        'file_manager_file_id',
        'is_available',
        'prep_time_minutes',
        'dietary_tags',
        'sort_order',
    ];

    protected $casts = [
        'price'             => 'decimal:2',
        'is_available'      => 'boolean',
        'prep_time_minutes' => 'integer',
        'dietary_tags'      => 'array',
        'sort_order'        => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            MenuCategory::class,
            'restaurant_menu_item_categories',
            'menu_item_id',
            'menu_category_id'
        )->orderBy('restaurant_menu_item_categories.sort_order')->orderBy('name');
    }

    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(FileManagerFile::class, 'file_manager_file_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'menu_item_id');
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'restaurant_recipe_ingredients', 'menu_item_id', 'ingredient_id')
            ->withPivot('quantity_required')
            ->withTimestamps();
    }

    public function prepLabel(): string
    {
        if (! $this->prep_time_minutes) {
            return '—';
        }

        $h = intdiv($this->prep_time_minutes, 60);
        $m = $this->prep_time_minutes % 60;

        return $h > 0 ? ($m > 0 ? "{$h}h {$m}m" : "{$h}h") : "{$m}m";
    }
}
