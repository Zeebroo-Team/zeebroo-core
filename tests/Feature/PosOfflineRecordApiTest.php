<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Bill;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\PosOfflineRecordApiController;
use Modules\Pos\Models\Customer;
use Modules\Pos\Services\PosOfflineRecordService;

// Exercise validation and responses without the application's database.
function offlineRecordFixture(): array
{
    $service = Mockery::mock(PosOfflineRecordService::class);
    $controller = new class($service) extends PosOfflineRecordApiController {
        protected function businessOrAbort(Request $request): Business
        {
            return (new Business)->forceFill(['id' => 12]);
        }

        protected function abortUnlessPerm(Request $request, Business $business, string $permKey): void
        {
        }
    };

    return [$controller, $service];
}

function offlineRecordRequest(array $body): Request
{
    $request = Request::create('/offline', 'POST', $body);
    $request->setUserResolver(fn () => new App\Models\User);

    return $request;
}

const OFFLINE_UUID = '5f0c2b8e-3a51-4c8e-9d2a-6b7f1e0a9c11';

test('a new offline customer is created with 201 and its id', function () {
    [$controller, $service] = offlineRecordFixture();
    $service->shouldReceive('saveCustomer')->once()
        ->withArgs(fn ($business, $data) => $business->id === 12 && $data['client_uuid'] === OFFLINE_UUID && $data['name'] === 'Nimal')
        ->andReturn(['customer' => (new Customer)->forceFill(['id' => 40, 'name' => 'Nimal', 'client_uuid' => OFFLINE_UUID]), 'duplicate' => false, 'matched' => false]);

    $response = $controller->storeCustomer(offlineRecordRequest(['client_uuid' => OFFLINE_UUID, 'name' => 'Nimal']));

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getData(true)['data']['id'])->toBe(40);
});

test('a re-uploaded or phone-matched customer answers 200', function (bool $duplicate, bool $matched) {
    [$controller, $service] = offlineRecordFixture();
    $service->shouldReceive('saveCustomer')->once()
        ->andReturn(['customer' => (new Customer)->forceFill(['id' => 40, 'name' => 'Nimal']), 'duplicate' => $duplicate, 'matched' => $matched]);

    $response = $controller->storeCustomer(offlineRecordRequest(['client_uuid' => OFFLINE_UUID, 'name' => 'Nimal']));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['duplicate'])->toBe($duplicate)
        ->and($response->getData(true)['matched'])->toBe($matched);
})->with([[true, false], [false, true]]);

test('an offline customer needs a name and a uuid', function (array $body) {
    [$controller, $service] = offlineRecordFixture();
    $service->shouldNotReceive('saveCustomer');

    $controller->storeCustomer(offlineRecordRequest($body));
})->with([
    [['client_uuid' => OFFLINE_UUID]],
    [['name' => 'Nimal']],
    [['client_uuid' => 'not-a-uuid', 'name' => 'Nimal']],
])->throws(ValidationException::class);

test('an offline expense is saved for the day it was spent', function () {
    [$controller, $service] = offlineRecordFixture();
    $spent = now()->subDays(2)->utc()->format('Y-m-d\TH:i:s.v\Z');
    $service->shouldReceive('saveExpense')->once()
        ->withArgs(fn ($user, $business, $data, CarbonImmutable $spentOn) => $data['amount'] == 450.5
            && $spentOn->toDateString() === now()->subDays(2)->toDateString())
        ->andReturn(['bill' => (new Bill)->forceFill(['id' => 9, 'name' => 'Tea', 'recurring_cost' => 450.5]), 'duplicate' => false]);

    $response = $controller->storeExpense(offlineRecordRequest([
        'client_uuid' => OFFLINE_UUID, 'name' => 'Tea', 'amount' => 450.5,
        'bill_category' => 'other', 'bill_category_other' => 'Refreshments', 'spent_at' => $spent,
    ]));

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getData(true)['data']['id'])->toBe(9);
});

test('an offline expense older than 7 days is refused', function () {
    [$controller, $service] = offlineRecordFixture();
    $service->shouldNotReceive('saveExpense');

    $response = $controller->storeExpense(offlineRecordRequest([
        'client_uuid' => OFFLINE_UUID, 'name' => 'Tea', 'amount' => 10,
        'bill_category' => 'water', 'spent_at' => now()->subDays(8)->toIso8601String(),
    ]));

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['code'])->toBe('offline_record_too_old');
});

test('a future expense time is clamped to now', function () {
    [$controller, $service] = offlineRecordFixture();
    $service->shouldReceive('saveExpense')->once()
        ->withArgs(fn ($user, $business, $data, CarbonImmutable $spentOn) => $spentOn->lte(now()->addSecond()))
        ->andReturn(['bill' => (new Bill)->forceFill(['id' => 9]), 'duplicate' => false]);

    $controller->storeExpense(offlineRecordRequest([
        'client_uuid' => OFFLINE_UUID, 'name' => 'Tea', 'amount' => 10,
        'bill_category' => 'gas', 'spent_at' => now()->addDays(3)->toIso8601String(),
    ]));
});

test('an expense needs a positive amount, a known category, and a description for other', function (array $override) {
    [$controller, $service] = offlineRecordFixture();
    $service->shouldNotReceive('saveExpense');

    $controller->storeExpense(offlineRecordRequest(array_merge([
        'client_uuid' => OFFLINE_UUID, 'name' => 'Tea', 'amount' => 10,
        'bill_category' => 'water', 'spent_at' => now()->toIso8601String(),
    ], $override)));
})->with([
    [['amount' => 0]],
    [['bill_category' => 'snacks']],
    [['bill_category' => 'other']],
])->throws(ValidationException::class);
