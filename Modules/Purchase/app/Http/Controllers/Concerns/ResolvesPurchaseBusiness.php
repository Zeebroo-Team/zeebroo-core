<?php

namespace Modules\Purchase\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Account\Models\Account;
use Modules\Business\Models\Business;

trait ResolvesPurchaseBusiness
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

    protected function businessHasCurrentAccount(Business $business, Request $request): bool
    {
        return Account::query()
            ->where('user_id', $request->user()->id)
            ->where('business_id', $business->id)
            ->whereHas('bankType', fn ($query) => $query->where('slug', 'current-account'))
            ->exists();
    }

    /**
     * @return Collection<int, Account>
     */
    protected function accountsForPurchasePayment(Business $business, Request $request): Collection
    {
        return Account::query()
            ->with(['bankType', 'bank', 'warehouse'])
            ->where('user_id', $request->user()->id)
            ->where('business_id', $business->id)
            ->orderBy('account_name')
            ->get();
    }
}
