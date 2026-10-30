<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessInvitation;
use Modules\Business\Models\BusinessMember;
use Modules\Business\Models\BusinessRole;
use Modules\Business\Services\BusinessInvitationService;
use Modules\HRManagement\Models\Employee;

/**
 * Lets an HR employee sign in to the business system (web + pos-desktop) as a team member.
 * Access goes through the normal team invitation, so the employee proves the email is theirs;
 * on accept, BusinessInvitationService links the employee row to the account.
 */
class EmployeeSystemAccessService
{
    public const STATUS_NONE = 'none';

    public const STATUS_INVITED = 'invited';

    public const STATUS_ACTIVE = 'active';

    public function __construct(private readonly BusinessInvitationService $invitations) {}

    /** Email the access is tied to: the linked account's, else the employee's personal email. */
    public function accessEmail(Employee $employee): string
    {
        $email = $employee->user_id !== null ? $employee->user?->email : null;

        return Str::lower(trim((string) ($email ?? $employee->personal_email)));
    }

    /**
     * @return array{status: string, email: string, role: ?string, role_name: ?string, member_id: ?int, member_status: ?string, invitation_id: ?int, invitation_expired: bool, sent_at: ?string}
     */
    public function status(Employee $employee): array
    {
        $this->syncExistingMemberLink($employee);

        $email      = $this->accessEmail($employee);
        $businessId = (int) $employee->business_id;
        $member     = $this->memberFor($employee);

        if ($member !== null) {
            return $this->payload(self::STATUS_ACTIVE, $email, $businessId, $member->role, [
                'member_id'     => (int) $member->id,
                'member_status' => $member->status,
            ]);
        }

        $invitation = $this->pendingInvitation($businessId, $email);
        if ($invitation !== null) {
            return $this->payload(self::STATUS_INVITED, $email, $businessId, $invitation->role, [
                'invitation_id'      => (int) $invitation->id,
                'invitation_expired' => $invitation->isExpired(),
                'sent_at'            => $invitation->last_sent_at?->toIso8601String(),
            ]);
        }

        return $this->payload(self::STATUS_NONE, $email, $businessId, null);
    }

    /**
     * Invite the employee with the given role, or change the role when they already have access.
     *
     * @return array{email_sent: bool}
     *
     * @throws ValidationException
     */
    public function grant(Employee $employee, Business $business, string $role, User $by): array
    {
        $this->syncExistingMemberLink($employee);

        $member = $this->memberFor($employee);
        if ($member !== null) {
            $member->update([
                'role'        => $role,
                'permissions' => $this->rolePermissions($business->id, $role),
            ]);

            return ['email_sent' => false];
        }

        if ($this->accessEmail($employee) === Str::lower((string) $business->user?->email)) {
            throw ValidationException::withMessages(['role' => 'This employee is the business owner and already has full access.']);
        }

        [, $sent] = $this->invitations->invite($business, $this->accessEmail($employee), $role, null, $by);

        return ['email_sent' => $sent];
    }

    /** Remove the employee from the team (or cancel the pending invite). The HR record and portal login stay. */
    public function revoke(Employee $employee): void
    {
        $this->memberFor($employee)?->delete();

        $invitation = $this->pendingInvitation((int) $employee->business_id, $this->accessEmail($employee));
        if ($invitation !== null) {
            $this->invitations->cancel($invitation);
        }
    }

    public function resend(Employee $employee): bool
    {
        $invitation = $this->pendingInvitation((int) $employee->business_id, $this->accessEmail($employee));
        if ($invitation === null) {
            throw ValidationException::withMessages(['invitation' => 'There is no pending invitation for this employee.']);
        }

        return $this->invitations->resend($invitation);
    }

    /**
     * Keep the team login in step when HR changes the employee's email (call before saving it):
     * a linked account gets the new email, a pending invitation is moved to the new address.
     * Returns 'login' | 'invitation' | null for what changed alongside the HR record.
     *
     * @throws ValidationException when the login can't follow the change
     */
    public function syncEmailChange(Employee $employee, Business $business, string $newEmail, User $by): ?string
    {
        $this->syncExistingMemberLink($employee);

        $newEmail = Str::lower(trim($newEmail));
        $oldEmail = $this->accessEmail($employee);
        if ($newEmail === $oldEmail) {
            return null;
        }

        $member     = $this->memberFor($employee);
        $invitation = $member === null ? $this->pendingInvitation((int) $business->id, $oldEmail) : null;
        if ($member === null && $invitation === null) {
            return null;
        }

        if (! $this->canManageAccess($business, $by)) {
            throw ValidationException::withMessages(['personal_email' => 'This employee has system access. Only the business owner or an admin can change their email.']);
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$newEmail])->where('id', '!=', (int) $employee->user_id)->exists()) {
            throw ValidationException::withMessages(['personal_email' => 'That email already belongs to another account.']);
        }

        if ($invitation !== null) {
            $this->invitations->cancel($invitation);
            $this->invitations->invite($business, $newEmail, $invitation->role, $invitation->permissions, $by);

            return 'invitation';
        }

        // The login is shared across businesses — only change it when this business is the account's only one
        $user          = $employee->user;
        $usedElsewhere = Business::query()->where('user_id', $user->id)->exists()
            || BusinessMember::query()->where('user_id', $user->id)->where('business_id', '!=', $business->id)->exists();
        if ($usedElsewhere) {
            throw ValidationException::withMessages(['personal_email' => "This employee's login is also used for another business, so its email can't be changed from here. Ask them to change it from their own profile."]);
        }

        $user->forceFill(['email' => $newEmail])->save();

        return 'login';
    }

    /**
     * Owner, or a member whose role has full access — mirrors who sees Settings → Users in pos-desktop.
     */
    public function canManageAccess(Business $business, User $user): bool
    {
        if ((int) $business->user_id === (int) $user->id) {
            return true;
        }

        $member = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($member === null) {
            return false;
        }

        return $member->role === 'admin'
            || BusinessRole::query()
                ->where('business_id', $business->id)
                ->where('slug', $member->role)
                ->whereNull('permissions')
                ->exists();
    }

    /**
     * Employees of this business keyed by linked user id — lets the Users list mark members who are employees.
     *
     * @return Collection<int, Employee>
     */
    public function employeesByUserId(Business $business): Collection
    {
        return Employee::query()
            ->where('business_id', $business->id)
            ->whereNotNull('user_id')
            ->with(['department:id,name', 'jobTitle:id,name'])
            ->get(['id', 'user_id', 'employee_id', 'full_name', 'department_id', 'job_title_id'])
            ->keyBy('user_id');
    }

    /**
     * Employees of this business keyed by lower-cased personal email — matches pending invitations.
     *
     * @return Collection<string, Employee>
     */
    public function employeesByEmail(Business $business): Collection
    {
        return Employee::query()
            ->where('business_id', $business->id)
            ->with(['department:id,name', 'jobTitle:id,name'])
            ->get(['id', 'user_id', 'employee_id', 'full_name', 'personal_email', 'department_id', 'job_title_id'])
            ->keyBy(fn (Employee $e) => Str::lower(trim((string) $e->personal_email)));
    }

    /**
     * Member user ids + pending invite emails of a business, for badging a whole employee list in two queries.
     *
     * @return array{member_user_ids: array<int, true>, invited_emails: array<string, true>}
     */
    public function accessLookup(Business $business): array
    {
        $memberIds = BusinessMember::query()->where('business_id', $business->id)->pluck('user_id');

        $invited = BusinessInvitation::query()
            ->where('business_id', $business->id)
            ->where('status', BusinessInvitation::STATUS_PENDING)
            ->pluck('email');

        return [
            'member_user_ids' => $memberIds->mapWithKeys(fn ($id) => [(int) $id => true])->all(),
            'invited_emails'  => $invited->mapWithKeys(fn ($e) => [Str::lower((string) $e) => true])->all(),
        ];
    }

    /** 'active' | 'invited' | 'none' from a prebuilt {@see accessLookup()} — no per-row queries. */
    public function quickStatus(Employee $employee, array $lookup): string
    {
        if ($employee->user_id !== null && isset($lookup['member_user_ids'][(int) $employee->user_id])) {
            return self::STATUS_ACTIVE;
        }

        $email = Str::lower(trim((string) $employee->personal_email));

        return isset($lookup['invited_emails'][$email]) ? self::STATUS_INVITED : self::STATUS_NONE;
    }

    /** Short shape attached to Users-list rows. */
    public function employeeTag(?Employee $employee): ?array
    {
        if ($employee === null) {
            return null;
        }

        return [
            'id'          => (int) $employee->id,
            'employee_id' => $employee->employee_id,
            'name'        => $employee->full_name,
            'department'  => $employee->department?->name,
            'job_title'   => $employee->jobTitle?->name,
        ];
    }

    /** Valid role slugs for the business (system + custom). */
    public function roleSlugs(Business $business): array
    {
        BusinessRole::seedForBusiness($business->id);

        return BusinessRole::query()->where('business_id', $business->id)->pluck('slug')->all();
    }

    /** An employee added under Settings → Users before HR linked them: attach the profile to that account. */
    private function syncExistingMemberLink(Employee $employee): void
    {
        if ($employee->user_id !== null) {
            return;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$this->accessEmail($employee)])->first();
        if ($user === null) {
            return;
        }

        $isMember = BusinessMember::query()
            ->where('business_id', $employee->business_id)
            ->where('user_id', $user->id)
            ->exists();

        if ($isMember) {
            $employee->forceFill(['user_id' => $user->id])->save();
            $employee->setRelation('user', $user);
        }
    }

    private function memberFor(Employee $employee): ?BusinessMember
    {
        if ($employee->user_id === null) {
            return null;
        }

        return BusinessMember::query()
            ->where('business_id', $employee->business_id)
            ->where('user_id', $employee->user_id)
            ->first();
    }

    private function pendingInvitation(int $businessId, string $email): ?BusinessInvitation
    {
        return BusinessInvitation::query()
            ->where('business_id', $businessId)
            ->where('email', $email)
            ->where('status', BusinessInvitation::STATUS_PENDING)
            ->latest('id')
            ->first();
    }

    private function rolePermissions(int $businessId, string $role): array
    {
        return BusinessRole::query()
            ->where('business_id', $businessId)
            ->where('slug', $role)
            ->value('permissions') ?? [];
    }

    private function payload(string $status, string $email, int $businessId, ?string $role, array $extra = []): array
    {
        $roleName = $role === null ? null : (BusinessRole::query()
            ->where('business_id', $businessId)
            ->where('slug', $role)
            ->value('name') ?? Str::headline($role));

        return array_merge([
            'status'             => $status,
            'email'              => $email,
            'role'               => $role,
            'role_name'          => $roleName,
            'member_id'          => null,
            'member_status'      => null,
            'invitation_id'      => null,
            'invitation_expired' => false,
            'sent_at'            => null,
        ], $extra);
    }
}
