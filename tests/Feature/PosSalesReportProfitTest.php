<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Models\Business;
use Modules\Pos\Services\SalesReportService;

// Builds only the tables the profit figures read — the report skips the rest.
beforeEach(function () {
    Schema::create('products', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->string('name');
        $t->decimal('cost_price', 12, 2)->nullable();
    });
    Schema::create('pos_customers', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('pos_sales', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id');
        $t->unsignedBigInteger('branch_id')->nullable();
        $t->unsignedBigInteger('pos_counter_id')->nullable();
        $t->unsignedBigInteger('user_id')->nullable();
        $t->unsignedBigInteger('pos_customer_id')->nullable();
        $t->string('sale_number');
        $t->string('status');
        $t->string('payment_method')->default('cash');
        $t->string('channel')->nullable();
        $t->decimal('subtotal', 12, 2);
        $t->decimal('discount_amount', 12, 2)->default(0);
        $t->decimal('coupon_discount', 12, 2)->default(0);
        $t->decimal('gift_card_amount', 12, 2)->default(0);
        $t->decimal('total', 12, 2);
        $t->decimal('amount_paid', 12, 2)->default(0);
        $t->boolean('is_settled')->default(true);
        $t->dateTime('sold_at');
    });
    Schema::create('pos_sale_items', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('pos_sale_id');
        $t->unsignedBigInteger('product_id')->nullable();
        $t->unsignedBigInteger('service_item_id')->nullable();
        $t->string('product_name');
        $t->decimal('quantity', 12, 3);
        $t->decimal('unit_cost', 12, 2)->nullable();
        $t->decimal('discount_amount', 12, 2)->default(0);
        $t->decimal('unit_sell_price', 12, 2);
        $t->decimal('line_total', 12, 2);
    });

    DB::table('products')->insert([
        ['id' => 1, 'business_id' => 7, 'name' => 'Water',  'cost_price' => null], // cost comes from its stock batch
        ['id' => 2, 'business_id' => 7, 'name' => 'Lamp',   'cost_price' => 50],
        ['id' => 3, 'business_id' => 7, 'name' => 'Soap',   'cost_price' => 30],   // sold without a stock batch
        ['id' => 4, 'business_id' => 7, 'name' => 'Poster', 'cost_price' => null], // no cost anywhere
    ]);

    $sale = function (int $id, float $subtotal, float $billDiscount, array $items) {
        DB::table('pos_sales')->insert([
            'id' => $id, 'business_id' => 7, 'sale_number' => "POS-{$id}", 'status' => 'completed',
            'subtotal' => $subtotal, 'discount_amount' => $billDiscount, 'total' => $subtotal - $billDiscount,
            'sold_at' => '2026-10-10 10:00:00',
        ]);
        foreach ($items as $item) {
            DB::table('pos_sale_items')->insert(['pos_sale_id' => $id] + $item);
        }
    };

    // 5 × Water: cost 100, list 100, item discount 20 → sold at 80 (loss 100)
    $sale(1, 400, 0, [['product_id' => 1, 'product_name' => 'Water', 'quantity' => 5, 'unit_cost' => 100,
        'discount_amount' => 20, 'unit_sell_price' => 80, 'line_total' => 400]]);
    // Lamp: cost 50 (batch recorded 0 — cost entered later), sold 100, bill discount 60 → 40 (loss 10)
    $sale(2, 100, 60, [['product_id' => 2, 'product_name' => 'Lamp', 'quantity' => 1, 'unit_cost' => 0,
        'unit_sell_price' => 100, 'line_total' => 100]]);
    // Soap: no batch (no unit cost), product cost 30, sold 50 (profit 20)
    // + Poster: no cost price at all, sold 200 — left out of profit
    // + a service line: no goods cost, sold 70 (profit 70)
    $sale(3, 320, 0, [
        ['product_id' => 3, 'product_name' => 'Soap', 'quantity' => 1, 'unit_cost' => null, 'unit_sell_price' => 50, 'line_total' => 50],
        ['product_id' => 4, 'product_name' => 'Poster', 'quantity' => 1, 'unit_cost' => null, 'unit_sell_price' => 200, 'line_total' => 200],
        ['service_item_id' => 9, 'product_name' => 'Fitting', 'quantity' => 1, 'unit_cost' => 0, 'unit_sell_price' => 70, 'line_total' => 70],
    ]);

    $this->report = app(SalesReportService::class)->build((new Business)->forceFill(['id' => 7]), [
        'from' => Carbon::parse('2026-10-01'), 'to' => Carbon::parse('2026-10-31'),
        'branch' => null, 'counter' => null, 'group_by' => null, 'txn_source' => null,
        'txn_status' => null, 'q' => null, 'page' => 1, 'per_page' => 25,
    ]);
});

test('items sold below cost reduce gross profit', function () {
    $s = $this->report['summary'];

    // Costed sales: Water 400 + Lamp 40 + Soap 50 + Fitting 70 = 560
    // Cost: Water 500 + Lamp 50 + Soap 30 + Fitting 0 = 580
    expect($s['pos_total'])->toEqual(760)
        ->and($s['costed_sales'])->toEqual(560)
        ->and($s['cogs'])->toEqual(580)
        ->and($s['gross_profit'])->toEqual(-20)
        ->and($s['gross_margin'])->toEqual(-3.6)
        ->and($s['below_cost_lines'])->toBe(2)
        ->and($s['below_cost_loss'])->toEqual(110);
});

test('sales without any cost price are left out of profit, not counted as 100% margin', function () {
    $s = $this->report['summary'];

    expect($s['uncosted_sales'])->toEqual(200)
        ->and($s['uncosted_lines'])->toBe(1)
        ->and($s['pos_total'] - $s['uncosted_sales'] - $s['cogs'])->toEqual($s['gross_profit']);
});

test('discounts include per-item discounts as well as the bill discount', function () {
    $s = $this->report['summary'];

    // Water item discount 20 × 5 = 100, Lamp bill discount 60
    expect($s['discounts'])->toEqual(160)
        ->and($s['discounted_count'])->toBe(2)
        ->and($s['gross_sales'])->toEqual(920);
});

test('product and category rows carry the bill discount and add up to the summary', function () {
    $products = collect($this->report['top_products'])->keyBy('name');

    expect($products['Water']['profit'])->toEqual(-100)
        ->and($products['Lamp']['revenue'])->toEqual(40)
        ->and($products['Lamp']['profit'])->toEqual(-10)
        ->and($products['Soap']['profit'])->toEqual(20)
        ->and($products['Poster']['profit'])->toBeNull()
        ->and($products['Fitting']['profit'])->toEqual(70);

    $categories = collect($this->report['by_category']);
    expect($categories->sum('total'))->toEqual($this->report['summary']['pos_total'])
        ->and($categories->sum('profit'))->toEqual($this->report['summary']['gross_profit']);
});
