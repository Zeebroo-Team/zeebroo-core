<?php

namespace Modules\FileManager\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;

trait ResolvesFileManagerBusiness
{
    protected function requireBusiness(Request $request): Business|RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (!$business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select or create a business first.']);
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);

        return $business;
    }
}
