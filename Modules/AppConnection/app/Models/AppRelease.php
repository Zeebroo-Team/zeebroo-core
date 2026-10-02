<?php

namespace Modules\AppConnection\Models;

use Illuminate\Database\Eloquent\Model;

class AppRelease extends Model
{
    public const APP_MAIN = 'main'; // electron_app — full Zeebroo POS desktop app
    public const APP_LITE = 'lite'; // electron_app_pos_lite — Zeebroo POS Lite

    public const APPS = [
        self::APP_MAIN => 'Zeebroo POS',
        self::APP_LITE => 'Zeebroo POS Lite',
    ];

    protected $fillable = [
        'app',
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

    public static function latestStable(string $app = self::APP_MAIN): ?self
    {
        return static::where('app', $app)->where('channel', 'stable')->where('is_latest', true)->first()
            ?? static::where('app', $app)->where('channel', 'stable')->orderByDesc('release_date')->orderByDesc('id')->first();
    }
}
