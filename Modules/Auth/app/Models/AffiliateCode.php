<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A referral code owned by an affiliate marketer. New accounts that sign up
 * with it are recorded as AffiliateReferral rows for that marketer.
 */
class AffiliateCode extends Model
{
    protected $fillable = ['user_id', 'code', 'label', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(AffiliateReferral::class);
    }

    /** Public sign-up link that carries this code. */
    public function signupUrl(): string
    {
        return route('register', ['ref' => $this->code]);
    }
}
