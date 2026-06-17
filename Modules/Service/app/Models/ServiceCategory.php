<?php

namespace Modules\Service\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Business\Models\Business;

class ServiceCategory extends Model
{
    protected $table = 'service_categories';

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function serviceItems(): BelongsToMany
    {
        return $this->belongsToMany(
            ServiceItem::class,
            'service_item_service_category',
            'service_category_id',
            'service_item_id',
        )->withTimestamps();
    }
}
