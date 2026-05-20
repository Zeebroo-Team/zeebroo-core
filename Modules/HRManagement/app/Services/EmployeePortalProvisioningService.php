<?php

namespace Modules\HRManagement\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;

class EmployeePortalProvisioningService
{
    /**
     * Create or link a login for the employee and return payload for the welcome email.
     *
     * @return array{scenario: string, temporary_password: ?string}
     */
    public function provisionPortalAccess(Employee $employee, Business $business): array
    {
        $email = Str::lower(trim($employee->personal_email));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $plain = Str::password(14, true, true, false, false);

            $user = User::query()->create([
                'name' => $employee->full_name,
                'email' => $email,
                'password' => $plain,
            ]);

            $employee->forceFill(['user_id' => $user->id])->save();

            return [
                'scenario' => 'new_credentials',
                'temporary_password' => $plain,
            ];
        }

        $user->loadMissing('hrEmployees');

        if ($user->hrEmployees->contains(fn (Employee $e) => $e->is($employee))) {
            return [
                'scenario' => 'noop',
                'temporary_password' => null,
            ];
        }

        if ($employee->user_id !== null && (int) $employee->user_id !== (int) $user->id) {
            return [
                'scenario' => 'email_conflict',
                'temporary_password' => null,
            ];
        }

        $employee->forceFill(['user_id' => $user->id])->save();

        if (filled($user->google_id)) {
            return [
                'scenario' => 'existing_google',
                'temporary_password' => null,
            ];
        }

        return [
            'scenario' => 'existing_password',
            'temporary_password' => null,
        ];
    }
}
