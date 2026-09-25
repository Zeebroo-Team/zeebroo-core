<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\AppConnection\Models\AppRelease;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;
use Modules\Pos\Models\Sale;
use Modules\Pos\Services\PosCatalogService;
use Modules\Pos\Services\PosSettingsService;
use Modules\Pos\Services\SaleService;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductCatalogOptionsService;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Services\InvoiceService;

class PosController extends Controller
{
    use ResolvesPosBusiness;

    public function __construct(
        private readonly PosCatalogService $catalog,
        private readonly SaleService $sales,
        private readonly PosSettingsService $posSettings,
        private readonly ProductCatalogOptionsService $productCatalogOptions,
        private readonly InvoiceService $invoices,
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
            'latestRelease' => AppRelease::latestStable(),
        ]);
    }

    public function online(Request $request): View|RedirectResponse
    {
        return $this->terminal($request, Sale::CHANNEL_ONLINE, 'pos::online.index', 'Online retail POS');
    }

    public function register(Request $request): View|RedirectResponse
    {
        return $this->terminal($request, Sale::CHANNEL_RETAIL, 'pos::register.index', 'Retail register', paginate: true);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['nullable', 'string', 'in:product,service'],
            'items.*.product_id' => ['nullable', 'integer', 'min:1'],
            'items.*.service_item_id' => ['nullable', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.product_stock_layer_id' => ['nullable', 'integer', 'min:1'],
            'items.*.product_selling_unit_id' => ['nullable', 'integer', 'min:1'],
            'items.*.selling_unit_label'  => ['nullable', 'string', 'max:80'],
            'items.*.selling_unit_factor' => ['nullable', 'numeric', 'min:0.000001'],
            'items.*.warranty_type' => ['nullable', 'string', 'in:lifetime,date'],
            'items.*.warranty_date' => ['nullable', 'date_format:Y-m-d'],
            'items.*.rental_return_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'items.*.item_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.custom_unit_price' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.custom_requirement_values' => ['nullable', 'array', 'max:20'],
            'items.*.custom_requirement_values.*.key' => ['nullable', 'string', 'max:100'],
            'items.*.custom_requirement_values.*.label' => ['nullable', 'string', 'max:255'],
            'items.*.custom_requirement_values.*.type' => ['nullable', 'string', 'in:text,textarea,select,number,date,checkbox,radio'],
            'items.*.custom_requirement_values.*.value' => ['nullable', 'string', 'max:1000'],
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
            'pos_customer_id' => ['nullable', 'integer', 'min:1'],
            'branch_id' => [
                'nullable', 'integer', 'min:1',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'pos_counter_id' => [
                'nullable', 'integer', 'min:1',
                Rule::exists('pos_counters', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
        ]);

        if ($validated['payment_method'] === 'credit' && empty($validated['pos_customer_id'])) {
            return back()
                ->withErrors(['pos_customer_id' => 'A customer is required for credit payment.'])
                ->withInput();
        }

        if (empty($validated['pos_customer_id'])) {
            $productIds = collect($validated['items'])->pluck('product_id')->filter()->unique();
            if ($productIds->isNotEmpty() && Product::query()->whereIn('id', $productIds)->where('is_subscription', true)->exists()) {
                return back()
                    ->withErrors(['pos_customer_id' => 'A customer is required to sell a subscription product.'])
                    ->withInput();
            }
            if ($productIds->isNotEmpty() && Product::query()->whereIn('id', $productIds)->where('is_rental', true)->exists()) {
                return back()
                    ->withErrors(['pos_customer_id' => 'A customer is required to rent a product.'])
                    ->withInput();
            }
        }

        $channel = $validated['channel'] ?? Sale::CHANNEL_RETAIL;

        $posSettings = $this->posSettings->forBusiness($business);
        $deferSettlement = ($posSettings['payment_settlement_mode'] ?? 'immediate') === 'end_of_day';

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
            isset($validated['pos_customer_id']) ? (int) $validated['pos_customer_id'] : null,
            $deferSettlement,
            isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
            posCounterId: isset($validated['pos_counter_id']) ? (int) $validated['pos_counter_id'] : null,
        );

        $redirectRoute = $channel === Sale::CHANNEL_ONLINE ? 'pos.online' : 'pos.register';

        if (($posSettings['receipt_mode'] ?? 'bill') === 'invoice') {
            $invoice = $this->invoices->createFromPosSale($sale);

            return redirect()
                ->route($redirectRoute)
                ->with('pos_print_invoice_id', $invoice->id)
                ->with('status', 'Sale '.$sale->sale_number.' completed — invoice '.$invoice->invoice_number.' created.');
        }

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
            'checkout_modal_enabled' => ['nullable', 'boolean'],
            'display_theme' => ['nullable', 'string', 'in:light,dark'],
            'receipt_header' => ['nullable', 'string', 'max:200'],
            'receipt_footer' => ['nullable', 'string', 'max:200'],
            'show_business_name' => ['nullable'],
            'show_business_address' => ['nullable'],
            'show_account_info' => ['nullable'],
            'receipt_paper_width' => ['nullable', 'string', 'in:58,80'],
            'receipt_mode' => ['nullable', 'string', 'in:bill,invoice'],
            'payment_settlement_mode' => ['nullable', 'string', 'in:immediate,end_of_day'],
            'featured_products_limit' => ['nullable', 'integer', 'min:0'],
            'featured_categories_limit' => ['nullable', 'integer', 'min:0'],
            'show_service_bound_products' => ['nullable'],
            'delivery_enabled' => ['nullable'],
            'delivery_methods' => ['nullable', 'array'],
            'delivery_methods.*' => ['nullable', 'string'],
            'tax_rules' => ['nullable', 'array'],
            'tax_rules.*.id' => ['nullable', 'string', 'max:36'],
            'tax_rules.*.name' => ['nullable', 'string', 'max:50'],
            'tax_rules.*.type' => ['nullable', 'string', 'in:percentage,flat'],
            'tax_rules.*.value' => ['nullable', 'numeric', 'min:0'],
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
        bool $paginate = false,
    ): View|RedirectResponse {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $search = (string) $request->query('q', '');
        $categoryId = $request->query('category');
        $categoryId = is_numeric($categoryId) ? (int) $categoryId : null;

        $mode = (string) $request->query('mode', 'products');
        if (! in_array($mode, ['products', 'services', 'rental', 'dynamic', 'campaign'], true)) {
            $mode = 'products';
        }
        $quickFilter = (string) $request->query('filter', '');
        $page = $paginate ? max(1, (int) $request->query('page', 1)) : 1;

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');

        $branchPosSeparate     = (bool) get_settings('business.branch_pos_separate', false, $business);
        $branchProductSeparate = (bool) get_settings('business.branch_product_separate', false, $business);
        $branchStockSeparate   = (bool) get_settings('business.branch_stock_separate', false, $business);

        $branchId = null;
        $branchOptions = collect();
        if ($branchPosSeparate) {
            $branchOptions = $business->branches()->get();
            $rawBranchId = $request->query('branch');
            if (is_numeric($rawBranchId)) {
                $candidate = (int) $rawBranchId;
                if ($branchOptions->contains('id', $candidate)) {
                    $branchId = $candidate;
                }
            }
        }

        $accounts = $this->accountsForPosPayment($business, $request);
        $posSettings = $this->posSettings->forBusiness($business);

        $featuredProductsLimit   = (int) ($posSettings['featured_products_limit'] ?? 0);
        $featuredCategoriesLimit = (int) ($posSettings['featured_categories_limit'] ?? 0);

        $categories = $this->catalog->posCategories($business, $branchId, $branchProductSeparate);
        if ($featuredCategoriesLimit > 0) {
            $categories = $categories->take($featuredCategoriesLimit);
        }

        $serviceItems = $this->catalog->serviceCardsForPos($business);

        $products = [];
        $productsMeta = null;
        $campaignGroups = [];

        if ($mode === 'campaign') {
            $campaignGroups = $this->catalog->productsGroupedByCampaign(
                $business,
                $branchId,
                $branchProductSeparate,
                $branchStockSeparate,
            );
        } elseif ($mode !== 'services') {
            $perPage = $featuredProductsLimit > 0 ? $featuredProductsLimit : ($paginate ? 60 : 500);
            $catalogPage = $this->catalog->productCardsForPos(
                $business,
                $search !== '' ? $search : null,
                $categoryId,
                $page,
                $perPage,
                $branchId,
                $branchProductSeparate,
                $branchStockSeparate,
                stockStatus: null,
                brandId: null,
                sort: 'name_asc',
                recentSales: $quickFilter === 'recent',
                discountOnly: $quickFilter === 'discount',
                rentalOnly: $mode === 'rental',
                dynamicOnly: $mode === 'dynamic',
            );
            $products = $catalogPage['data'];
            $productsMeta = $catalogPage['meta'];
        }

        $today = $this->sales->todaySummaryForBusiness($business);
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
                ->with(['items.serviceItem.products', 'items.productRental', 'items.subscription', 'creditAccount', 'user', 'customer'])
                ->first();
        }

        $printInvoiceUrl = null;
        $printInvoiceId = session()->pull('pos_print_invoice_id');
        if (is_numeric($printInvoiceId)) {
            $invoiceExists = Invoice::query()
                ->where('business_id', $business->id)
                ->whereKey((int) $printInvoiceId)
                ->exists();
            if ($invoiceExists) {
                $printInvoiceUrl = route('sales.invoices.print', (int) $printInvoiceId);
            }
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
            'productsMeta' => $productsMeta,
            'campaignGroups' => $campaignGroups,
            'mode' => $mode,
            'quickFilter' => $quickFilter,
            'serviceItems' => $serviceItems,
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
            'printInvoiceUrl' => $printInvoiceUrl,
            'branchPosSeparate' => $branchPosSeparate,
            'branchOptions' => $branchOptions,
            'branchId' => $branchId,
        ]);
    }
}
