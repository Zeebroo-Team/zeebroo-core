<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductStockLayer;
use Modules\Product\Services\ProductBundleService;
use Modules\Product\Services\ProductCatalogOptionsService;
use Modules\Product\Services\ProductDiscountService;
use Modules\Product\Services\ProductService;
use Modules\Product\Services\ProductImageService;
use Modules\Product\Services\ProductSkuGeneratorService;
use Modules\Product\Services\ProductStockActivityService;
use Modules\Product\Services\ProductSalesChartService;
use Modules\Product\Services\ProductStockLayerService;

class ProductController extends Controller
{
    /**
     * Mirrors the delivery partner keys/labels configured under POS Settings → Delivery
     * (see Modules\Pos\Services\PosSettingsService::DELIVERY_METHOD_KEYS).
     */
    private const DELIVERY_PARTNER_LABELS = [
        'dhl' => 'DHL Express',
        'fedex' => 'FedEx',
        'uber' => 'Uber',
        'pickme' => 'PickMe',
        'koobiyo' => 'Koobiyo',
        'pronto' => 'Pronto Lanka',
    ];

    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductCatalogOptionsService $catalogOptionsService,
        private readonly ProductSkuGeneratorService $skuGeneratorService,
        private readonly ProductImageService $productImageService,
        private readonly ProductBundleService $productBundleService,
        private readonly ProductStockActivityService $productStockActivity,
        private readonly ProductStockLayerService $productStockLayers,
        private readonly ProductSalesChartService $productSalesChart,
        private readonly ProductDiscountService $discountService,
    ) {
    }

    public function generateSku(Request $request): JsonResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (!$business) {
            return response()->json(['error' => 'No business selected.'], 422);
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);

        $validated = $request->validate([
            'product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $excluding = null;
        if (!empty($validated['product_id'])) {
            $product = Product::query()->find((int) $validated['product_id']);
            $excluding = $product && $this->productService->productForBusiness($business, $product) instanceof Product
                ? $product
                : null;
        }

        return response()->json([
            'sku' => $this->skuGeneratorService->generate(
                $business,
                $excluding,
                $validated['name'] ?? null,
            ),
        ]);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (!$business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select or create a business first.']);
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $catalog = $this->catalogOptionsService->optionsForBusiness($business);
        $branchProductSeparate = (bool) get_settings('business.branch_product_separate', false, $business);
        $branchOptions = $branchProductSeparate ? $business->branches()->get() : collect();

        $search         = trim((string) $request->query('q', ''));
        $filterCategory = $request->query('category') ? (int) $request->query('category') : null;
        $filterBrand    = $request->query('brand')    ? (int) $request->query('brand')    : null;
        $filterStatus   = in_array($request->query('status'), ['active', 'inactive'], true)
            ? (string) $request->query('status')
            : null;

        $products = $this->productService->listForBusiness(
            $business, $search, $filterCategory, $filterBrand, $filterStatus,
        );

        $totalProductCount = ($search === '' && $filterCategory === null && $filterBrand === null && $filterStatus === null)
            ? $products->total()
            : $business->products()->count();

        // Load active discounts for products on this page (single query)
        $pageProductIds = $products->pluck('id')->all();
        $activeDiscounts = $this->discountService->activeForProducts($business, $pageProductIds);
        // Best base-price discount per product (null selling_unit_id)
        $baseDiscountByProduct = $activeDiscounts
            ->filter(fn ($d) => $d->product_selling_unit_id === null)
            ->groupBy('product_id')
            ->map(fn ($group) => $group->first());

        return view('product::products.index', [
            'business'             => $business,
            'products'             => $products,
            'totalProductCount'    => $totalProductCount,
            'currency'             => $currency,
            'categories'           => $catalog['categories'],
            'brands'               => $catalog['brands'],
            'units'                => $catalog['units'],
            'bundlePickerCatalog'  => $this->productBundleService->pickerCatalogForBusiness($business),
            'search'               => $search,
            'filterCategory'       => $filterCategory,
            'filterBrand'          => $filterBrand,
            'filterStatus'         => $filterStatus,
            'branchProductSeparate' => $branchProductSeparate,
            'branchOptions'        => $branchOptions,
            'baseDiscountByProduct' => $baseDiscountByProduct,
            'deliveryPartners'     => $this->deliveryPartnersForBusiness($business),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (!$business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'No business selected.']);
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);

        $data = $this->validatedProduct($request, $business);
        $data = $this->catalogOptionsService->normalizeProductCatalogFields($business, $data);

        $this->productService->create($business, $data);

        return redirect()->route('product.index')->with('status', 'Product added.');
    }

    public function quickStore(Request $request): JsonResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (!$business) {
            return response()->json(['message' => 'No business selected.'], 403);
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'sku'        => ['nullable', 'string', 'max:120'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'description'=> ['nullable', 'string', 'max:5000'],
        ]);

        $product = $this->productService->create($business, array_filter([
            'name'        => $data['name'],
            'sku'         => $data['sku'] ?? null,
            'unit_price'  => isset($data['unit_price']) ? (float) $data['unit_price'] : null,
            'description' => $data['description'] ?? null,
            'is_active'   => true,
        ], fn ($v) => $v !== null));

        return response()->json([
            'message' => 'Product created.',
            'product' => [
                'id'         => $product->id,
                'name'       => $product->name,
                'sku'        => $product->sku,
                'unit_price' => $product->unit_price,
            ],
        ], 201);
    }

    public function show(Request $request, Product $product): View|RedirectResponse
    {
        $business = $this->resolveBusinessProduct($request, $product);
        if (!$business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select or create a business first.']);
        }

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $product = $this->productService->loadForShow($product);
        $activeTab = (string) $request->query('tab', 'overview');
        $allowedTabs = ['overview', 'pricing', 'selling-units', 'stock', 'advanced', 'bundle', 'gallery'];
        if (! in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'overview';
        }
        if ($activeTab === 'bundle' && ! $product->is_bundle) {
            $activeTab = 'overview';
        }
        $hasGallery = $product->productImages->isNotEmpty() || $product->imageUrl();
        if ($activeTab === 'gallery' && ! $hasGallery) {
            $activeTab = 'overview';
        }

        $stockView = (string) $request->query('stock', 'layers');
        if (! in_array($stockView, ['layers', 'po', 'grn'], true)) {
            $stockView = 'layers';
        }

        $stockActivity = $this->productStockActivity->forProduct($product);
        $stockSellingMarkupPercent = (float) get_settings('product.stock_selling_markup_percent', 25, $business);
        $branchStockSeparate = (bool) get_settings('business.branch_stock_separate', false, $business);

        $salesPeriod = (string) $request->query('sales_period', 'weekly');
        if (! in_array($salesPeriod, ['daily', 'weekly', 'monthly'], true)) {
            $salesPeriod = 'weekly';
        }
        $salesChart = $this->productSalesChart->build($product, $salesPeriod);

        // Active discounts for this product (base price + per selling unit)
        $productDiscounts = $this->discountService->activeForProducts($business, [$product->id]);
        $baseDiscount     = $productDiscounts->firstWhere('product_selling_unit_id', null);
        $suDiscountById   = $productDiscounts
            ->filter(fn ($d) => $d->product_selling_unit_id !== null)
            ->keyBy('product_selling_unit_id');

        return view('product::products.show', array_merge([
            'business'             => $business,
            'product'              => $product,
            'currency'             => $currency,
            'activeTab'            => $activeTab,
            'stockView'            => $stockView,
            'stockSellingMarkupPercent' => $stockSellingMarkupPercent,
            'branchStockSeparate'  => $branchStockSeparate,
            'salesChart'           => $salesChart,
            'salesPeriod'          => $salesPeriod,
            'baseDiscount'         => $baseDiscount,
            'suDiscountById'       => $suDiscountById,
            'deliveryPartnerLabels' => self::DELIVERY_PARTNER_LABELS,
        ], $stockActivity));
    }

    private function stockLayerOrAbort(Request $request, Product $product, ProductStockLayer $stockLayer): Business
    {
        $business = $this->resolveBusinessProduct($request, $product);
        abort_unless($business !== null, 404);

        abort_unless(
            (int) $stockLayer->product_id === (int) $product->id
            && (int) $stockLayer->business_id === (int) $business->id,
            404,
        );

        return $business;
    }

    public function updateStockLayerSellingPrice(Request $request, Product $product, ProductStockLayer $stockLayer): RedirectResponse
    {
        $this->stockLayerOrAbort($request, $product, $stockLayer);

        $validated = $request->validate([
            'selling_unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $this->productStockLayers->updateSellingPrice(
            $stockLayer,
            isset($validated['selling_unit_price']) ? (float) $validated['selling_unit_price'] : null,
        );

        return redirect()
            ->route('product.show', ['product' => $product, 'tab' => 'stock', 'stock' => 'layers'])
            ->with('status', 'Selling price updated for this stock batch.');
    }

    public function updateStockLayerCostPrice(Request $request, Product $product, ProductStockLayer $stockLayer): RedirectResponse
    {
        $this->stockLayerOrAbort($request, $product, $stockLayer);

        $validated = $request->validate([
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $this->productStockLayers->updateCostPrice($stockLayer, (float) $validated['unit_cost']);

        return redirect()
            ->route('product.show', ['product' => $product, 'tab' => 'stock', 'stock' => 'layers'])
            ->with('status', 'Cost price updated for this stock batch.');
    }

    public function updateStockLayerWholesalePrice(Request $request, Product $product, ProductStockLayer $stockLayer): RedirectResponse
    {
        $this->stockLayerOrAbort($request, $product, $stockLayer);

        $validated = $request->validate([
            'wholesale_unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $this->productStockLayers->updateWholesalePrice(
            $stockLayer,
            isset($validated['wholesale_unit_price']) ? (float) $validated['wholesale_unit_price'] : null,
        );

        return redirect()
            ->route('product.show', ['product' => $product, 'tab' => 'stock', 'stock' => 'layers'])
            ->with('status', 'Wholesale price updated for this stock batch.');
    }

    public function updateStockLayerBarcode(Request $request, Product $product, ProductStockLayer $stockLayer): RedirectResponse
    {
        $this->stockLayerOrAbort($request, $product, $stockLayer);

        $validated = $request->validate([
            'batch_sku' => [
                'nullable', 'string', 'max:150',
                Rule::unique('product_stock_layers', 'batch_sku')->ignore($stockLayer->id),
            ],
        ]);

        $this->productStockLayers->updateBarcode($stockLayer, $validated['batch_sku'] ?? null);

        return redirect()
            ->route('product.show', ['product' => $product, 'tab' => 'stock', 'stock' => 'layers'])
            ->with('status', 'Barcode updated for this stock batch.');
    }

    public function edit(Request $request, Product $product): View|RedirectResponse
    {
        $business = $this->resolveBusinessProduct($request, $product);
        if (!$business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select or create a business first.']);
        }

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $catalog = $this->catalogOptionsService->optionsForBusiness($business);
        $branchProductSeparate = (bool) get_settings('business.branch_product_separate', false, $business);
        $branchOptions = $branchProductSeparate ? $business->branches()->get() : collect();
        $product->load(['categories', 'brands', 'productUnit', 'imageFile', 'productImages.file', 'bundleItems.itemProduct']);

        return view('product::products.edit', [
            'business'             => $business,
            'product'              => $product,
            'currency'             => $currency,
            'categories'           => $catalog['categories'],
            'brands'               => $catalog['brands'],
            'units'                => $catalog['units'],
            'bundlePickerCatalog'  => $this->productBundleService->pickerCatalogForBusiness($business, $product),
            'branchProductSeparate' => $branchProductSeparate,
            'branchOptions'        => $branchOptions,
            'deliveryPartners'     => $this->deliveryPartnersForBusiness($business),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $business = $this->resolveBusinessProduct($request, $product);
        if (!$business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'No business selected.']);
        }

        $data = $this->validatedProduct($request, $business);
        $data = $this->catalogOptionsService->normalizeProductCatalogFields($business, $data);

        $this->productService->update($product, $data);

        return redirect()->route('product.index')->with('status', 'Product updated.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $business = $this->resolveBusinessProduct($request, $product);
        if (!$business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'No business selected.']);
        }

        $this->productService->delete($product);

        return redirect()->route('product.index')->with('status', 'Product removed.');
    }

    private function resolveBusinessProduct(Request $request, Product $product): ?Business
    {
        $business = Business::currentForNavbar($request->user());
        if (!$business) {
            return null;
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);
        abort_unless($this->productService->productForBusiness($business, $product) instanceof Product, 404);

        return $business;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedProduct(Request $request, Business $business): array
    {
        $branchProductSeparate = (bool) get_settings('business.branch_product_separate', false, $business);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => $branchProductSeparate
                ? ['nullable', 'integer', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $business->id))]
                : ['nullable'],
            'sku' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'product_category_ids' => ['nullable', 'array'],
            'product_category_ids.*' => [
                'integer',
                Rule::exists('product_categories', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'new_category_name' => ['nullable', 'string', 'max:255'],
            'new_category_names' => ['nullable', 'array'],
            'new_category_names.*' => ['string', 'max:255'],
            'product_brand_ids' => ['nullable', 'array'],
            'product_brand_ids.*' => [
                'integer',
                Rule::exists('product_brands', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'new_brand_name' => ['nullable', 'string', 'max:255'],
            'new_brand_names' => ['nullable', 'array'],
            'new_brand_names.*' => ['string', 'max:255'],
            'product_unit_id' => ['nullable', 'integer', Rule::exists('product_units', 'id')->where(fn ($q) => $q->where('business_id', $business->id))],
            'unit' => ['nullable', 'string', 'max:40'],
            'unit_price'      => ['nullable', 'numeric', 'min:0'],
            'cost_price'      => ['nullable', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'numeric', 'min:0'],
            'file_manager_file_id' => ['nullable', 'integer'],
            'file_manager_file_ids' => ['nullable', 'array', 'max:20'],
            'file_manager_file_ids.*' => ['integer'],
            'remove_product_image' => ['nullable', 'boolean'],
            'remove_product_images' => ['nullable', 'boolean'],
            'is_bundle' => ['nullable', 'boolean'],
            'bundle_items' => ['nullable', 'array'],
            'bundle_items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
            'bundle_items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],

            // Basic — extended fields
            'model_no' => ['nullable', 'string', 'max:120'],
            'size' => ['nullable', 'string', 'max:120'],
            'mfg_date' => ['nullable', 'date'],
            'tags' => ['nullable', 'string', 'max:1000'],

            // Advanced options
            'has_warranty' => ['nullable', 'boolean'],
            'warranty_duration' => ['nullable', 'string', 'max:60'],
            'track_expiry' => ['nullable', 'boolean'],
            'exp_date' => ['nullable', 'date'],
            'loyalty_redeemable' => ['nullable', 'boolean'],
            'is_customer_required' => ['nullable', 'boolean'],
            'is_rental' => ['nullable', 'boolean'],
            'rental_daily_rate' => ['nullable', 'numeric', 'min:0'],
            'rental_max_days' => ['nullable', 'integer', 'min:0'],
            'rental_late_fee_multiplier' => ['nullable', 'numeric', 'min:0'],
            'rental_needs_cleaning' => ['nullable', 'boolean'],
            'is_subscription' => ['nullable', 'boolean'],
            'subscription_recurring_period' => ['nullable', Rule::in(['weekly', 'monthly', 'quarterly', 'yearly'])],
            'subscription_free_trial' => ['nullable', 'boolean'],
            'is_dynamic_pricing' => ['nullable', 'boolean'],
            'dynamic_price_qty_linked' => ['nullable', 'boolean'],
            'item_wise_tax' => ['nullable', 'boolean'],
            'item_wise_discount' => ['nullable', 'boolean'],

            // Delivery — keyed by enabled partner key, e.g. delivery_methods[dhl][selected/price]
            'courier_delivery' => ['nullable', 'boolean'],
            'delivery_methods' => ['nullable', 'array'],
            'delivery_methods.*.selected' => ['nullable', 'boolean'],
            'delivery_methods.*.price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $rawFileIds = $request->input('file_manager_file_ids', []);
        if (!is_array($rawFileIds)) {
            $rawFileIds = [];
        }
        if ($rawFileIds === [] && !empty($validated['file_manager_file_id'])) {
            $rawFileIds = [(int) $validated['file_manager_file_id']];
        }

        $removeImages = $request->boolean('remove_product_images') || $request->boolean('remove_product_image');
        $fileIds = $this->productImageService->resolveImageFileIds($business, $rawFileIds, $removeImages);

        $validated['file_manager_file_ids'] = $fileIds;
        $validated['file_manager_file_id'] = $fileIds[0] ?? null;
        unset($validated['remove_product_image'], $validated['remove_product_images']);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_bundle'] = $request->boolean('is_bundle');
        $validated['bundle_items'] = array_values(array_filter(
            $request->input('bundle_items', []),
            static fn ($row) => is_array($row) && !empty($row['product_id']),
        ));
        $validated['unit_price']      = isset($validated['unit_price'])      ? (float) $validated['unit_price']      : null;
        $validated['cost_price']      = isset($validated['cost_price'])      ? (float) $validated['cost_price']      : null;
        $validated['wholesale_price'] = isset($validated['wholesale_price']) ? (float) $validated['wholesale_price'] : null;
        $validated['stock_quantity']  = isset($validated['stock_quantity'])  ? (float) $validated['stock_quantity']  : 0;

        $validated['tags'] = $this->parseTags($request->input('tags'));

        $validated['has_warranty']         = $request->boolean('has_warranty');
        $validated['track_expiry']         = $request->boolean('track_expiry');
        $validated['loyalty_redeemable']   = $request->boolean('loyalty_redeemable');
        $validated['is_customer_required'] = $request->boolean('is_customer_required');
        $validated['is_rental']            = $request->boolean('is_rental');
        $validated['rental_needs_cleaning'] = $request->boolean('rental_needs_cleaning');
        $validated['is_subscription']      = $request->boolean('is_subscription');
        $validated['subscription_free_trial'] = $request->boolean('subscription_free_trial');
        $validated['is_dynamic_pricing']   = $request->boolean('is_dynamic_pricing');
        $validated['dynamic_price_qty_linked'] = $request->boolean('dynamic_price_qty_linked');
        $validated['item_wise_tax']        = $request->boolean('item_wise_tax');
        $validated['item_wise_discount']   = $request->boolean('item_wise_discount');
        $validated['courier_delivery']     = $request->boolean('courier_delivery');

        $validated['rental_daily_rate']          = isset($validated['rental_daily_rate']) ? (float) $validated['rental_daily_rate'] : null;
        $validated['rental_max_days']             = isset($validated['rental_max_days']) ? (int) $validated['rental_max_days'] : null;
        $validated['rental_late_fee_multiplier']  = isset($validated['rental_late_fee_multiplier']) ? (float) $validated['rental_late_fee_multiplier'] : null;

        $enabledDeliveryKeys = $this->deliveryPartnersForBusiness($business)->pluck('key')->all();
        $rawDeliveryMethods = $request->input('delivery_methods', []);
        $validated['delivery_methods'] = [];
        foreach ($enabledDeliveryKeys as $key) {
            $row = $rawDeliveryMethods[$key] ?? null;
            if (! is_array($row) || empty($row['selected'])) {
                continue;
            }
            $validated['delivery_methods'][] = [
                'key' => $key,
                'price' => isset($row['price']) && $row['price'] !== '' ? (float) $row['price'] : null,
            ];
        }

        $validated['product_category_ids'] = $validated['product_category_ids'] ?? [];
        $validated['product_brand_ids'] = $validated['product_brand_ids'] ?? [];

        if (empty($validated['product_unit_id'])) {
            $validated['product_unit_id'] = null;
        }

        if (! $branchProductSeparate || empty($validated['branch_id'])) {
            $validated['branch_id'] = null;
        }

        return $validated;
    }

    /**
     * Delivery partners enabled for this business under POS Settings → Delivery,
     * as [{key, label}, ...] — only these can be picked as a product's delivery methods.
     *
     * @return \Illuminate\Support\Collection<int, array{key: string, label: string}>
     */
    private function deliveryPartnersForBusiness(Business $business): \Illuminate\Support\Collection
    {
        if (! (bool) get_settings('delivery.enabled', false, $business)) {
            return collect();
        }

        $enabledKeys = (array) get_settings('delivery.methods', [], $business);

        return collect(self::DELIVERY_PARTNER_LABELS)
            ->only($enabledKeys)
            ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
            ->values();
    }

    /**
     * @return list<string>
     */
    private function parseTags(mixed $raw): array
    {
        if (is_array($raw)) {
            $raw = implode(',', $raw);
        }

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($tag) => trim($tag),
            preg_split('/[,\n]+/', $raw) ?: [],
        ), static fn ($tag) => $tag !== '')));
    }
}
