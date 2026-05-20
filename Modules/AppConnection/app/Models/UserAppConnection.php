<?php

namespace Modules\AppConnection\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAppConnection extends Model
{
    public const PROVIDER_GOOGLE = 'google';

    protected $table = 'user_app_connections';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'email',
        'name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
