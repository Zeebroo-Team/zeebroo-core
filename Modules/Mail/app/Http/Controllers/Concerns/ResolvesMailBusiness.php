<?php

namespace Modules\Mail\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessMember;

trait ResolvesMailBusiness
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

    /**
     * @return Collection<int, \App\Models\User>
     */
    protected function assignableUsers(Business $business): Collection
    {
        $memberUsers = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        return $memberUsers->push($business->user)->filter()->unique('id')->sortBy('name')->values();
    }
}
