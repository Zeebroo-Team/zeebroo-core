<?php

namespace Modules\Pos\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Account\Models\Account;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Sale;
use Modules\Product\Models\Product;

trait ResolvesPosBusiness
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

    protected function saleForBusiness(Business $business, Sale $sale): Sale
    {
        abort_unless((int) $sale->business_id === (int) $business->id, 404);

        return $sale;
    }

    protected function productForBusiness(Business $business, Product $product): Product
    {
        abort_unless((int) $product->business_id === (int) $business->id, 404);

        return $product;
    }

    /**
     * @return Collection<int, Account>
     */
    protected function accountsForPosPayment(Business $business, Request $request): Collection
    {
        return Account::query()
            ->with(['bankType', 'bank', 'warehouse'])
            ->where('user_id', $request->user()->id)
            ->where('business_id', $business->id)
            ->orderBy('account_name')
            ->get();
    }
}
