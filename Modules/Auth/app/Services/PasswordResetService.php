<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Mail\Services\AutomatedEmailService;

/**
 * Forgot-password flow with an emailed 6-digit code. The code is stored hashed in
 * password_reset_tokens; used by both the web pages and the pos-desktop API.
 */
final class PasswordResetService
{
    private const MAX_ATTEMPTS = 5;

    /** Minimum gap between two codes for the same address. */
    private const RESEND_SECONDS = 60;

    public function __construct(private readonly AutomatedEmailService $automated) {}

    /**
     * Email a reset code if an active account exists. Silently does nothing otherwise so
     * the response can't be used to discover which addresses are registered.
     *
     * @throws \Throwable when the mail transport fails
     */
    public function sendCode(string $email): void
    {
        $email = Str::lower(trim($email));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null || ! $user->is_active) {
            return;
        }

        $existing = DB::table('password_reset_tokens')->where('email', $email)->first();
        if ($existing !== null && now()->diffInSeconds($existing->created_at, true) < self::RESEND_SECONDS) {
            return;
        }

        $otp = (string) random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($otp), 'created_at' => now()],
        );
        RateLimiter::clear($this->attemptsKey($email));

        try {
            $this->automated->sendPasswordResetCode($user, $otp);
        } catch (\Throwable $e) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            throw $e;
        }
    }

    /**
     * Check the code and set the new password. Signs the user out everywhere else.
     *
     * @throws ValidationException
     */
    public function reset(string $email, string $otp, string $password): User
    {
        $email = Str::lower(trim($email));
        $key = $this->attemptsKey($email);
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        $invalid = fn (string $message) => ValidationException::withMessages(['otp' => $message]);

        if ($row === null) {
            throw $invalid(__('This code is invalid or has expired. Request a new one.'));
        }

        if (now()->diffInMinutes($row->created_at, true) >= $this->automated->otpMinutes()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            throw $invalid(__('This code has expired. Request a new one.'));
        }

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            throw $invalid(__('Too many incorrect attempts. Request a new code.'));
        }

        if (! Hash::check($otp, $row->token)) {
            RateLimiter::hit($key, 3600);

            throw $invalid(__('Incorrect code. Check your email and try again.'));
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($user === null || ! $user->is_active) {
            throw $invalid(__('This code is invalid or has expired. Request a new one.'));
        }

        DB::transaction(function () use ($user, $password, $email) {
            $user->forceFill([
                'password' => $password, // hashed by the model cast
                'remember_token' => Str::random(60),
            ])->save();

            // A reset means the old password may be compromised: end every other session and API token.
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();

            DB::table('password_reset_tokens')->where('email', $email)->delete();
        });

        RateLimiter::clear($key);

        return $user;
    }

    private function attemptsKey(string $email): string
    {
        return 'password-reset-otp:'.sha1($email);
    }
}
