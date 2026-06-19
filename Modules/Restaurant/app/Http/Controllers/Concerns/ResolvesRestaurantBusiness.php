<?php

namespace Modules\Restaurant\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;

trait ResolvesRestaurantBusiness
{
    protected function requireBusiness(Request $request): Business|RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());

        if (! $business instanceof Business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select a business first.']);
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);

        return $business;
    }
}
