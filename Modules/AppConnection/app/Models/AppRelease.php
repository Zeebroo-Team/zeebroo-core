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

    public static function latestStable(): ?self
    {
        return static::where('channel', 'stable')->where('is_latest', true)->first()
            ?? static::where('channel', 'stable')->orderByDesc('release_date')->orderByDesc('id')->first();
    }
}
