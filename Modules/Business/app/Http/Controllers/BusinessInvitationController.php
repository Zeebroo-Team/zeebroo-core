<?php

namespace Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Modules\Business\Services\BusinessInvitationService;

/** Public page opened from the invitation email: accept, or set a password and join. */
class BusinessInvitationController extends Controller
{
    public function __construct(private readonly BusinessInvitationService $invitations) {}

    public function show(string $token): View
    {
        $invitation = $this->invitations->findByToken($token);
        $existingUser = $invitation ? $this->invitations->existingUserFor($invitation) : null;

        return view('business::invitations.show', [
            'token' => $token,
            'invitation' => $invitation,
            'roleName' => $invitation ? $this->invitations->roleName($invitation) : null,
            'hasAccount' => $existingUser !== null,
            // Prefilled with the account's current name; still editable.
            'accountName' => $existingUser?->name,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->invitations->findByToken($token);
        abort_if($invitation === null, 404);

        // Name is asked of everyone; a password only when there is no account yet.
        $isNew = $this->invitations->existingUserFor($invitation) === null;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => $isNew ? ['required', 'confirmed', Password::defaults()] : ['exclude'],
        ]);
        $newAccount = $isNew ? $data : null;

        $user = $this->invitations->accept($invitation, $newAccount, $data['name']);
        $businessName = $invitation->business->name;

        // New accounts are signed straight in; existing users sign in with their own password
        // unless they're already signed in as the invited account.
        if ($newAccount !== null || Auth::id() === $user->id) {
            if (Auth::id() !== $user->id) {
                Auth::login($user);
                $request->session()->regenerate();
            }

            return redirect()->route('dashboard')
                ->with('status', __('Welcome! You have joined :business.', ['business' => $businessName]));
        }

        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')
            ->withInput(['email' => $user->email])
            ->with('status', __('You have joined :business. Sign in to get started.', ['business' => $businessName]));
    }
}
