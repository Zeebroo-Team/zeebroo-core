<?php

declare(strict_types=1);

namespace Modules\HRManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\HrComplaint;
use Modules\HRManagement\Models\LeaveRequest;
use Modules\HRManagement\Services\EmployeePortalService;
use Modules\HRManagement\Services\HrPayrollSettingsService;

class HrEmployeePortalController extends Controller
{
    public function __construct(
        private readonly EmployeePortalService $employeePortal,
        private readonly HrPayrollSettingsService $hrPayrollSettings,
    ) {}

    public function showLogin(): View|RedirectResponse
    {
        $hasAccountButNoEmployee = false;
        if (Auth::check()) {
            $employee = $this->employeePortal->linkAndResolve(Auth::user());
            if ($employee !== null) {
                return redirect()->route('hr.portal.dashboard');
            }
            $hasAccountButNoEmployee = true;
        }

        return view('hrmanagement::portal.login', [
            'googleAuthConfigured' => $this->googleOAuthConfigured(),
            'hasAccountButNoEmployee' => $hasAccountButNoEmployee,
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            $employee = $this->employeePortal->linkAndResolve(Auth::user());

            return $employee !== null
                ? redirect()->route('hr.portal.dashboard')
                : redirect()->route('login')->with('status', __('You are already signed in. Use your workspace, or sign out to switch accounts.'));
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => __('These credentials do not match our records.')])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $employee = $this->employeePortal->linkAndResolve($user);

        if ($employee === null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors([
                    'email' => __('No employee profile is linked to this account. Sign in with the same email your HR team has on file, or ask them to connect your login.'),
                ])
                ->onlyInput('email');
        }

        return redirect()->intended(route('hr.portal.dashboard'));
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $employee = $this->employeePortal->linkAndResolve($user);
        if ($employee === null) {
            return redirect()->route('hr.portal.login')
                ->withErrors(['email' => __('Your session no longer has an employee profile.')]);
        }

        $employee->load(['business', 'department', 'jobTitle']);

        $business = $employee->business;
        if ($business === null || ! $this->hrPayrollSettings->optedIn($business)) {
            return view('hrmanagement::portal.unavailable', [
                'employee' => $employee,
                'heading' => __('HR portal'),
                'portalEmployeeChoices' => $this->employeePortal->linkedEmployeesForUser($user),
            ]);
        }

        $employee->load(['leaveRequests' => fn ($q) => $q->orderByDesc('created_at')->limit(20)]);

        $portalFeatures = $this->resolvePortalFeatures($employee);

        return view('hrmanagement::portal.dashboard', [
            'employee' => $employee,
            'heading' => __('HR portal'),
            'employeePortal' => true,
            'portalEmployerBusiness' => $business,
            'portalEmployee' => $employee,
            'portalEmployeeChoices' => $this->employeePortal->linkedEmployeesForUser($user),
            'portalFeatures' => $portalFeatures,
        ]);
    }

    public function switchEmployer(Request $request): RedirectResponse
    {
        $request->validate([
            'employee_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        if (! $this->employeePortal->setPortalEmployee($user, (int) $request->input('employee_id'))) {
            return back()->withErrors([
                'employer' => __('That employer is not available for your account.'),
            ]);
        }

        return back();
    }

    public function profile(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $employee = $this->employeePortal->linkAndResolve($user);
        if ($employee === null) {
            return redirect()->route('hr.portal.login');
        }

        $employee->load(['business', 'department', 'jobTitle', 'bank']);

        $business = $employee->business;
        if ($business === null || ! $this->hrPayrollSettings->optedIn($business)) {
            return view('hrmanagement::portal.unavailable', [
                'employee' => $employee,
                'heading' => __('HR portal'),
                'portalEmployeeChoices' => $this->employeePortal->linkedEmployeesForUser($user),
            ]);
        }

        return view('hrmanagement::portal.profile', [
            'employee' => $employee,
            'heading' => __('My profile'),
            'employeePortal' => true,
            'portalEmployerBusiness' => $business,
            'portalEmployee' => $employee,
            'portalEmployeeChoices' => $this->employeePortal->linkedEmployeesForUser($user),
        ]);
    }

    public function leaves(Request $request): View|RedirectResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return $gate;
        }

        /** @var array{user: User, employee: Employee, business: Business, choices: Collection} $gate */
        ['employee' => $employee, 'business' => $business, 'choices' => $choices] = $gate;

        if ($denied = $this->denyPortalFeature($employee, 'leaves')) {
            return $denied;
        }

        $leaveRequests = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('hrmanagement::portal.leaves', [
            'employee' => $employee,
            'leaveRequests' => $leaveRequests,
            'heading' => __('My leaves'),
            'employeePortal' => true,
            'portalEmployerBusiness' => $business,
            'portalEmployee' => $employee,
            'portalEmployeeChoices' => $choices,
        ]);
    }

    public function complaints(Request $request): View|RedirectResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return $gate;
        }

        /** @var array{user: User, employee: Employee, business: Business, choices: Collection} $gate */
        ['employee' => $employee, 'business' => $business, 'choices' => $choices] = $gate;

        if ($denied = $this->denyPortalFeature($employee, 'complaints')) {
            return $denied;
        }

        $complaints = HrComplaint::query()
            ->where('employee_id', $employee->id)
            ->where('business_id', $employee->business_id)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('hrmanagement::portal.complaints', [
            'employee' => $employee,
            'complaints' => $complaints,
            'heading' => __('Complaints'),
            'employeePortal' => true,
            'portalEmployerBusiness' => $business,
            'portalEmployee' => $employee,
            'portalEmployeeChoices' => $choices,
        ]);
    }

    public function storeComplaint(Request $request): View|RedirectResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return $gate;
        }

        /** @var array{user: User, employee: Employee, business: Business, choices: Collection} $gate */
        ['user' => $user, 'employee' => $employee] = $gate;

        if ($denied = $this->denyPortalFeature($employee, 'complaints')) {
            return $denied;
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        HrComplaint::query()->create([
            'business_id' => $employee->business_id,
            'employee_id' => $employee->id,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'status' => HrComplaint::STATUS_OPEN,
            'recorded_by_user_id' => $user->id,
        ]);

        return redirect()
            ->route('hr.portal.complaints')
            ->with('status', __('Your complaint has been submitted.'));
    }

    public function salary(Request $request): View|RedirectResponse
    {
        $gate = $this->assertPortalEmployerAvailable($request);
        if ($gate instanceof RedirectResponse || $gate instanceof View) {
            return $gate;
        }

        /** @var array{user: User, employee: Employee, business: Business, choices: Collection} $gate */
        ['employee' => $employee, 'business' => $business, 'choices' => $choices] = $gate;

        if ($denied = $this->denyPortalFeature($employee, 'salary')) {
            return $denied;
        }

        $employee->load(['employeeAllowances.allowanceType']);

        return view('hrmanagement::portal.salary', [
            'employee' => $employee,
            'heading' => __('My salary'),
            'employeePortal' => true,
            'portalEmployerBusiness' => $business,
            'portalEmployee' => $employee,
            'portalEmployeeChoices' => $choices,
        ]);
    }

    /**
     * @return array<string, mixed>|View|RedirectResponse
     */
    private function assertPortalEmployerAvailable(Request $request): array|View|RedirectResponse
    {
        $user = $request->user();
        $employee = $this->employeePortal->linkAndResolve($user);
        if ($employee === null) {
            return redirect()->route('hr.portal.login');
        }

        $choices = $this->employeePortal->linkedEmployeesForUser($user);
        $employee->loadMissing(['business', 'jobTitle']);
        $business = $employee->business;

        if ($business === null || ! $this->hrPayrollSettings->optedIn($business)) {
            return view('hrmanagement::portal.unavailable', [
                'employee' => $employee,
                'heading' => __('HR portal'),
                'portalEmployeeChoices' => $choices,
            ]);
        }

        return compact('user', 'employee', 'business', 'choices');
    }

    /** Returns null if allowed, or a redirect if the designation blocks this feature. */
    private function denyPortalFeature(Employee $employee, string $feature): ?RedirectResponse
    {
        $employee->loadMissing('jobTitle');

        if ($employee->jobTitle !== null && ! $employee->jobTitle->hasPortalFeature($feature)) {
            return redirect()->route('hr.portal.dashboard')
                ->withErrors(['access' => __('This feature is not available for your role.')]);
        }

        return null;
    }

    /** Returns a keyed bool array of which features are accessible for this employee. */
    private function resolvePortalFeatures(Employee $employee): array
    {
        $employee->loadMissing('jobTitle');

        return array_combine(
            \Modules\HRManagement\Models\JobTitle::PORTAL_FEATURES,
            array_map(
                fn (string $f) => $employee->jobTitle === null || $employee->jobTitle->hasPortalFeature($f),
                \Modules\HRManagement\Models\JobTitle::PORTAL_FEATURES
            )
        );
    }

    private function googleOAuthConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }
}
