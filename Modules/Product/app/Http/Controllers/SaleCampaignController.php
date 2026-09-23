<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Product\Http\Controllers\Concerns\ResolvesProductBusiness;
use Modules\Product\Models\SaleCampaign;
use Modules\Product\Services\SaleCampaignService;

class SaleCampaignController extends Controller
{
    use ResolvesProductBusiness;

    public function __construct(private readonly SaleCampaignService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $campaigns = $this->service->list($business, $search, $status);

        $products = $business->products()
            ->where('is_active', true)
            ->select('id', 'name', 'sku', 'unit_price')
            ->orderBy('name')
            ->get();

        $viewErrors = $request->session()->get('errors');
        $modalOpen  = $campaigns->isNotEmpty()
            && $viewErrors !== null
            && $viewErrors->any()
            && ! $viewErrors->has('campaign');

        return view('product::campaigns.index', [
            'business'  => $business,
            'campaigns' => $campaigns,
            'products'  => $products,
            'search'    => $search,
            'status'    => $status,
            'modalOpen' => $modalOpen,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $this->validated($request, $business);
        $this->service->create($business, $data);

        return redirect()->route('product.campaigns.index')->with('status', 'Campaign created.');
    }

    public function edit(Request $request, SaleCampaign $campaign): View|RedirectResponse
    {
        $business = $this->requireCampaign($request, $campaign);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $campaign->load(['items.product']);

        $products = $business->products()
            ->where('is_active', true)
            ->select('id', 'name', 'sku', 'unit_price')
            ->orderBy('name')
            ->get();

        return view('product::campaigns.edit', [
            'business' => $business,
            'campaign' => $campaign,
            'products' => $products,
        ]);
    }

    public function update(Request $request, SaleCampaign $campaign): RedirectResponse
    {
        $business = $this->requireCampaign($request, $campaign);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $this->validated($request, $business, $campaign);
        $this->service->update($campaign, $data);

        return redirect()->route('product.campaigns.index')->with('status', 'Campaign updated.');
    }

    public function destroy(Request $request, SaleCampaign $campaign): RedirectResponse
    {
        $business = $this->requireCampaign($request, $campaign);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->service->delete($campaign);

        return redirect()->route('product.campaigns.index')->with('status', 'Campaign deleted.');
    }

    private function requireCampaign(Request $request, SaleCampaign $campaign): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->service->campaignForBusiness($business, $campaign) instanceof SaleCampaign, 404);

        return $business;
    }

    private function validated(Request $request, Business $business, ?SaleCampaign $campaign = null): array
    {
        $data = $request->validate([
            'name'                     => ['required', 'string', 'max:191'],
            'description'              => ['nullable', 'string', 'max:2000'],
            'mode'                     => ['required', 'string', Rule::in(['storewide', 'individual'])],
            'discount_type'            => [Rule::requiredIf(fn () => $request->input('mode') === 'storewide'), 'nullable', 'string', Rule::in(['flat', 'percentage'])],
            'discount_value'           => [Rule::requiredIf(fn () => $request->input('mode') === 'storewide'), 'nullable', 'numeric', 'min:0.01', 'max:999999'],
            'is_long_term'             => ['nullable', 'boolean'],
            'starts_at'                => ['nullable', 'date'],
            'ends_at'                  => [Rule::requiredIf(fn () => ! $request->boolean('is_long_term')), 'nullable', 'date', 'after_or_equal:starts_at'],
            'items'                    => [Rule::requiredIf(fn () => $request->input('mode') === 'individual'), 'nullable', 'array'],
            'items.*.product_id'      => ['required_with:items', 'integer', Rule::exists('products', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'items.*.discount_type'    => ['required_with:items', 'string', Rule::in(['flat', 'percentage'])],
            'items.*.discount_value'   => ['required_with:items', 'numeric', 'min:0.01', 'max:999999'],
        ]);

        $data['is_long_term'] = $request->boolean('is_long_term');
        $data['is_active']    = $request->boolean('is_active', true);

        if ($data['is_long_term']) {
            $data['ends_at'] = null;
        }

        if ($data['mode'] === 'individual') {
            $data['discount_type']  = null;
            $data['discount_value'] = null;
        } else {
            unset($data['items']);
        }

        return $data;
    }
}
