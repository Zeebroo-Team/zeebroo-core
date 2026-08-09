<?php

namespace Modules\AdvertisingAgency\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;

trait ResolvesAgencyBusiness
{
    protected function requireBusiness(Request $request): Business|RedirectResponse
    {
        /** @var User $user */
        $user     = $request->user();
        $business = Business::currentForNavbar($user);

        if ($business === null) {
            return redirect()->route('home')->with('error', 'No business found.');
        }

        return $business;
    }
}
