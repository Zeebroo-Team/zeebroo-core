<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Illuminate\Support\Str;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;

/**
 * Settings → Users places a team member in a department and position without the HR module.
 * The member gets an incomplete employee profile (name, email, department, position, login)
 * that HR can complete later; an existing HR profile is moved instead of duplicated.
 */
class UserEmployeeLinkService
{
    private const ID_PREFIX = 'EMP-';

    private const FIRST_NUMBER = 1001;

    /**
     * Set the department and position of the member's employee profile, creating an incomplete
     * one when there is none. Nothing is created for a member placed in neither.
     */
    public function assign(
        Business $business,
        string $email,
        ?int $userId,
        string $name,
        ?int $departmentId,
        ?int $jobTitleId,
    ): ?Employee {
        $employee = $this->find($business, $email, $userId);

        if ($employee === null) {
            if ($departmentId === null && $jobTitleId === null) {
                return null;
            }

            $employee = new Employee();
            $employee->business_id = $business->id;
            $employee->fill([
                'user_id'          => $userId,
                'profile_complete' => false,
                'full_name'        => $name,
                'personal_email'   => Str::lower(trim($email)),
                'employee_id'      => $this->nextEmployeeId($business),
                'department_id'    => $departmentId,
                'job_title_id'     => $jobTitleId,
                'date_of_joining'  => now()->toDateString(),
            ])->save();

            return $employee;
        }

        $employee->forceFill([
            'department_id' => $departmentId,
            'job_title_id'  => $jobTitleId,
            'user_id'       => $employee->user_id ?? $userId,
        ])->save();

        return $employee;
    }

    /**
     * A member removed from the team loses the incomplete profile Settings → Users gave them,
     * so they drop out of their department. A profile HR completed stays: it is an HR record.
     */
    public function removeIncomplete(Business $business, int $userId): void
    {
        Employee::query()
            ->where('business_id', $business->id)
            ->where('user_id', $userId)
            ->where('profile_complete', false)
            ->delete();
    }

    /** A cancelled invitation drops the incomplete profile still waiting for that email. */
    public function removeIncompleteInvite(Business $business, string $email): void
    {
        Employee::query()
            ->where('business_id', $business->id)
            ->whereNull('user_id')
            ->where('profile_complete', false)
            ->whereRaw('LOWER(personal_email) = ?', [Str::lower(trim($email))])
            ->delete();
    }

    /** The member's employee profile: the one linked to the login, else the one with their email. */
    public function find(Business $business, string $email, ?int $userId): ?Employee
    {
        if ($userId !== null) {
            $linked = Employee::query()
                ->where('business_id', $business->id)
                ->where('user_id', $userId)
                ->orderBy('id')
                ->first();
            if ($linked !== null) {
                return $linked;
            }
        }

        return Employee::query()
            ->where('business_id', $business->id)
            ->whereNull('user_id')
            ->whereRaw('LOWER(personal_email) = ?', [Str::lower(trim($email))])
            ->orderBy('id')
            ->first();
    }

    /** EMP-1001, EMP-1002, … — one past the highest number already used in the business. */
    public function nextEmployeeId(Business $business): string
    {
        $highest = Employee::query()
            ->where('business_id', $business->id)
            ->where('employee_id', 'like', self::ID_PREFIX . '%')
            ->pluck('employee_id')
            ->map(fn (string $id) => (int) substr($id, strlen(self::ID_PREFIX)))
            ->max();

        return self::ID_PREFIX . max(self::FIRST_NUMBER, ((int) $highest) + 1);
    }
}
