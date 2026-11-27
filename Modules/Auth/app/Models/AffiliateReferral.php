<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An account that was created with an affiliate's referral code. */
class AffiliateReferral extends Model
{
    public const PLATFORM_WEB = 'web';

    public const PLATFORM_GOOGLE = 'google';

    public const PLATFORM_DESKTOP = 'desktop';

    protected $fillable = ['affiliate_user_id', 'affiliate_code_id', 'referred_user_id', 'code', 'platform'];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affiliate_user_id');
    }

    public function affiliateCode(): BelongsTo
    {
        return $this->belongsTo(AffiliateCode::class);
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function platformLabel(): string
    {
        // The desktop register API stores its own platform value (desktop, mobile, pos_lite).
        return match ($this->platform) {
            self::PLATFORM_GOOGLE => 'Google sign-up',
            self::PLATFORM_DESKTOP => 'Desktop app',
            'mobile' => 'Mobile app',
            'pos_lite' => 'POS Lite app',
            default => 'Website',
        };
    }
}
