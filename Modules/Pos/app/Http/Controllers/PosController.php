<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;
use Modules\Pos\Models\Sale;
use Modules\Pos\Services\PosCatalogService;
use Modules\Pos\Services\PosSettingsService;
use Modules\Pos\Services\SaleService;
use Modules\Product\Services\ProductCatalogOptionsService;

class PosController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(
        private readonly PosCatalogService $catalog,
        private readonly SaleService $sales,
        private readonly PosSettingsService $posSettings,
        private readonly ProductCatalogOptionsService $productCatalogOptions,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $today = $this->sales->todaySummaryForBusiness($business);
        $hasProducts = $business->products()->where('is_active', true)->where('is_bundle', false)->exists();

        return view('pos::hub.index', [
            'business' => $business,
            'currency' => $currency,
            'today' => $today,
            'hasProducts' => $hasProducts,
            'hasSales' => $this->sales->businessHasSales($business),
        ]);
    }

    public function online(Request $request): View|RedirectResponse
    {
        return $this->terminal($request, Sale::CHANNEL_ONLINE, 'pos::online.index', 'Online retail POS');
    }

    public function register(Request $request): View|RedirectResponse
    {
        return $this->terminal($request, Sale::CHANNEL_RETAIL, 'pos::register.index', 'Retail register');
    }

    public function checkout(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.product_stock_layer_id' => ['nullable', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'in:cash,card,credit'],
            'channel' => ['nullable', 'string', 'in:retail,online'],
            'credit_account_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf(in_array($request->input('payment_method'), ['cash', 'card'], true)),
            ],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'amount_tendered' => ['nullable', 'numeric', 'min:0', 'required_if:payment_method,cash'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $channel = $validated['channel'] ?? Sale::CHANNEL_RETAIL;

        $sale = $this->sales->checkout(
            $business,
            $request->user(),
            $validated['items'],
            $validated['payment_method'],
            isset($validated['credit_account_id']) ? (int) $validated['credit_account_id'] : null,
            isset($validated['amount_paid']) ? (float) $validated['amount_paid'] : null,
            $validated['notes'] ?? null,
            $channel,
            isset($validated['discount_percent']) ? (float) $validated['discount_percent'] : null,
            isset($validated['amount_tendered']) ? (float) $validated['amount_tendered'] : null,
        );

        $redirectRoute = $channel === Sale::CHANNEL_ONLINE ? 'pos.online' : 'pos.register';

        return redirect()
            ->route($redirectRoute)
            ->with('pos_print_sale_id', $sale->id)
            ->with('status', 'Sale '.$sale->sale_number.' completed.');
    }

    public function toggleWalkingCustomer(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        session(['pos_walking_customer' => $request->boolean('enabled')]);

        $redirect = $request->input('redirect');
        if (is_string($redirect) && str_starts_with($redirect, url('/'))) {
            return redirect()->to($redirect);
        }

        return redirect()->route('pos.online');
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'default_deposit_account_id' => ['nullable', 'integer', 'min:1'],
            'discount_field_enabled' => ['nullable'],
            'display_theme' => ['nullable', 'string', 'in:light,dark'],
            'redirect' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->posSettings->saveForBusiness($business, $validated);

        $redirect = $validated['redirect'] ?? null;
        if (is_string($redirect) && str_starts_with($redirect, url('/'))) {
            return redirect()->to($redirect)->with('status', 'POS settings saved.');
        }

        return redirect()->route('pos.online')->with('status', 'POS settings saved.');
    }

    private function terminal(
        Request $request,
        string $channel,
        string $view,
        string $heading,
    ): View|RedirectResponse {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $search = (string) $request->query('q', '');
        $categoryId = $request->query('category');
        $categoryId = is_numeric($categoryId) ? (int) $categoryId : null;

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $accounts = $this->accountsForPosPayment($business, $request);
        $categories = $this->catalog->posCategories($business);
        $products = $this->catalog->productCardsForPos(
            $business,
            $search !== '' ? $search : null,
            $categoryId,
        );
        $today = $this->sales->todaySummaryForBusiness($business);
        $posSettings = $this->posSettings->forBusiness($business);
        $posShellClass = match ($posSettings['display_theme']) {
            'dark' => 'pos-shell--dark',
            'light' => 'pos-shell--light',
            default => '',
        };

        $printSale = null;
        $printSaleId = session()->pull('pos_print_sale_id');
        if (is_numeric($printSaleId)) {
            $printSale = Sale::query()
                ->where('business_id', $business->id)
                ->whereKey((int) $printSaleId)
                ->with(['items', 'creditAccount', 'user'])
                ->first();
        }

        $catalogOptions = $this->productCatalogOptions->optionsForBusiness($business);

        return view($view, [
            'business' => $business,
            'currency' => $currency,
            'productUnits' => $catalogOptions['units'],
            'search' => $search,
            'categoryId' => $categoryId,
            'categories' => $categories,
            'products' => $products,
            'accounts' => $accounts,
            'hasAccounts' => $accounts->isNotEmpty(),
            'channel' => $channel,
            'today' => $today,
            'heading' => $heading,
            'posWalkingCustomer' => (bool) session('pos_walking_customer', true),
            'posSettings' => $posSettings,
            'posShellClass' => $posShellClass,
            'defaultDepositAccountId' => $posSettings['default_deposit_account_id'],
            'printSale' => $printSale,
        ]);
    }
}
