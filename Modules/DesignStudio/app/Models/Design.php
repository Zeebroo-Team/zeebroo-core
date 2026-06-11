<?php

namespace Modules\DesignStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class Design extends Model
{
    protected $table = 'design_studio_designs';

    protected $fillable = [
        'business_id',
        'user_id',
        'title',
        'type',
        'width',
        'height',
        'canvas_json',
    ];

    protected $casts = [
        'width'  => 'integer',
        'height' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
