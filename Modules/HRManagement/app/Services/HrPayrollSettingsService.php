<?php

namespace Modules\HRManagement\Services;

use Modules\Business\Models\Business;
use Modules\Settings\Services\SettingsService;

class HrPayrollSettingsService
{
    public const SETTING_OPTED_IN = 'hr.payroll.opted_in';

    public const SETTING_DECLINED = 'hr.payroll.declined';

    public const SETTING_SALARY_ACCOUNT_ID = 'hr.payroll.salary_account_id';

    public const SETTING_EMPLOYEE_BAND = 'hr.payroll.employee_count_band';

    /** @var list<string> */
    public const EMPLOYEE_COUNT_BANDS = ['1_10', '10_50', '50_100', '100_500'];

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function optedIn(?Business $business): bool
    {
        if ($business === null) {
            return false;
        }

        return (bool) $this->settings->get($business, self::SETTING_OPTED_IN, false);
    }

    public function declined(?Business $business): bool
    {
        if ($business === null) {
            return false;
        }

        return (bool) $this->settings->get($business, self::SETTING_DECLINED, false);
    }

    public function markDeclined(Business $business): void
    {
        $this->settings->set($business, self::SETTING_DECLINED, true);
        $this->settings->set($business, self::SETTING_OPTED_IN, false);
        $this->settings->forget($business, self::SETTING_SALARY_ACCOUNT_ID);
        $this->settings->forget($business, self::SETTING_EMPLOYEE_BAND);
    }

    /** @param  array{salary_account_id: int, employee_count_band: string}  $data */
    public function markCompleted(Business $business, array $data): void
    {
        $this->settings->set($business, self::SETTING_DECLINED, false);
        $this->settings->set($business, self::SETTING_OPTED_IN, true);
        $this->settings->set($business, self::SETTING_SALARY_ACCOUNT_ID, (int) $data['salary_account_id']);
        $this->settings->set($business, self::SETTING_EMPLOYEE_BAND, (string) $data['employee_count_band']);
    }

    public function salaryHandlingAccountId(?Business $business): ?int
    {
        if ($business === null) {
            return null;
        }

        $v = $this->settings->get($business, self::SETTING_SALARY_ACCOUNT_ID, null);

        return $v !== null ? (int) $v : null;
    }

    public function employeeCountBand(?Business $business): ?string
    {
        if ($business === null) {
            return null;
        }

        $band = $this->settings->get($business, self::SETTING_EMPLOYEE_BAND, null);

        return is_string($band) && $band !== '' ? $band : null;
    }
}
