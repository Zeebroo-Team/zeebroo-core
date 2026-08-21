<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Services\PosProductQuickCreateService;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductBrand;
use Modules\Product\Models\ProductCategory;
use Modules\Product\Services\ProductService;
use Modules\Product\Services\ProductStockLayerService;

class PosProductApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly PosProductQuickCreateService $quickCreate,
        private readonly ProductService $productService,
        private readonly ProductStockLayerService $stockLayers,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_products');

        try {
            $quickResult = $this->quickCreate->create($business, $request->all());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Could not save product.',
                'errors'  => $e->errors(),
            ], 422);
        }

        // Sync extended fields when provided
        if (isset($quickResult['id'])) {
            $product = $business->products()->find($quickResult['id']);
            if ($product) {
                $fill = [];
                if ($request->filled('description'))         $fill['description']          = $request->input('description');
                if ($request->filled('model_no'))            $fill['model_no']             = $request->input('model_no');
                if ($request->filled('size'))                $fill['size']                 = $request->input('size');
                if ($request->filled('mfg_date'))            $fill['mfg_date']             = $request->input('mfg_date');
                if ($request->filled('exp_date'))            $fill['exp_date']             = $request->input('exp_date');
                if ($request->has('cost_price'))             $fill['cost_price']           = $request->input('cost_price') !== null ? (float) $request->input('cost_price') : null;
                if ($request->has('wholesale_price'))        $fill['wholesale_price']      = $request->input('wholesale_price') !== null ? (float) $request->input('wholesale_price') : null;
                if ($request->has('is_active'))              $fill['is_active']            = $request->boolean('is_active');
                if ($request->has('has_warranty'))           $fill['has_warranty']         = $request->boolean('has_warranty');
                if ($request->filled('warranty_duration'))   $fill['warranty_duration']    = $request->input('warranty_duration');
                if ($request->has('warranty_duration') && !$request->filled('warranty_duration')) $fill['warranty_duration'] = null;
                if ($request->has('track_expiry'))           $fill['track_expiry']         = $request->boolean('track_expiry');
                if ($request->has('courier_delivery'))       $fill['courier_delivery']     = $request->boolean('courier_delivery');
                if ($request->has('loyalty_redeemable'))     $fill['loyalty_redeemable']   = $request->boolean('loyalty_redeemable');
                if ($request->has('is_customer_required'))   $fill['is_customer_required'] = $request->boolean('is_customer_required');
                if ($request->has('is_rental'))              $fill['is_rental']            = $request->boolean('is_rental');
                if ($request->has('rental_daily_rate'))      $fill['rental_daily_rate']     = $request->input('rental_daily_rate') !== null ? (float) $request->input('rental_daily_rate') : null;
                if ($request->has('rental_max_days'))        $fill['rental_max_days']       = $request->input('rental_max_days') !== null ? (int) $request->input('rental_max_days') : null;
                if ($request->has('rental_late_fee_multiplier')) $fill['rental_late_fee_multiplier'] = $request->input('rental_late_fee_multiplier') !== null ? (float) $request->input('rental_late_fee_multiplier') : null;
                if ($request->has('rental_needs_cleaning'))  $fill['rental_needs_cleaning'] = $request->boolean('rental_needs_cleaning');
                if ($request->has('is_subscription'))        $fill['is_subscription']      = $request->boolean('is_subscription');
                if ($request->has('item_wise_tax'))          $fill['item_wise_tax']        = $request->boolean('item_wise_tax');
                if ($request->has('item_wise_discount'))     $fill['item_wise_discount']   = $request->boolean('item_wise_discount');
                if ($fill) $product->fill($fill)->save();

                if ($request->has('product_category_ids')) {
                    $ids = array_filter(array_map('intval', (array) $request->input('product_category_ids', [])), fn ($id) => $id > 0);
                    $product->categories()->sync($ids);
                }
                if ($request->has('product_brand_ids')) {
                    $ids = array_filter(array_map('intval', (array) $request->input('product_brand_ids', [])), fn ($id) => $id > 0);
                    $product->brands()->sync($ids);
                }
                if ($request->has('file_manager_file_ids')) {
                    $fileIds = array_filter(array_map('intval', (array) $request->input('file_manager_file_ids', [])), fn ($id) => $id > 0);
                    if ($fileIds) {
                        $this->productService->update($product, ['file_manager_file_ids' => $fileIds]);
                    }
                }
                if ($request->has('is_bundle')) {
                    $isBundle    = $request->boolean('is_bundle');
                    $bundleItems = (array) $request->input('bundle_items', []);
                    $this->productService->update($product, ['is_bundle' => $isBundle, 'bundle_items' => $bundleItems]);
                }

                // Create one stock layer per opening batch
                $openingBatches = (array) $request->input('opening_batches', []);
                foreach ($openingBatches as $batch) {
                    $qty = (float) ($batch['quantity'] ?? 0);
                    if ($qty <= 0) continue;
                    $cost     = isset($batch['cost_price'])      && $batch['cost_price']      !== null ? (float) $batch['cost_price']      : (float) ($product->cost_price ?? 0);
                    $selling  = isset($batch['selling_price'])   && $batch['selling_price']   !== null ? (float) $batch['selling_price']   : null;
                    $wholesale = isset($batch['wholesale_price']) && $batch['wholesale_price'] !== null ? (float) $batch['wholesale_price'] : ($product->wholesale_price !== null ? (float) $product->wholesale_price : null);
                    $this->stockLayers->createManualLayer($business, $product, $qty, $cost, $selling, $wholesale);
                }
            }
        }

        return response()->json([
            'message' => 'Product added.',
            'data'    => $quickResult,
        ], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_products');

        if (! $this->productService->productForBusiness($business, $product)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'name'                      => 'sometimes|required|string|max:255',
            'sku'                       => 'nullable|string|max:120',
            'model_no'                  => 'nullable|string|max:120',
            'size'                      => 'nullable|string|max:120',
            'mfg_date'                  => 'nullable|date',
            'exp_date'                  => 'nullable|date',
            'description'               => 'nullable|string|max:5000',
            'unit_price'                => 'nullable|numeric|min:0',
            'cost_price'                => 'nullable|numeric|min:0',
            'wholesale_price'           => 'nullable|numeric|min:0',
            'stock_quantity'            => 'nullable|numeric|min:0',
            'product_unit_id'           => 'nullable|integer',
            'is_active'                 => 'boolean',
            'is_bundle'                 => 'boolean',
            'has_warranty'              => 'boolean',
            'warranty_duration'         => 'nullable|string|max:120',
            'track_expiry'              => 'boolean',
            'courier_delivery'          => 'boolean',
            'loyalty_redeemable'        => 'boolean',
            'is_customer_required'      => 'boolean',
            'is_rental'                 => 'boolean',
            'rental_daily_rate'         => 'nullable|numeric|min:0',
            'rental_max_days'           => 'nullable|integer|min:1',
            'rental_late_fee_multiplier' => 'nullable|numeric|min:0',
            'rental_needs_cleaning'     => 'boolean',
            'is_subscription'           => 'boolean',
            'item_wise_tax'             => 'boolean',
            'item_wise_discount'        => 'boolean',
            'product_category_ids'      => 'nullable|array',
            'product_category_ids.*'    => 'integer',
            'product_brand_ids'         => 'nullable|array',
            'product_brand_ids.*'       => 'integer',
            'file_manager_file_ids'     => 'nullable|array',
            'file_manager_file_ids.*'   => 'integer',
            'bundle_items'              => 'nullable|array',
            'bundle_items.*.product_id' => 'required_with:bundle_items|integer',
            'bundle_items.*.quantity'   => 'required_with:bundle_items|numeric|min:0.001',
        ]);

        if ($request->has('is_active'))              $data['is_active']            = $request->boolean('is_active');
        if ($request->has('is_bundle'))              $data['is_bundle']            = $request->boolean('is_bundle');
        if ($request->has('has_warranty'))           $data['has_warranty']         = $request->boolean('has_warranty');
        if ($request->has('track_expiry'))           $data['track_expiry']         = $request->boolean('track_expiry');
        if ($request->has('courier_delivery'))       $data['courier_delivery']     = $request->boolean('courier_delivery');
        if ($request->has('loyalty_redeemable'))     $data['loyalty_redeemable']   = $request->boolean('loyalty_redeemable');
        if ($request->has('is_customer_required'))   $data['is_customer_required'] = $request->boolean('is_customer_required');
        if ($request->has('is_rental'))              $data['is_rental']            = $request->boolean('is_rental');
        if ($request->has('rental_daily_rate'))      $data['rental_daily_rate']     = $request->input('rental_daily_rate') !== null ? (float) $request->input('rental_daily_rate') : null;
        if ($request->has('rental_max_days'))        $data['rental_max_days']       = $request->input('rental_max_days') !== null ? (int) $request->input('rental_max_days') : null;
        if ($request->has('rental_late_fee_multiplier')) $data['rental_late_fee_multiplier'] = $request->input('rental_late_fee_multiplier') !== null ? (float) $request->input('rental_late_fee_multiplier') : null;
        if ($request->has('rental_needs_cleaning'))  $data['rental_needs_cleaning'] = $request->boolean('rental_needs_cleaning');
        if ($request->has('is_subscription'))        $data['is_subscription']      = $request->boolean('is_subscription');
        if ($request->has('item_wise_tax'))          $data['item_wise_tax']        = $request->boolean('item_wise_tax');
        if ($request->has('item_wise_discount'))     $data['item_wise_discount']   = $request->boolean('item_wise_discount');

        $this->productService->update($product, $data);

        return response()->json([
            'message' => 'Product updated.',
            'data'    => ['id' => $product->id, 'name' => $product->fresh()->name],
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'inv_products');

        if (! $this->productService->productForBusiness($business, $product)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $this->productService->delete($product);

        return response()->json(['message' => 'Product deleted.']);
    }

    public function import(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $request->validate([
            'rows'                  => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.name'           => ['required', 'string', 'max:255'],
            'rows.*.sku'            => ['nullable', 'string', 'max:120'],
            'rows.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'rows.*.stock_quantity' => ['nullable', 'numeric', 'min:0'],
            'rows.*.wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'rows.*.category'       => ['nullable', 'string', 'max:255'],
            'rows.*.brand'          => ['nullable', 'string', 'max:255'],
            'rows.*.description'    => ['nullable', 'string', 'max:5000'],
        ]);

        $rows       = $request->input('rows');
        $imported   = 0;
        $skipped    = 0;
        $errors     = [];
        $catCache   = [];
        $brandCache = [];

        foreach ($rows as $idx => $row) {
            try {
                $result  = $this->quickCreate->create($business, [
                    'name'           => $row['name'],
                    'sku'            => $row['sku'] ?? null,
                    'unit_price'     => (float) $row['unit_price'],
                    'stock_quantity' => isset($row['stock_quantity']) ? (float) $row['stock_quantity'] : 0,
                ]);


                $product = $business->products()->find($result['id'] ?? null);

                if ($product) {
                    $fill2 = [];
                    if (!empty($row['description']))    $fill2['description']    = $row['description'];
                    if (isset($row['wholesale_price']) && $row['wholesale_price'] !== '') {
                        $fill2['wholesale_price'] = (float) $row['wholesale_price'];
                    }
                    if ($fill2) $product->fill($fill2)->save();

                    if (!empty($row['category'])) {
                        $catName = trim($row['category']);
                        if (!isset($catCache[$catName])) {
                            $cat = ProductCategory::firstOrCreate(
                                ['business_id' => $business->id, 'name' => $catName],
                                ['is_active' => true],
                            );
                            $catCache[$catName] = $cat->id;
                        }
                        $product->categories()->syncWithoutDetaching([$catCache[$catName]]);
                    }

                    if (!empty($row['brand'])) {
                        $brandName = trim($row['brand']);
                        if (!isset($brandCache[$brandName])) {
                            $brand = ProductBrand::firstOrCreate(
                                ['business_id' => $business->id, 'name' => $brandName],
                                ['is_active' => true],
                            );
                            $brandCache[$brandName] = $brand->id;
                        }
                        $product->brands()->syncWithoutDetaching([$brandCache[$brandName]]);
                    }
                }

                $imported++;
            } catch (\Illuminate\Validation\ValidationException $e) {
                $skipped++;
                $errors[] = [
                    'row'     => $idx + 1,
                    'name'    => $row['name'] ?? '',
                    'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                ];
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = [
                    'row'     => $idx + 1,
                    'name'    => $row['name'] ?? '',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ]);
    }
}
