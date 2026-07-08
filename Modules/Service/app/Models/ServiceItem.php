<?php

namespace Modules\Service\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;
use Modules\Product\Models\Product;

class ServiceItem extends Model
{
    protected $table = 'service_items';

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'price',
        'duration_minutes',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'duration_minutes' => 'integer',
        'is_active'        => 'boolean',
        'is_featured'      => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            ServiceCategory::class,
            'service_item_service_category',
            'service_item_id',
            'service_category_id',
        )->withTimestamps();
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(
            Employee::class,
            'service_item_employee',
            'service_item_id',
            'employee_id',
        )->withTimestamps();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'service_item_product',
            'service_item_id',
            'product_id',
        )->withPivot('qty')->withTimestamps();
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(ServiceDiscount::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function durationLabel(): string
    {
        if (!$this->duration_minutes) {
            return '—';
        }

        $h = intdiv($this->duration_minutes, 60);
        $m = $this->duration_minutes % 60;

        if ($h > 0 && $m > 0) {
            return "{$h}h {$m}m";
        }

        return $h > 0 ? "{$h}h" : "{$m}m";
    }
}
