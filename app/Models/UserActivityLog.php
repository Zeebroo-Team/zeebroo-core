<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class UserActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const PLATFORM_WEB = 'web';

    public const PLATFORM_DESKTOP = 'desktop';

    public const EVENT_REGISTER = 'register';

    public const EVENT_LOGIN = 'login';

    protected $fillable = [
        'user_id',
        'platform',
        'event',
        'device_name',
        'ip_address',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(User $user, string $platform, string $event, ?Request $request = null, ?string $deviceName = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'platform' => $platform,
            'event' => $event,
            'device_name' => $deviceName,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
