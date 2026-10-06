<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Auth\Services\EmailVerificationService;
use Throwable;

/** Web "verify your email" page, opened from the reminder banner in the app layout. */
class EmailVerificationController extends Controller
{
    public function __construct(private readonly EmailVerificationService $verification) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! $this->verification->isPending($request->user())) {
            return redirect()->route('dashboard');
        }

        return view('auth::auth.verify-email', [
            'email' => $request->user()->email,
            'minutes' => $this->verification->expiryMinutes(),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['otp' => ['required', 'string', 'digits:6']]);

        $this->verification->verify($request->user(), $data['otp']);

        return redirect()->route('dashboard')->with('status', __('Your email has been verified.'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($wait = $this->verification->resendAvailableIn($user)) {
            return back()->withErrors(['otp' => __('Please wait :seconds seconds before requesting another code.', ['seconds' => $wait])]);
        }

        try {
            $this->verification->sendCode($user);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['otp' => __('We couldn\'t send the email right now. Please try again in a few minutes.')]);
        }

        return back()->with('status', __('A new code has been sent to your email.'));
    }
}
