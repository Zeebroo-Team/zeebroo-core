<?php

namespace Modules\Developers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Business\Models\Business;

class ApiKey extends Model
{
    protected $table = 'developer_api_keys';

    protected $fillable = [
        'business_id',
        'name',
        'token_hash',
        'token_prefix',
        'permissions',
        'is_active',
        'expires_at',
        'last_used_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'permissions'  => 'array',
            'is_active'    => 'boolean',
            'expires_at'   => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public static function generateToken(): array
    {
        $raw    = 'sk_live_' . Str::random(40);
        $hash   = hash('sha256', $raw);
        $prefix = substr($raw, 0, 16) . '…';

        return ['token' => $raw, 'hash' => $hash, 'prefix' => $prefix];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
