<?php

namespace Modules\HRManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Account\Models\Account;
use Modules\Business\Models\Business;
use Modules\HRManagement\Services\DepartmentEmployeeGrowthChartService;
use Modules\HRManagement\Services\HrHubInboxService;
use Modules\HRManagement\Services\HrHubSummaryService;
use Modules\HRManagement\Services\HrPayrollSettingsService;
use Modules\HRManagement\Services\HrUpcomingBirthdaysService;

class HRManagementController extends Controller
{
    public function __construct(
        private readonly HrPayrollSettingsService $hrPayrollSettings,
        private readonly HrHubSummaryService $hrHubSummary,
        private readonly DepartmentEmployeeGrowthChartService $employeeGrowthChart,
        private readonly HrUpcomingBirthdaysService $hrUpcomingBirthdays,
        private readonly HrHubInboxService $hrHubInbox,
    ) {}

    public function index(Request $request): RedirectResponse|View
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        $bandLabels = [
            '1_10' => '1–10 employees',
            '10_50' => '10–50 employees',
            '50_100' => '50–100 employees',
            '100_500' => '100–500 (enterprise)',
        ];

        return view('hrmanagement::index', [
            'business' => $business,
            'employeeBandLabel' => $bandLabels[$this->hrPayrollSettings->employeeCountBand($business) ?? ''] ?? null,
            'hrSummary' => $this->hrHubSummary->forBusiness($business),
            'headcountChart' => $this->employeeGrowthChart->buildCompanyHeadcountSeries($business),
            'upcomingBirthdays' => $this->hrUpcomingBirthdays->upcomingWithinDays($business),
            'upcomingBirthdaysWindowDays' => HrUpcomingBirthdaysService::DEFAULT_WINDOW_DAYS,
            'hrPendingLeaveCount' => $this->hrHubInbox->pendingLeaveRequestsCount($business),
            'hrInboxComplaints' => $this->hrHubInbox->openComplaints($business),
            'hrInboxEmployees' => $this->hrHubInbox->employeesForSelect($business),
        ]);
    }

    public function onboarding(Request $request): RedirectResponse|View
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if ($this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.index');
        }

        $accounts = Account::query()
            ->with(['bankType', 'bank', 'warehouse'])
            ->where('user_id', $request->user()->id)
            ->where('business_id', $business->id)
            ->orderBy('account_name')
            ->get();

        return view('hrmanagement::onboarding', [
            'business' => $business,
            'accounts' => $accounts,
            'bands' => HrPayrollSettingsService::EMPLOYEE_COUNT_BANDS,
            'bandLabels' => [
                '1_10' => '1–10',
                '10_50' => '10–50',
                '50_100' => '50–100',
                '100_500' => '100–500 (enterprise)',
            ],
            'defaults' => [
                'salary_account_id' => old(
                    'salary_account_id',
                    $this->hrPayrollSettings->salaryHandlingAccountId($business)
                ),
                'employee_count_band' => old(
                    'employee_count_band',
                    $this->hrPayrollSettings->employeeCountBand($business)
                ),
            ],
        ]);
    }

    public function declineSetup(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        $this->hrPayrollSettings->markDeclined($business);

        return redirect()->route('dashboard')->with('status', 'Understood — employee payroll stays outside SociBiz for this business.');
    }

    public function completeSetup(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $business = Business::currentForNavbar($user);
        abort_if($business === null, 403);

        if ($this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.index');
        }

        $validated = $request->validate([
            'salary_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q
                    ->where('user_id', $user->id)
                    ->where('business_id', $business->id)),
            ],
            'employee_count_band' => [
                'required',
                Rule::in(HrPayrollSettingsService::EMPLOYEE_COUNT_BANDS),
            ],
        ]);

        $this->hrPayrollSettings->markCompleted($business, [
            'salary_account_id' => (int) $validated['salary_account_id'],
            'employee_count_band' => (string) $validated['employee_count_band'],
        ]);

        return redirect()->route('hr.index')->with('status', 'Payroll setup saved. Employees and Payroll are now in your sidebar.');
    }
}
