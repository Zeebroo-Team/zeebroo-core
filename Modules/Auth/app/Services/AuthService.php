<?php

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\BusinessMember;
use Modules\Payment\Models\Payment;
use Spatie\Permission\Models\Role;

class AuthService
{
    public function attemptLogin(array $credentials, bool $remember = false): bool
    {
        return Auth::attempt($credentials, $remember);
    }

    /** Sign-up pre-check: true when no account uses this email yet (case-insensitive). */
    public function isEmailAvailable(string $email): bool
    {
        return ! User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->exists();
    }

    public function register(array $data): User
    {
        /** Public sign-up is always a standard user — admin roles are never granted here. */
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $roleName = 'user';
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user->assignRole($roleName);

        // Used by both web sign-up and the pos-desktop register API. Emails the verification
        // code when that automation is on (welcome follows once verified), else the welcome email.
        app(EmailVerificationService::class)->startFor($user);

        return $user;
    }

    public function loginUser(User $user): void
    {
        Auth::login($user);
    }

    public function logout(): void
    {
        Auth::logout();
    }

    /**
     * "Cancel sign-up" from the business setup wizard. The account row was created at
     * register time, so it is deleted here — but only while it is still an empty shell
     * (no business owned or joined, no accounts, employee links, payments, or admin role).
     * Anything else is just signed out so no real data is ever lost.
     *
     * @return bool true when the account was deleted, false when it was only kept
     */
    public function cancelSignup(User $user): bool
    {
        if (! $this->isAbandonedSignup($user)) {
            return false;
        }

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        return true;
    }

    private function isAbandonedSignup(User $user): bool
    {
        // Admin-created logins (admins, affiliate marketers) never own a business, so they
        // must never look like an abandoned sign-up.
        if ($user->hasAnyRole(['admin', AffiliateService::ROLE])) {
            return false;
        }

        return ! $user->businesses()->exists()
            && ! $user->accounts()->exists()
            && ! $user->hrEmployees()->exists()
            && ! BusinessMember::where('user_id', $user->id)->exists()
            && ! Payment::where('user_id', $user->id)->exists();
    }
}
