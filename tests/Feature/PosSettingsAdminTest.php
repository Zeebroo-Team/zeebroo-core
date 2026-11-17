<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Admin\PosSettingsAdminController;
use Modules\Pos\Services\PosSettingsAdminService;
use Modules\Pos\Services\PosSettingsService;
use Modules\Pos\Support\PosSettingsCatalog;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// Builds only the tables these settings read.
beforeEach(function () {
    Schema::create('settings', function (Blueprint $t) {
        $t->id();
        $t->string('scope_type');
        $t->unsignedBigInteger('scope_id');
        $t->string('key');
        $t->text('value')->nullable();
        $t->string('value_type')->default('string');
        $t->timestamps();
    });
    Schema::create('accounts', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('business_id')->nullable();
        $t->string('account_name');
    });

    DB::table('accounts')->insert([
        ['id' => 5, 'business_id' => 1, 'account_name' => 'Main cash'],
        ['id' => 6, 'business_id' => 2, 'account_name' => 'Another business'],
    ]);

    $this->business = (new Business)->forceFill(['id' => 1, 'name' => 'Shop One']);
    $this->service = app(PosSettingsAdminService::class);
    $this->stored = fn (string $key) => DB::table('settings')->where('scope_id', 1)->where('key', $key)->first();
});

test('every setting the POS apps receive is in the admin catalog', function () {
    // slug is worked out from the name; purchase_order_enabled follows purchasing_mode.
    $appFields = array_diff(array_keys(app(PosSettingsService::class)->forBusiness($this->business)), ['slug', 'purchase_order_enabled']);

    expect(array_values(array_diff($appFields, array_keys(PosSettingsCatalog::definitions()))))->toBe([]);
});

test('catalog defaults match what the apps fall back to once a value is deleted', function () {
    $values = app(PosSettingsService::class)->forBusiness($this->business);

    foreach (PosSettingsCatalog::definitions() as $field => $def) {
        // The name lives on the business; the threshold has no default; GRN depends on the accounts.
        if (in_array($field, ['business_name', 'large_sale_threshold', 'grn_payment_source'], true)) {
            continue;
        }
        expect($values[$field])->toEqual($def['default'], "Default of {$field}");
    }
});

test('settings are grouped by the apps that use them, and shared ones show under each', function () {
    $panel = $this->service->panel($this->business);
    $mobile = array_merge(...array_values($panel['groups']['mobile']['sections']));
    $desktop = array_merge(...array_values($panel['groups']['desktop']['sections']));

    expect(array_keys($panel['groups']))->toBe(['desktop', 'mobile', 'lite', 'web'])
        ->and($desktop)->toContain('receipt_footer', 'rental_enabled')
        ->and($mobile)->toContain('receipt_footer')
        ->and($mobile)->not->toContain('rental_enabled')
        ->and($panel['groups']['desktop']['sections'])->toHaveKey('Receipt');
});

test('a value not saved yet shows its default, and a saved one its value', function () {
    $this->business->setSetting('pos.receipt_language', 'si');

    $rows = $this->service->panel($this->business)['rows'];

    expect($rows['receipt_footer']['saved'])->toBeFalse()
        ->and($rows['receipt_footer']['value'])->toBe('Thank you for your purchase!')
        ->and($rows['receipt_language']['saved'])->toBeTrue()
        ->and($rows['receipt_language']['display'])->toBe('Sinhala');
});

test('edit controls get their dropdown options', function () {
    $rows = $this->service->panel($this->business)['rows'];

    expect(array_column($rows['stock_selection_mode']['options'], 'value'))->toBe(['fifo', 'choose', 'last_price'])
        ->and(array_column($rows['default_deposit_account_id']['options'], 'label'))->toBe(['None', 'Main cash'])
        ->and(array_column($rows['timezone']['options'], 'value'))->toContain('Asia/Colombo')
        ->and(array_column($rows['currency']['options'], 'value'))->toContain('LKR');
});

test('a currency outside the short list stays selectable', function () {
    $this->business->setSetting('business.currency', 'KES');

    expect(array_column($this->service->panel($this->business)['rows']['currency']['options'], 'value'))->toContain('KES');
});

test('edits get the same clean-up as the apps', function () {
    $this->service->update($this->business, 'invoice_prefix', 'abc');
    $this->service->update($this->business, 'receipt_logo_size', 150);
    $this->service->update($this->business, 'show_business_name', false);

    expect(($this->stored)('invoice.prefix')->value)->toBe('ABC')
        ->and(($this->stored)('pos.receipt_logo_size')->value)->toBe('150')
        ->and(($this->stored)('pos.show_business_name')->value)->toBe('0');
});

test('the system default theme can be chosen again', function () {
    $this->service->update($this->business, 'display_theme', 'dark');
    $this->service->update($this->business, 'display_theme', 'inherit');

    expect(($this->stored)('pos.display_theme')->value)->toBe('inherit');
});

test('tax rules, delivery partners, courier services and purchasing are saved', function () {
    $this->service->update($this->business, 'tax_rules', [['name' => 'VAT', 'type' => 'percentage', 'value' => 18]]);
    $this->service->update($this->business, 'delivery_methods', ['dhl', 'uber']);
    $this->service->update($this->business, 'courier_services', ['domex' => ['enabled' => true, 'charge' => 350]]);
    $this->service->update($this->business, 'purchasing_mode', 'direct_only');
    $this->service->update($this->business, 'large_sale_threshold', 5000);

    $values = app(PosSettingsService::class)->forBusiness($this->business);
    $rows = $this->service->panel($this->business)['rows'];

    expect($values['tax_rules'][0]['name'])->toBe('VAT')
        ->and($values['delivery_methods'])->toBe(['dhl', 'uber'])
        ->and($values['courier_services']['domex'])->toEqual(['enabled' => true, 'charge' => 350])
        ->and($values['purchasing_mode'])->toBe('direct_only')
        ->and($rows['tax_rules']['display'])->toContain('VAT 18')
        ->and($rows['courier_services']['display'])->toBe('Domex (350)')
        ->and($rows['large_sale_threshold']['value'])->toEqual(5000);
});

test('invalid values are refused', function (string $setting, mixed $value) {
    $this->service->update($this->business, $setting, $value);
})->with([
    'not a dropdown option' => ['stock_selection_mode', 'whatever'],
    'not a boolean' => ['tax_enabled', 'maybe'],
    'over the maximum' => ['receipt_logo_size', 999],
    'under the minimum' => ['invoice_next_number', 0],
    'not a timezone' => ['timezone', 'Mars/Base'],
    'not a web link' => ['receipt_logo_url', 'javascript:alert(1)'],
    'another business account' => ['default_deposit_account_id', 6],
    'unknown delivery partner' => ['delivery_methods', ['pigeon']],
    'tax rule without a name' => ['tax_rules', [['name' => '', 'type' => 'percentage', 'value' => 5]]],
    'empty business name' => ['business_name', ''],
])->throws(ValidationException::class);

test('deleting removes the saved value and the apps go back to the default', function () {
    $this->service->update($this->business, 'receipt_footer', 'Come again');
    $this->service->delete($this->business, 'receipt_footer');

    $row = $this->service->panel($this->business)['rows']['receipt_footer'];

    expect(($this->stored)('pos.receipt_footer'))->toBeNull()
        ->and($row['saved'])->toBeFalse()
        ->and($row['value'])->toBe('Thank you for your purchase!');
});

test('deleting a setting stored in two rows removes both', function () {
    $this->service->update($this->business, 'purchasing_mode', 'both');
    $this->service->delete($this->business, 'purchasing_mode');

    expect(($this->stored)('pos.purchasing_mode'))->toBeNull()
        ->and(($this->stored)('pos.purchase_order_enabled'))->toBeNull();
});

test('required settings cannot be deleted', function (string $setting) {
    $this->service->delete($this->business, $setting);
})->with(['currency', 'business_name'])->throws(ValidationException::class);

test('saved POS rows outside the catalog are listed and keep their type', function () {
    $this->business->setSetting('tax.rate', 15);
    $this->business->setSetting('pos.choose_stock', true);

    $panel = $this->service->panel($this->business);
    $this->service->update($this->business, 'tax.rate', '20');

    expect($panel['other'])->toBe(['pos.choose_stock', 'tax.rate'])
        ->and($panel['rows']['tax.rate']['type'])->toBe('number')
        ->and($panel['rows']['pos.choose_stock']['type'])->toBe('toggle')
        ->and(($this->stored)('tax.rate')->value)->toBe('20')
        ->and(($this->stored)('tax.rate')->value_type)->toBe('integer');

    $this->service->delete($this->business, 'tax.rate');
    expect(($this->stored)('tax.rate'))->toBeNull();
});

test('settings that are not POS settings cannot be reached', function (string $action) {
    $this->business->setSetting('mail.smtp_password', 'secret');
    $this->business->setSetting('business.subscription_status', 'active');

    expect($this->service->panel($this->business)['other'])->toBe([]);

    $action === 'update'
        ? $this->service->update($this->business, 'mail.smtp_password', 'changed')
        : $this->service->delete($this->business, 'mail.smtp_password');
})->with(['update', 'delete'])->throws(NotFoundHttpException::class);

test('another business keeps its own values', function () {
    $other = (new Business)->forceFill(['id' => 2]);
    $other->setSetting('pos.receipt_footer', 'Theirs');

    $this->service->update($this->business, 'receipt_footer', 'Ours');
    $this->service->delete($this->business, 'receipt_footer');

    expect($other->getSetting('pos.receipt_footer'))->toBe('Theirs');
});

test('the panel renders every app and escapes saved text', function () {
    $this->business->setSetting('pos.receipt_header', '<script>alert(1)</script>');

    $html = app(PosSettingsAdminController::class)->index($this->business)->getData(true)['html'];

    expect($html)->toContain('POS Desktop', 'POS Mobile', 'POS Lite', 'POS Web', '&lt;script&gt;alert(1)')
        ->and($html)->not->toContain('<script>alert(1)');
});

test('the routes are admin only', function (string $name) {
    expect(Route::getRoutes()->getByName($name)->gatherMiddleware())->toContain('auth', 'role:admin');
})->with([
    'admin.businesses.pos-settings.index',
    'admin.businesses.pos-settings.update',
    'admin.businesses.pos-settings.destroy',
]);
