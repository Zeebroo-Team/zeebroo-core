<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;

class Slide extends Model
{
    protected $table = 'ecom_slides';

    protected $fillable = [
        'business_id',
        'image_url',
        'title',
        'subtitle',
        'button_text',
        'button_link',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
