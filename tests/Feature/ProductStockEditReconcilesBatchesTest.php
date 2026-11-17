<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductStockLayer;
use Modules\Product\Services\ProductService;

// Builds only the tables a product save and the stock-batch reconcile touch.
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

function stockProduct(float $stock): Product
{
    return Product::query()->forceCreate(['business_id' => 84, 'name' => 'Coolant 4L', 'unit_price' => 2150, 'stock_quantity' => $stock])->fresh();
}

function stockBatch(Product $product, float $remaining, string $receivedAt = '2026-10-28'): ProductStockLayer
{
    return ProductStockLayer::query()->forceCreate([
        'business_id' => 84,
        'product_id' => $product->id,
        'quantity_received' => $remaining,
        'quantity_remaining' => $remaining,
        'selling_unit_price' => 2150,
        'received_at' => $receivedAt,
    ]);
}

function sellableQty(Product $product): float
{
    return (float) ProductStockLayer::query()->where('product_id', $product->id)->sum('quantity_remaining');
}

test('raising stock on a product with batches adds a batch for the difference', function () {
    $product = stockProduct(4);
    stockBatch($product, 4);

    app(ProductService::class)->update($product, ['stock_quantity' => 500]);

    expect((float) $product->fresh()->stock_quantity)->toBe(500.0)
        ->and(sellableQty($product))->toBe(500.0)
        ->and(ProductStockLayer::query()->where('product_id', $product->id)->count())->toBe(2)
        ->and((float) ProductStockLayer::query()->latest('id')->value('selling_unit_price'))->toBe(2150.0);
});

test('lowering stock draws the difference from the oldest batches first', function () {
    $product = stockProduct(30);
    $old = stockBatch($product, 10, '2026-10-01');
    $new = stockBatch($product, 20, '2026-10-20');

    app(ProductService::class)->update($product, ['stock_quantity' => 15]);

    expect((float) $old->fresh()->quantity_remaining)->toBe(0.0)
        ->and((float) $new->fresh()->quantity_remaining)->toBe(15.0)
        ->and(sellableQty($product))->toBe(15.0);
});

test('a product without batches is left without batches', function () {
    $product = stockProduct(4);

    app(ProductService::class)->update($product, ['stock_quantity' => 500]);

    expect((float) $product->fresh()->stock_quantity)->toBe(500.0)
        ->and(ProductStockLayer::query()->count())->toBe(0);
});

test('saving without changing stock leaves batches alone', function () {
    $product = stockProduct(500);
    stockBatch($product, 4);

    app(ProductService::class)->update($product, ['name' => 'Coolant 4L (blue)']);

    expect(sellableQty($product))->toBe(4.0);
});
