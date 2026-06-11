<?php

namespace Modules\DesignStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class SocialMediaConnection extends Model
{
    protected $fillable = [
        'business_id',
        'platform',
        'external_id',
        'name',
        'picture_url',
        'access_token',
        'token_expires_at',
        'metadata',
        'connected_by',
    ];

    protected $casts = [
        'access_token'     => 'encrypted',
        'token_expires_at' => 'datetime',
        'metadata'         => 'array',
    ];

    protected $hidden = ['access_token'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function platformLabel(): string
    {
        return match ($this->platform) {
            'facebook' => 'Facebook',
            'linkedin' => 'LinkedIn',
            'youtube'  => 'YouTube',
            'tiktok'   => 'TikTok',
            default    => ucfirst($this->platform),
        };
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
