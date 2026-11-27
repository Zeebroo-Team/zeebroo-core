<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Modules\Auth\Models\AffiliateReferral;
use Modules\Auth\Services\AffiliateService;
use Modules\Auth\Services\AuthService;
use Modules\Auth\Services\ImpersonationService;
use Modules\HRManagement\Models\Employee;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function showLogin(): View
    {
        return view('auth::auth.login', ['googleAuthConfigured' => $this->googleOAuthConfigured()]);
    }

    public function showRegister(Request $request, AffiliateService $affiliates): View
    {
        // Opened from an affiliate's shared link (/register?ref=CODE): remember the code
        // so it also applies to "Sign up with Google" and to a sign-up later on.
        $affiliates->rememberFromRequest($request);

        return view('auth::auth.register', [
            'googleAuthConfigured' => $this->googleOAuthConfigured(),
            'referralCode' => $affiliates->pendingCode($request),
        ]);
    }

    private function googleOAuthConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! $this->authService->attemptLogin($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            $this->authService->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => __('Your account has been disabled. Contact an administrator.')])
                ->onlyInput('email');
        }

        if ($user !== null) {
            UserActivityLog::record($user, UserActivityLog::PLATFORM_WEB, UserActivityLog::EVENT_LOGIN, $request);
        }

        if ($user !== null && $user->isHrPortalOnlyUser()) {
            $this->authService->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('hr.portal.login')
                ->withErrors(['email' => __('Employee accounts must sign in via the HR portal.')])
                ->onlyInput('email');
        }

        if ($user !== null && $user->hasRole('admin')) {
            return redirect()->intended(route('admin.panel'));
        }

        if ($user !== null && $user->hasRole(AffiliateService::ROLE)) {
            return redirect()->intended(route('affiliate.dashboard'));
        }

        return redirect()->intended(route('dashboard'));
    }

    public function register(Request $request, AffiliateService $affiliates): RedirectResponse
    {
        // Check employee email before running unique validation so provisioned accounts don't get blocked.
        $rawEmail = $request->input('email', '');
        if ($rawEmail && Employee::whereRaw('LOWER(personal_email) = ?', [strtolower(trim($rawEmail))])->exists()) {
            session(['emp_verify_email' => $rawEmail]);

            return redirect()->route('register.employee-verify');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'confirmed'],
            'password' => ['required', Password::defaults()],
            'referral_code' => $affiliates->signupCodeRule(),
        ]);

        $user = $this->authService->register($data);
        $affiliates->recordReferral($user, $data['referral_code'] ?? null, AffiliateReferral::PLATFORM_WEB);
        $affiliates->forgetPending($request);
        UserActivityLog::record($user, UserActivityLog::PLATFORM_WEB, UserActivityLog::EVENT_REGISTER, $request);
        $this->authService->loginUser($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request, ImpersonationService $impersonation): RedirectResponse
    {
        // While an admin is impersonating, "Logout" returns them to their admin account
        // instead of ending the whole session.
        if ($impersonation->isImpersonating($request) && ($impersonated = $impersonation->stop($request))) {
            return redirect()->route('admin.users.show', $impersonated)
                ->with('status', __('Returned to your admin account.'));
        }

        $this->authService->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Cancel button on the business setup wizard: removes the just-created (still empty)
     * account, then ends the session. Non-empty accounts are only signed out.
     */
    public function cancelSignup(Request $request, ImpersonationService $impersonation): RedirectResponse
    {
        // An impersonating admin must never delete the target account from here.
        if ($impersonation->isImpersonating($request)) {
            return $this->logout($request, $impersonation);
        }

        $deleted = $this->authService->cancelSignup($request->user());

        $this->authService->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', $deleted
                ? __('Sign-up cancelled. Your account details were removed.')
                : __('You have been signed out.'));
    }
}
