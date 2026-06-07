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
use Modules\Product\Services\ProductService;
use Modules\Product\Services\ProductImageService;
use Modules\Product\Services\ProductSkuGeneratorService;
use Modules\Product\Services\ProductStockActivityService;
use Modules\Product\Services\ProductSalesChartService;
use Modules\Product\Services\ProductStockLayerService;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductCatalogOptionsService $catalogOptionsService,
        private readonly ProductSkuGeneratorService $skuGeneratorService,
        private readonly ProductImageService $productImageService,
        private readonly ProductBundleService $productBundleService,
        private readonly ProductStockActivityService $productStockActivity,
        private readonly ProductStockLayerService $productStockLayers,
        private readonly ProductSalesChartService $productSalesChart,
    ) {
    }

    public function generateSku(Request $request): JsonResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (!$business) {
            return response()->json(['error' => 'No business selected.'], 422);
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);

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

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $catalog = $this->catalogOptionsService->optionsForBusiness($business);

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

        return view('product::products.index', [
            'business'          => $business,
            'products'          => $products,
            'totalProductCount' => $totalProductCount,
            'currency'          => $currency,
            'categories'        => $catalog['categories'],
            'brands'            => $catalog['brands'],
            'units'             => $catalog['units'],
            'bundlePickerCatalog' => $this->productBundleService->pickerCatalogForBusiness($business),
            'search'            => $search,
            'filterCategory'    => $filterCategory,
            'filterBrand'       => $filterBrand,
            'filterStatus'      => $filterStatus,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (!$business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'No business selected.']);
        }

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);

        $data = $this->validatedProduct($request, $business);
        $data = $this->catalogOptionsService->normalizeProductCatalogFields($business, $data);

        $this->productService->create($business, $data);

        return redirect()->route('product.index')->with('status', 'Product added.');
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
        $allowedTabs = ['overview', 'selling-units', 'stock', 'bundle', 'gallery'];
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

        $salesPeriod = (string) $request->query('sales_period', 'weekly');
        if (! in_array($salesPeriod, ['daily', 'weekly', 'monthly'], true)) {
            $salesPeriod = 'weekly';
        }
        $salesChart = $this->productSalesChart->build($product, $salesPeriod);

        return view('product::products.show', array_merge([
            'business' => $business,
            'product' => $product,
            'currency' => $currency,
            'activeTab' => $activeTab,
            'stockView' => $stockView,
            'stockSellingMarkupPercent' => $stockSellingMarkupPercent,
            'salesChart' => $salesChart,
            'salesPeriod' => $salesPeriod,
        ], $stockActivity));
    }

    public function updateStockLayer(Request $request, Product $product, ProductStockLayer $stockLayer): RedirectResponse
    {
        $business = $this->resolveBusinessProduct($request, $product);
        if (!$business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select or create a business first.']);
        }

        abort_unless(
            (int) $stockLayer->product_id === (int) $product->id
            && (int) $stockLayer->business_id === (int) $business->id,
            404,
        );

        $validated = $request->validate([
            'selling_unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $this->productStockLayers->updateSellingPrice($stockLayer, (float) $validated['selling_unit_price']);

        return redirect()
            ->route('product.show', ['product' => $product, 'tab' => 'stock', 'stock' => 'layers'])
            ->with('status', 'Selling price updated for this stock batch.');
    }

    public function edit(Request $request, Product $product): View|RedirectResponse
    {
        $business = $this->resolveBusinessProduct($request, $product);
        if (!$business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select or create a business first.']);
        }

        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $catalog = $this->catalogOptionsService->optionsForBusiness($business);
        $product->load(['categories', 'brands', 'productUnit', 'imageFile', 'productImages.file', 'bundleItems.itemProduct']);

        return view('product::products.edit', [
            'business' => $business,
            'product' => $product,
            'currency' => $currency,
            'categories' => $catalog['categories'],
            'brands' => $catalog['brands'],
            'units' => $catalog['units'],
            'bundlePickerCatalog' => $this->productBundleService->pickerCatalogForBusiness($business, $product),
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

        abort_unless($request->user()->businesses()->whereKey($business->id)->exists(), 403);
        abort_unless($this->productService->productForBusiness($business, $product) instanceof Product, 404);

        return $business;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedProduct(Request $request, Business $business): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
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
            'unit_price' => ['nullable', 'numeric', 'min:0'],
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
        $validated['unit_price'] = isset($validated['unit_price']) ? (float) $validated['unit_price'] : null;
        $validated['stock_quantity'] = isset($validated['stock_quantity']) ? (float) $validated['stock_quantity'] : 0;

        $validated['product_category_ids'] = $validated['product_category_ids'] ?? [];
        $validated['product_brand_ids'] = $validated['product_brand_ids'] ?? [];

        if (empty($validated['product_unit_id'])) {
            $validated['product_unit_id'] = null;
        }

        return $validated;
    }
}
