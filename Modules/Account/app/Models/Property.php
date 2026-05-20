<?php

namespace Modules\Account\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class Property extends Model
{
    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            'accessories' => __('Accessories'),
            'machinery' => __('Machinery'),
            'landing' => __('Landing'),
            'land' => __('Land'),
            'building' => __('Building'),
            'office' => __('Office'),
            'shop' => __('Shop'),
            'warehouse' => __('Warehouse'),
            'vehicle' => __('Vehicle'),
            'furniture' => __('Furniture'),
            'electronics' => __('Electronics'),
            'other' => __('Other'),
        ];
    }

    protected $fillable = [
        'user_id',
        'business_id',
        'property_name',
        'property_type',
        'cost',
        'has_expiry',
        'expire_date',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'has_expiry' => 'boolean',
            'expire_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
