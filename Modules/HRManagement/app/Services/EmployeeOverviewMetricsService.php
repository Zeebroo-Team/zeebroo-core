<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Models\Employee;

/** Snapshot metrics for employee profile Overview (enrich when Leave / Payroll / Attendance ship). */
final class EmployeeOverviewMetricsService
{
    /**
     * @return array{
     *   leave:array{title:string,value:string,suffix:?string,hint:string,accent:string},
     *   attendance:array{title:string,value:string,suffix:?string,hint:string,accent:string},
     *   salary:array{title:string,value:string,suffix:?string,hint:string,accent:string},
     *   deductions:array{title:string,value:string,suffix:?string,hint:string,accent:string,detail:?string},
     * }
     */
    public function forEmployee(Business $business, Employee $employee): array
    {
        $employee->loadMissing(['department', 'employeeAllowances.allowanceType']);

        $currency = trim((string) get_settings('business.currency', '', $business));
        $prefix = $currency !== '' ? $currency.' ' : '';
        $dept = $employee->department;
        $salMin = $dept instanceof Department && $dept->salary_range_min !== null ? (float) $dept->salary_range_min : null;
        $salMax = $dept instanceof Department && $dept->salary_range_max !== null ? (float) $dept->salary_range_max : null;

        $salaryPrimary = __('Not recorded');
        $salaryHint = __('Add compensation on hire or when employee editing ships.');
        if ($employee->salary !== null) {
            $salaryPrimary = $prefix.$this->money((float) $employee->salary);
            $salaryHint = __('Monthly gross on file.');
            if ($employee->basic_salary !== null) {
                $salaryHint .= ' '.__('Basic: :amount.', ['amount' => $prefix.$this->money((float) $employee->basic_salary)]);
            }
            if ($employee->employeeAllowances->isNotEmpty()) {
                $salaryHint .= ' '.__('Includes recorded allowances.');
            }
            if ($dept instanceof Department && ($salMin !== null || $salMax !== null)) {
                $salaryHint .= ' '.__('Department band is indicative only.');
            }
        } elseif ($dept instanceof Department && ($salMin !== null || $salMax !== null)) {
            if ($salMin !== null && $salMax !== null) {
                $salaryPrimary = $prefix.$this->money($salMin).' – '.$this->money($salMax);
            } elseif ($salMin !== null) {
                $salaryPrimary = __('from').' '.$prefix.$this->money($salMin);
            } elseif ($salMax !== null) {
                $salaryPrimary = __('up to').' '.$prefix.$this->money($salMax);
            }
            $salaryHint = __('Department salary guide · not this person\'s actual pay.');
        } elseif (! $dept instanceof Department) {
            $salaryHint = __('Assign a department under Employment for a departmental guide.');
        }

        $presentStatutoryLabels = [];
        if (filled($employee->epf_number)) {
            $presentStatutoryLabels[] = 'EPF';
        }
        if (filled($employee->etf_number)) {
            $presentStatutoryLabels[] = 'ETF';
        }
        if (filled($employee->tax_tin)) {
            $presentStatutoryLabels[] = 'TIN';
        }

        $monthLabel = now()->translatedFormat('F Y');


        return [
            'leave' => [
                'title' => __('Leave taken'),
                'value' => '0',
                'suffix' => __('days'),
                'hint' => __('Approved leave · year-to-date (ledger arriving soon).'),
                'accent' => 'var(--accent-leave,#0d9488)',
            ],
            'attendance' => [
                'title' => __('Attendance'),
                'value' => '—',
                'suffix' => null,
                'hint' => __(':month · biometric or timesheets not connected yet.', ['month' => $monthLabel]),
                'accent' => 'var(--accent-attendance,#0369a1)',
            ],
            'salary' => [
                'title' => __('Salary snapshot'),
                'value' => $salaryPrimary,
                'suffix' => null,
                'hint' => $salaryHint,
                'accent' => 'var(--accent-salary,#b45309)',
            ],
            'deductions' => [
                'title' => __('Deductions'),
                'value' => $presentStatutoryLabels === [] ? '—' : (string) count($presentStatutoryLabels),
                'suffix' => $presentStatutoryLabels !== [] ? __('references') : null,
                'hint' => __('Statutory & payroll withholdings'),
                'detail' => $presentStatutoryLabels !== [] ? implode(' · ', $presentStatutoryLabels) : __('No statutory refs on file.'),
                'accent' => 'var(--accent-ded,#6d28d9)',
            ],
        ];
    }

    private function money(float $n): string
    {
        $whole = abs($n - round($n)) < 0.0001;

        return number_format($n, $whole ? 0 : 2);
    }
}
