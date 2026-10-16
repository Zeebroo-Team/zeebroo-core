<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Auth\Services\ImpersonationService;

class ImpersonationController extends Controller
{
    public function __construct(private readonly ImpersonationService $impersonation) {}

    public function start(Request $request, User $user): RedirectResponse
    {
        if ($reason = $this->impersonation->cannotImpersonateReason($request->user(), $user)) {
            return redirect()->route('admin.users.show', $user)->withErrors(['impersonate' => $reason]);
        }

        $this->impersonation->start($request, $request->user(), $user);

        return redirect()->route('dashboard');
    }

    public function leave(Request $request): RedirectResponse
    {
        $impersonated = $this->impersonation->stop($request);

        if (! $impersonated) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('admin.users.show', $impersonated)
            ->with('status', __('Returned to your admin account.'));
    }
}
