<?php

namespace Modules\Restaurant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(FileManagerFile::class, 'file_manager_file_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'menu_item_id');
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
