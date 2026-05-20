<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\HRManagement\Models\Employee;

class EmployeePortalService
{
    public const SESSION_PORTAL_EMPLOYEE_ID = 'hr_portal_employee_id';

    /**
     * Sync email matches, then resolve the active employee (session or first linked).
     */
    public function linkAndResolve(User $user): ?Employee
    {
        $this->syncEmailLinks($user);

        return $this->resolveActiveEmployee($user);
    }

    /**
     * All employee rows linked to this user (HR portal employers).
     *
     * @return Collection<int, Employee>
     */
    public function linkedEmployeesForUser(User $user): Collection
    {
        return Employee::query()
            ->where('user_id', $user->id)
            ->with('business')
            ->orderBy('id')
            ->get();
    }

    /**
     * Persist which employer row is active for the HR portal session.
     */
    public function setPortalEmployee(User $user, int $employeeId): bool
    {
        $allowed = Employee::query()
            ->where('user_id', $user->id)
            ->whereKey($employeeId)
            ->exists();

        if (! $allowed) {
            return false;
        }

        session([self::SESSION_PORTAL_EMPLOYEE_ID => $employeeId]);

        return true;
    }

    /**
     * Link every employee row whose personal email matches this login (case-insensitive).
     */
    protected function syncEmailLinks(User $user): void
    {
        $email = strtolower(trim($user->email));
        if ($email === '') {
            return;
        }

        Employee::query()
            ->whereNull('user_id')
            ->whereRaw('LOWER(personal_email) = ?', [$email])
            ->each(function (Employee $row) use ($user): void {
                $row->forceFill(['user_id' => $user->id])->save();
            });
    }

    protected function resolveActiveEmployee(User $user): ?Employee
    {
        $employees = $this->linkedEmployeesForUser($user);
        if ($employees->isEmpty()) {
            return null;
        }

        $sessionId = session()->get(self::SESSION_PORTAL_EMPLOYEE_ID);
        if ($sessionId !== null) {
            $match = $employees->firstWhere('id', (int) $sessionId);
            if ($match !== null) {
                return $match;
            }
        }

        $first = $employees->first();
        session([self::SESSION_PORTAL_EMPLOYEE_ID => $first->id]);

        return $first;
    }

    public function requireEmployee(User $user): Employee
    {
        $employee = $this->linkAndResolve($user);
        if ($employee === null) {
            abort(403, 'No employee profile for this account.');
        }

        return $employee;
    }
}
