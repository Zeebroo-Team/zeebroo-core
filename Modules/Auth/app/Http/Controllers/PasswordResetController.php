<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Modules\Auth\Services\PasswordResetService;
use Modules\Mail\Services\AutomatedEmailService;
use Throwable;

class PasswordResetController extends Controller
{
    private const SESSION_EMAIL = 'pwd_reset_email';

    public function __construct(
        private readonly PasswordResetService $passwords,
        private readonly AutomatedEmailService $automated,
    ) {}

    public function showRequest(): View
    {
        return view('auth::auth.forgot-password');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        try {
            $this->passwords->sendCode($data['email']);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['email' => __('We couldn\'t send the email right now. Please try again in a few minutes.')]);
        }

        session([self::SESSION_EMAIL => $data['email']]);

        return redirect()->route('password.reset')
            ->with('status', __('If an account exists for that email, we\'ve sent a 6-digit code to it.'));
    }

    public function showReset(): View|RedirectResponse
    {
        if (! session()->has(self::SESSION_EMAIL)) {
            return redirect()->route('password.request');
        }

        return view('auth::auth.reset-password', [
            'email' => session(self::SESSION_EMAIL),
            'minutes' => $this->automated->otpMinutes(),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $email = session(self::SESSION_EMAIL);
        if (! $email) {
            return redirect()->route('password.request');
        }

        $data = $request->validate([
            'otp' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->passwords->reset($email, $data['otp'], $data['password']);

        session()->forget(self::SESSION_EMAIL);

        return redirect()->route('login')
            ->withInput(['email' => $email])
            ->with('status', __('Your password has been reset. Sign in with your new password.'));
    }
}
