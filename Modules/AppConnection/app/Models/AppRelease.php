<?php

namespace Modules\AppConnection\Models;

use Illuminate\Database\Eloquent\Model;

class AppRelease extends Model
{
    protected $fillable = [
        'version',
        'release_date',
        'channel',
        'is_latest',
        'notes',
        'windows_url',
        'macos_url',
        'linux_url',
    ];

    protected $casts = [
        'release_date' => 'date',
        'is_latest'    => 'boolean',
        'notes'        => 'array',
    ];
}
