<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Modules\Auth\Services\AuthService;
use Modules\HRManagement\Models\Employee;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function showLogin(): View
    {
        return view('auth::auth.login', ['googleAuthConfigured' => $this->googleOAuthConfigured()]);
    }

    public function showRegister(): View
    {
        return view('auth::auth.register', ['googleAuthConfigured' => $this->googleOAuthConfigured()]);
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
        if ($user !== null && $user->isHrPortalOnlyUser()) {
            $this->authService->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('hr.portal.login')
                ->withErrors(['email' => __('Employee accounts must sign in via the HR portal.')])
                ->onlyInput('email');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function register(Request $request): RedirectResponse
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
        ]);

        $user = $this->authService->register($data);
        $this->authService->loginUser($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
