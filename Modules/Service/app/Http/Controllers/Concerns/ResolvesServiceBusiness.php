<?php

namespace Modules\Service\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;

trait ResolvesServiceBusiness
{
    protected function requireBusiness(Request $request): Business|RedirectResponse
    {
        $business = $request->user()?->businesses()->first();

        if (!$business instanceof Business) {
            return redirect()->route('business.create')
                ->withErrors(['business' => 'Please create a business first.']);
        }

        return $business;
    }
}
