<?php

namespace Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Setting extends Model
{
    protected $fillable = [
        'scope_type',
        'scope_id',
        'key',
        'value',
        'value_type',
    ];

    public function scope(): MorphTo
    {
        return $this->morphTo();
    }
}
