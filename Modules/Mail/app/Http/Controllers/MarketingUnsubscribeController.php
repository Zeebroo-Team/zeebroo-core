<?php

namespace Modules\Mail\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Signed-link unsubscribe for admin marketing emails. GET shows a confirm page (so link
 * scanners that prefetch URLs don't unsubscribe people); POST performs it and also
 * serves RFC 8058 one-click requests from mail clients.
 */
class MarketingUnsubscribeController extends Controller
{
    public function show(Request $request, User $user): View
    {
        return view('mail::admin.marketing.unsubscribe', [
            'user' => $user,
            'done' => $user->marketing_opt_out_at !== null,
            'actionUrl' => $request->fullUrl(),
        ]);
    }

    public function store(User $user): View
    {
        if ($user->marketing_opt_out_at === null) {
            $user->forceFill(['marketing_opt_out_at' => now()])->save();
        }

        return view('mail::admin.marketing.unsubscribe', [
            'user' => $user,
            'done' => true,
            'actionUrl' => null,
        ]);
    }
}
