<?php

namespace Modules\Restaurant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class MenuCategory extends Model
{
    protected $table = 'restaurant_menu_categories';

    protected $fillable = ['business_id', 'name', 'description', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'menu_category_id');
    }
}
