<?php

namespace Modules\Service\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Service\Http\Controllers\Concerns\ResolvesServiceBusiness;
use Modules\Service\Models\ServiceDiscount;
use Modules\Service\Models\ServiceItem;

class ServiceDiscountController extends Controller
{
    use ResolvesServiceBusiness;

    public function store(Request $request, ServiceItem $serviceItem): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        abort_unless($serviceItem->business_id === $business->id, 404);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'discount_type'  => ['required', 'string', 'in:flat,percentage'],
            'discount_value' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'starts_at'      => ['nullable', 'date'],
            'ends_at'        => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $serviceItem->discounts()->create(array_merge($data, [
            'business_id' => $business->id,
        ]));

        return redirect()
            ->route('service.catalog.show', $serviceItem)
            ->with('status', 'Discount added.');
    }

    public function destroy(Request $request, ServiceItem $serviceItem, ServiceDiscount $discount): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        abort_unless($serviceItem->business_id === $business->id, 404);
        abort_unless($discount->service_item_id === $serviceItem->id, 404);

        $discount->delete();

        return redirect()
            ->route('service.catalog.show', $serviceItem)
            ->with('status', 'Discount removed.');
    }
}
