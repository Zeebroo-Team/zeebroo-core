<?php

namespace Modules\Pos\Listeners;

use Illuminate\Auth\Events\Logout;
use Modules\Pos\Services\PosWebAppHandoffService;

class RevokePosWebAppTokens
{
    public function __construct(private PosWebAppHandoffService $handoff) {}

    /**
     * Signing out of the website also signs out the POS web app tabs that were
     * opened from it ("Continue to Dashboard"). Runs before the controller
     * invalidates the session, so the session id is still the old one.
     */
    public function handle(Logout $event): void
    {
        if ($event->guard !== 'web' || ! app()->bound('session')) {
            return;
        }

        $sessionId = session()->getId();
        if ($sessionId) {
            $this->handoff->revokeForSession($sessionId);
        }
    }
}
