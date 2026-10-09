<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Mail\Services\AutomatedEmailService;

/**
 * Confirms a self-service sign-up's email with an emailed 6-digit code. Used by the
 * web /verify-email page and the pos-desktop / POS Lite API. Only accounts flagged at
 * registration (while the "Email verification" automated email is on) are ever asked.
 */
final class EmailVerificationService
{
    private const MAX_ATTEMPTS = 5;

    /** Minimum gap between two codes for the same account. */
    public const RESEND_SECONDS = 60;

    public function __construct(private readonly AutomatedEmailService $automated) {}

    /**
     * Called right after sign-up. Flags the account and emails the first code when
     * verification is on; otherwise sends the welcome email straight away.
     * A mail failure never fails the sign-up — the user can resend from the verify screen.
     */
    public function startFor(User $user): void
    {
        if (! $this->automated->emailVerificationEnabled()) {
            $this->automated->queueWelcome($user);

            return;
        }

        $user->forceFill(['email_verification_required' => true])->save();

        try {
            $this->sendCode($user);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** True while this user still has to enter a code before using the app. */
    public function isPending(User $user): bool
    {
        return $user->email_verification_required
            && $user->email_verified_at === null
            && $this->automated->emailVerificationEnabled();
    }

    /** Seconds until another code may be sent (0 = now). */
    public function resendAvailableIn(User $user): int
    {
        if ($user->email_verification_sent_at === null) {
            return 0;
        }

        return max(0, self::RESEND_SECONDS - (int) $user->email_verification_sent_at->diffInSeconds(now(), true));
    }

    public function expiryMinutes(): int
    {
        return $this->automated->verificationMinutes();
    }

    /**
     * Email a fresh code (no-op while the previous one is under a minute old).
     * Returns false when throttled.
     *
     * @throws \Throwable when the mail transport fails
     */
    public function sendCode(User $user): bool
    {
        if (! $this->isPending($user)) {
            return false;
        }

        if ($this->resendAvailableIn($user) > 0) {
            return false;
        }

        $otp = (string) random_int(100000, 999999);

        $user->forceFill([
            'email_verification_code' => Hash::make($otp),
            'email_verification_sent_at' => now(),
        ])->save();
        RateLimiter::clear($this->attemptsKey($user));

        try {
            $this->automated->sendVerificationCode($user, $otp);
        } catch (\Throwable $e) {
            $user->forceFill(['email_verification_code' => null, 'email_verification_sent_at' => null])->save();

            throw $e;
        }

        return true;
    }

    /**
     * Check the code and mark the email verified, then send the welcome email.
     *
     * @throws ValidationException
     */
    public function verify(User $user, string $otp): void
    {
        if (! $this->isPending($user)) {
            return;
        }

        $key = $this->attemptsKey($user);
        $invalid = fn (string $message) => ValidationException::withMessages(['otp' => $message]);

        if ($user->email_verification_code === null || $user->email_verification_sent_at === null) {
            throw $invalid(__('This code is invalid or has expired. Request a new one.'));
        }

        if ($user->email_verification_sent_at->diffInMinutes(now(), true) >= $this->expiryMinutes()) {
            $user->forceFill(['email_verification_code' => null])->save();

            throw $invalid(__('This code has expired. Request a new one.'));
        }

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $user->forceFill(['email_verification_code' => null])->save();

            throw $invalid(__('Too many incorrect attempts. Request a new code.'));
        }

        if (! Hash::check($otp, $user->email_verification_code)) {
            RateLimiter::hit($key, 3600);

            throw $invalid(__('Incorrect code. Check your email and try again.'));
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_sent_at' => null,
        ])->save();

        RateLimiter::clear($key);

        $this->automated->queueWelcome($user);
    }

    private function attemptsKey(User $user): string
    {
        return 'email-verify-otp:'.$user->id;
    }
}
