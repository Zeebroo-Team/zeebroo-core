<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Modules\Auth\Mail\EmployeeVerifyOtpMail;
use Modules\HRManagement\Models\Employee;
use Spatie\Permission\Models\Role;

class EmployeeVerifyController extends Controller
{
    private const SESSION_EMAIL = 'emp_verify_email';
    private const SESSION_OTP   = 'emp_verify_otp';
    private const SESSION_EXP   = 'emp_verify_otp_expires';
    private const SESSION_DONE  = 'emp_verify_otp_done';

    public function showEmailConfirm(): View|RedirectResponse
    {
        if (! session()->has(self::SESSION_EMAIL)) {
            return redirect()->route('register');
        }

        return view('auth::auth.employee-verify', [
            'maskedEmail' => $this->maskEmail(session(self::SESSION_EMAIL, '')),
        ]);
    }

    public function submitEmail(Request $request): RedirectResponse
    {
        if (! session()->has(self::SESSION_EMAIL)) {
            return redirect()->route('register');
        }

        $request->validate(['email' => ['required', 'email']]);

        $stored  = strtolower(trim(session(self::SESSION_EMAIL, '')));
        $entered = strtolower(trim($request->input('email', '')));

        if ($stored !== $entered) {
            return back()->withErrors(['email' => __('That email does not match. Enter the exact email address you used.')]);
        }

        $otp = (string) random_int(100000, 999999);

        session([
            self::SESSION_OTP  => $otp,
            self::SESSION_EXP  => now()->addMinutes(10)->timestamp,
            self::SESSION_DONE => false,
        ]);

        Mail::to($stored)->send(new EmployeeVerifyOtpMail($otp));

        return redirect()->route('register.employee-verify.otp');
    }

    public function showOtp(): View|RedirectResponse
    {
        if (! session()->has(self::SESSION_EMAIL) || ! session()->has(self::SESSION_OTP)) {
            return redirect()->route('register');
        }

        return view('auth::auth.employee-verify-otp', [
            'maskedEmail' => $this->maskEmail(session(self::SESSION_EMAIL, '')),
        ]);
    }

    public function submitOtp(Request $request): RedirectResponse
    {
        if (! session()->has(self::SESSION_EMAIL) || ! session()->has(self::SESSION_OTP)) {
            return redirect()->route('register');
        }

        $request->validate(['otp' => ['required', 'string', 'digits:6']]);

        if (now()->timestamp > (int) session(self::SESSION_EXP, 0)) {
            return back()->withErrors(['otp' => __('The verification code has expired. Go back and request a new one.')]);
        }

        if ($request->input('otp') !== session(self::SESSION_OTP)) {
            return back()->withErrors(['otp' => __('Incorrect code. Check your email and try again.')]);
        }

        session([self::SESSION_DONE => true]);

        return redirect()->route('register.employee-verify.password');
    }

    public function showPassword(): View|RedirectResponse
    {
        if (! session()->get(self::SESSION_DONE)) {
            return redirect()->route('register');
        }

        return view('auth::auth.employee-verify-password');
    }

    public function submitPassword(Request $request): RedirectResponse
    {
        if (! session()->get(self::SESSION_DONE)) {
            return redirect()->route('register');
        }

        $email = session(self::SESSION_EMAIL);
        if (! $email) {
            return redirect()->route('register');
        }

        $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);

        $hashed = Hash::make($request->input('password'));
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $user = User::where('email', $email)->first();

        if ($user !== null) {
            // Account already exists (e.g. provisioned by HR admin) — update the password.
            $user->update(['password' => $hashed]);
        } else {
            $employee = Employee::whereRaw('LOWER(personal_email) = ?', [strtolower(trim($email))])->first();
            $name = $employee?->full_name ?? explode('@', $email)[0];

            $user = User::create([
                'name'     => $name,
                'email'    => $email,
                'password' => $hashed,
            ]);
        }

        if (! $user->hasRole('user')) {
            $user->assignRole('user');
        }

        session()->forget([self::SESSION_EMAIL, self::SESSION_OTP, self::SESSION_EXP, self::SESSION_DONE]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return $email;
        }
        [$local, $domain] = $parts;
        if (strlen($local) <= 2) {
            return $email;
        }

        return str_repeat('*', strlen($local) - 2) . substr($local, -2) . '@' . $domain;
    }
}
