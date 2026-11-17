<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\PosProductApiController;
use Modules\Pos\Services\PosCatalogService;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductStockLayer;
use Modules\Product\Services\ProductService;

// Builds only the tables a product save and the POS price lookup read.
beforeEach(function () {
    Schema::create('businesses', function (Blueprint $t) {
        $t->id();
        $t->string('name')->nullable();
        $t->timestamps();
    });
    Schema::create('products', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->unsignedBigInteger('branch_id')->nullable();
        $t->string('name');
        $t->string('sku')->nullable();
        $t->decimal('unit_price', 12, 2)->nullable();
        $t->decimal('stock_quantity', 12, 3)->default(0);
        $t->boolean('is_active')->default(true);
        $t->boolean('is_bundle')->default(false);
        $t->boolean('is_dynamic_pricing')->default(false);
        $t->boolean('dynamic_price_qty_linked')->default(false);
        $t->unsignedBigInteger('product_unit_id')->nullable();
        $t->unsignedBigInteger('file_manager_file_id')->nullable();
        $t->timestamps();
    });
    Schema::create('product_stock_layers', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->unsignedBigInteger('branch_id')->nullable();
        $t->unsignedBigInteger('product_id');
        $t->unsignedBigInteger('goods_receive_note_item_id')->nullable();
        $t->decimal('quantity_received', 12, 3)->default(0);
        $t->decimal('quantity_remaining', 12, 3)->default(0);
        $t->decimal('unit_cost', 12, 2)->default(0);
        $t->decimal('selling_unit_price', 12, 2)->nullable();
        $t->string('batch_sku')->nullable();
        $t->date('received_at')->nullable();
        $t->timestamps();
    });
    Schema::create('goods_receive_note_items', function (Blueprint $t) {
        $t->id();
        $t->decimal('selling_unit_price', 12, 2)->nullable();
        $t->timestamps();
    });
    Schema::create('product_categories', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('parent_id')->nullable();
        $t->integer('sort_order')->default(0);
        $t->string('name');
    });
    Schema::create('product_brands', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('product_images', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('product_id');
        $t->integer('sort_order')->default(0);
    });
    Schema::create('product_bundle_items', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('bundle_product_id');
        $t->integer('sort_order')->default(0);
    });
    Schema::create('product_product_category', function (Blueprint $t) {
        $t->unsignedBigInteger('product_id');
        $t->unsignedBigInteger('product_category_id');
        $t->timestamps();
    });
    Schema::create('product_product_brand', function (Blueprint $t) {
        $t->unsignedBigInteger('product_id');
        $t->unsignedBigInteger('product_brand_id');
        $t->timestamps();
    });

    DB::table('businesses')->insert(['id' => 84, 'name' => 'Shop']);
});

function priceProduct(float $price, array $attributes = []): Product
{
    return Product::query()->forceCreate(['business_id' => 84, 'name' => 'Lunch bag', 'unit_price' => $price] + $attributes)->fresh();
}

function priceBatch(Product $product, ?float $price, float $remaining = 50, array $attributes = []): ProductStockLayer
{
    return ProductStockLayer::query()->forceCreate([
        'business_id' => 84,
        'product_id' => $product->id,
        'quantity_received' => max($remaining, 50),
        'quantity_remaining' => $remaining,
        'selling_unit_price' => $price,
        'received_at' => '2026-10-31',
    ] + $attributes);
}

function posPrice(Product $product): ?float
{
    return app(PosCatalogService::class)->posMetaForProduct($product->fresh())['unit_sell_price'];
}

function updateProductPrice(Product $product, mixed $price): Product
{
    return app(ProductService::class)->update($product, ['unit_price' => $price]);
}

test('the POS sells at the new price after an imported product is edited', function () {
    $product = priceProduct(5200);
    priceBatch($product, 5200);
    expect(posPrice($product))->toBe(5200.0);

    updateProductPrice($product, 6000);

    expect(posPrice($product))->toBe(6000.0);
});

test('every in-stock batch on the old price moves, and its GRN line with it', function () {
    $product = priceProduct(5200);
    $itemId = DB::table('goods_receive_note_items')->insertGetId(['selling_unit_price' => 5200]);
    $received = priceBatch($product, 5200, 10, ['goods_receive_note_item_id' => $itemId]);
    $manual = priceBatch($product, 5200, 5);

    updateProductPrice($product, 6000);

    expect((float) $received->fresh()->selling_unit_price)->toBe(6000.0)
        ->and((float) $manual->fresh()->selling_unit_price)->toBe(6000.0)
        ->and((float) DB::table('goods_receive_note_items')->where('id', $itemId)->value('selling_unit_price'))->toBe(6000.0);
});

test('a batch priced differently keeps its price', function () {
    $product = priceProduct(5200);
    $own = priceBatch($product, 4500);

    updateProductPrice($product, 6000);

    expect((float) $own->fresh()->selling_unit_price)->toBe(4500.0);
});

test('a sold-out batch keeps the price it sold at', function () {
    $product = priceProduct(5200);
    $soldOut = priceBatch($product, 5200, 0);

    updateProductPrice($product, 6000);

    expect((float) $soldOut->fresh()->selling_unit_price)->toBe(5200.0);
});

test('saving without a price change leaves batches alone', function () {
    $product = priceProduct(6000);
    $stale = priceBatch($product, 5200);
    $touchedAt = $stale->updated_at;

    updateProductPrice($product, 6000);
    app(ProductService::class)->update($product, ['name' => 'Renamed']);

    expect((float) $stale->fresh()->selling_unit_price)->toBe(5200.0)
        ->and($stale->fresh()->updated_at->equalTo($touchedAt))->toBeTrue();
});

test('clearing the price does not change what the batches sell at', function (mixed $cleared) {
    $product = priceProduct(5200);
    $batch = priceBatch($product, 5200);

    updateProductPrice($product, $cleared);

    expect((float) $batch->fresh()->selling_unit_price)->toBe(5200.0);
})->with([[null], [0]]);

test('linked dynamic pricing batches stay at 1.00', function () {
    $product = priceProduct(1, ['is_dynamic_pricing' => true, 'dynamic_price_qty_linked' => true]);
    $batch = priceBatch($product, 1);

    updateProductPrice($product, 500);

    expect((float) $batch->fresh()->selling_unit_price)->toBe(1.0);
});

// Skips the business and permission lookup, which need the rest of the application's tables.
function productPriceController(): PosProductApiController
{
    return new class(
        app(\Modules\Pos\Services\PosProductQuickCreateService::class),
        app(ProductService::class),
        app(\Modules\Product\Services\ProductStockLayerService::class),
        app(\Modules\Pos\Services\PosSettingsService::class),
        app(\Modules\Pos\Services\PosProductExportService::class),
    ) extends PosProductApiController {
        protected function businessOrAbort(Request $request): Business
        {
            return Business::query()->findOrFail(84);
        }

        protected function abortUnlessPerm(Request $request, Business $business, string $permKey): void
        {
        }
    };
}

test('the API lists batches left on another price after a price change', function () {
    $product = priceProduct(5200);
    priceBatch($product, 5200);
    $other = priceBatch($product, 4500, 12, ['batch_sku' => 'U2058-02']);

    $response = productPriceController()->update(Request::create('/', 'PATCH', ['unit_price' => 6000]), $product);

    expect($response->getData(true)['data']['batches_on_other_price'])->toBe([[
        'id' => $other->id,
        'batch_sku' => 'U2058-02',
        'selling_unit_price' => 4500,
        'quantity_remaining' => 12,
    ]]);
});

test('the API lists nothing when the price did not change', function () {
    $product = priceProduct(6000);
    priceBatch($product, 5200);

    $response = productPriceController()->update(Request::create('/', 'PATCH', ['unit_price' => 6000]), $product);

    expect($response->getData(true)['data']['batches_on_other_price'])->toBe([]);
});
