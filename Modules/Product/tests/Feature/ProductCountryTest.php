<?php

use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductCountryService;

// Only the tables this feature touches: the full migration set has MySQL-only
// statements that SQLite (the test DB) can't run.
beforeEach(function () {
    foreach ([
        'database/migrations/0001_01_01_000000_create_users_table.php',
        'Modules/Business/database/migrations/2026_04_30_103500_create_businesses_table.php',
        'Modules/Product/database/migrations/2026_05_15_100000_create_products_table.php',
        'Modules/Product/database/migrations/2026_11_23_100000_create_product_countries_table.php',
    ] as $file) {
        (require base_path($file))->up();
    }
});

function countryTestBusiness(string $name = 'Shop'): Business
{
    // Raw inserts: the models' events reach tables this test doesn't create.
    $userId = DB::table('users')->insertGetId([
        'name'     => $name.' owner',
        'email'    => strtolower($name).uniqid().'@example.test',
        'password' => 'x',
    ]);
    $id = DB::table('businesses')->insertGetId(['user_id' => $userId, 'name' => $name, 'category' => 'retail']);

    return Business::findOrFail($id);
}

test('countries list, filter by status and count products using them once', function () {
    $business = countryTestBusiness();
    $service  = app(ProductCountryService::class);

    $lk = $service->create($business, ['name' => ' Sri Lanka ', 'code' => 'lk']);
    $cn = $service->create($business, ['name' => 'China', 'is_active' => false]);

    expect($lk->name)->toBe('Sri Lanka')->and($lk->code)->toBe('LK');

    // Same country as origin and manufacture counts the product once.
    Product::forceCreate(['business_id' => $business->id, 'name' => 'Tea', 'unit_price' => 1, 'origin_country_id' => $lk->id, 'manufacture_country_id' => $lk->id]);
    Product::forceCreate(['business_id' => $business->id, 'name' => 'Phone', 'unit_price' => 1, 'origin_country_id' => $lk->id, 'manufacture_country_id' => $cn->id]);

    $all = $service->list($business)->keyBy('name');
    expect($all['Sri Lanka']->products_count)->toBe(2)
        ->and($all['China']->products_count)->toBe(1)
        ->and($service->list($business, '', 'active')->pluck('name')->all())->toBe(['Sri Lanka'])
        ->and($service->list($business, '', 'inactive')->pluck('name')->all())->toBe(['China'])
        ->and($service->usageCount($cn))->toBe(1);

    $product = Product::where('name', 'Phone')->first();
    expect($product->originCountry->name)->toBe('Sri Lanka')
        ->and($product->manufactureCountry->name)->toBe('China');
});

test('imports match an existing country by name or code, else add it', function () {
    $business = countryTestBusiness();
    $service  = app(ProductCountryService::class);
    $lk = $service->create($business, ['name' => 'Sri Lanka', 'code' => 'LK', 'is_active' => false]);

    expect($service->findOrCreateByName($business, ' sri lanka ')->id)->toBe($lk->id)
        ->and($service->findOrCreateByName($business, 'lk')->id)->toBe($lk->id)
        ->and($service->findOrCreateByName($business, ''))->toBeNull();

    $new = $service->findOrCreateByName($business, 'Germany');
    expect($new->name)->toBe('Germany')->and($new->is_active)->toBeTrue()
        ->and($service->findOrCreateByName($business, 'GERMANY')->id)->toBe($new->id)
        ->and($business->productCountries()->count())->toBe(2);
});

test('a country only belongs to its own business', function () {
    $mine   = countryTestBusiness('Mine');
    $theirs = countryTestBusiness('Theirs');
    $service = app(ProductCountryService::class);

    $country = $service->create($theirs, ['name' => 'Japan']);

    expect($service->belongsToBusiness($mine, $country->id))->toBeFalse()
        ->and($service->belongsToBusiness($theirs, $country->id))->toBeTrue()
        ->and($service->belongsToBusiness($mine, null))->toBeTrue()
        ->and($service->countryForBusiness($mine, $country))->toBeNull();
});
