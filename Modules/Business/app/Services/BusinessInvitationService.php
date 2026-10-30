<?php

namespace Modules\Business\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessInvitation;
use Modules\Business\Models\BusinessMember;
use Modules\Business\Models\BusinessRole;
use Modules\HRManagement\Models\Employee;
use Modules\Mail\Mail\AdminMarketingMail;
use Modules\Mail\Services\AutomatedEmailService;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Team invitations by email. Works for people with and without a Zeebroo account:
 * the emailed link opens /invitations/{token}, where an existing user accepts and a
 * new person sets their name + password. Membership is only created on accept.
 */
class BusinessInvitationService
{
    public const EXPIRY_DAYS = 7;

    public function __construct(private readonly AutomatedEmailService $automated) {}

    /**
     * Create (or refresh) an invitation and email it. Re-inviting a pending email
     * updates its role/permissions and sends a new link instead of erroring.
     *
     * @return array{0: BusinessInvitation, 1: bool} invitation, whether the email was sent
     *
     * @throws ValidationException
     */
    public function invite(Business $business, string $email, string $role, ?array $permissions, User $inviter): array
    {
        $email = Str::lower(trim($email));

        if (Str::lower((string) $business->user?->email) === $email) {
            throw ValidationException::withMessages(['email' => 'This user is the business owner and already has full access.']);
        }

        $existingUser = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existingUser && BusinessMember::query()->where('business_id', $business->id)->where('user_id', $existingUser->id)->exists()) {
            throw ValidationException::withMessages(['email' => 'This user is already a member of your business.']);
        }

        // If no permissions explicitly set, inherit from role template
        if ($permissions === null) {
            $permissions = BusinessRole::query()
                ->where('business_id', $business->id)
                ->where('slug', $role)
                ->value('permissions') ?? [];
        }

        $invitation = BusinessInvitation::query()
            ->where('business_id', $business->id)
            ->where('email', $email)
            ->where('status', BusinessInvitation::STATUS_PENDING)
            ->first() ?? new BusinessInvitation([
                'business_id' => $business->id,
                'email' => $email,
                'status' => BusinessInvitation::STATUS_PENDING,
            ]);

        $invitation->fill([
            'role' => $role,
            'permissions' => $permissions,
            'invited_by' => $inviter->id,
        ]);

        return [$invitation, $this->issueAndSend($invitation, $business, $existingUser)];
    }

    /** New link + new expiry, then email it again. Returns whether the email was sent. */
    public function resend(BusinessInvitation $invitation): bool
    {
        $existingUser = User::query()->whereRaw('LOWER(email) = ?', [$invitation->email])->first();

        return $this->issueAndSend($invitation, $invitation->business, $existingUser);
    }

    public function cancel(BusinessInvitation $invitation): void
    {
        $invitation->update(['status' => BusinessInvitation::STATUS_CANCELLED]);
    }

    public function findByToken(string $token): ?BusinessInvitation
    {
        return BusinessInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->with('business.user')
            ->first();
    }

    public function existingUserFor(BusinessInvitation $invitation): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [$invitation->email])->first();
    }

    /**
     * Join the business. A new person's account is created here — the link proved they own
     * the address, so the email is marked verified.
     *
     * @param  array{name: string, password: string}|null  $newAccount  required when no account exists
     * @param  string|null  $name  name entered on the invitation page — renames an existing account
     */
    public function accept(BusinessInvitation $invitation, ?array $newAccount = null, ?string $name = null): User
    {
        $created = false;

        $user = DB::transaction(function () use ($invitation, $newAccount, $name, &$created) {
            $invitation = BusinessInvitation::query()->lockForUpdate()->findOrFail($invitation->id);

            if (! $invitation->isOpen()) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is no longer valid.']);
            }

            $user = $this->existingUserFor($invitation);

            if ($user === null) {
                if ($newAccount === null) {
                    throw ValidationException::withMessages(['password' => 'Choose a password to create your account.']);
                }

                $user = User::create([
                    'name' => $newAccount['name'],
                    'email' => $invitation->email,
                    'password' => $newAccount['password'],
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();

                Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
                $user->assignRole('user');
                $created = true;
            } elseif ($name !== null && trim($name) !== '') {
                $user->update(['name' => trim($name)]);
            }

            BusinessMember::query()->firstOrCreate(
                ['business_id' => $invitation->business_id, 'user_id' => $user->id],
                [
                    'role' => $invitation->role,
                    'permissions' => $invitation->permissions ?? [],
                    'status' => 'active',
                    'invited_by' => $invitation->invited_by,
                ],
            );

            // HR → "Give system access" invites go to the employee's email: link that profile to the account.
            if (Schema::hasTable('hr_employees')) {
                Employee::query()
                    ->where('business_id', $invitation->business_id)
                    ->whereNull('user_id')
                    ->whereRaw('LOWER(personal_email) = ?', [$invitation->email])
                    ->update(['user_id' => $user->id]);
            }

            $invitation->update([
                'status' => BusinessInvitation::STATUS_ACCEPTED,
                'accepted_by' => $user->id,
                'accepted_at' => now(),
            ]);

            return $user;
        });

        if ($created) {
            $this->automated->queueWelcome($user);
        }

        return $user;
    }

    public function roleName(BusinessInvitation $invitation): string
    {
        return BusinessRole::query()
            ->where('business_id', $invitation->business_id)
            ->where('slug', $invitation->role)
            ->value('name') ?? Str::headline($invitation->role);
    }

    /** Shape returned to the desktop client alongside members in GET /users. */
    public function payload(BusinessInvitation $invitation): array
    {
        $existingUser = $this->existingUserFor($invitation);

        return [
            'id' => (int) $invitation->id,
            'email' => $invitation->email,
            'name' => $existingUser?->name,
            'role' => $invitation->role,
            'role_name' => $this->roleName($invitation),
            'permissions' => $invitation->permissions ?? [],
            'status' => $invitation->isExpired() ? 'expired' : $invitation->status,
            'has_account' => $existingUser !== null,
            'invited_by' => $invitation->invitedBy?->name,
            'sent_at' => $invitation->last_sent_at?->toIso8601String(),
            'expires_at' => $invitation->expires_at?->toIso8601String(),
        ];
    }

    private function issueAndSend(BusinessInvitation $invitation, Business $business, ?User $existingUser): bool
    {
        $token = Str::random(48);

        $invitation->fill([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(self::EXPIRY_DAYS),
            'last_sent_at' => now(),
        ])->save();

        $roleName = $this->roleName($invitation);
        $inviter = $invitation->invitedBy?->name ?? $business->user?->name ?? $business->name;

        $html = view('business::emails.invitation', [
            'businessName' => $business->name,
            'roleName' => $roleName,
            'inviterName' => $inviter,
            'email' => $invitation->email,
            'name' => $existingUser?->name,
            'hasAccount' => $existingUser !== null,
            'url' => route('business.invitations.show', $token),
            'expiryDays' => self::EXPIRY_DAYS,
            'appName' => (string) config('app.name'),
        ])->render();

        try {
            Mail::to($invitation->email)->send(new AdminMarketingMail(
                "{$inviter} invited you to join {$business->name} on ".config('app.name'),
                $html,
            ));
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }
}
