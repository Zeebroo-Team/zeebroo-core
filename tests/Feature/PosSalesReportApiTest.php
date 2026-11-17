<?php

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\PosSalesReportApiController;
use Modules\Pos\Services\SalesReportService;

// Exercise filter parsing without the application's database.
function salesReportFixture(): array
{
    $service = Mockery::mock(SalesReportService::class);
    $controller = new class($service) extends PosSalesReportApiController {
        protected function businessOrAbort(Request $request): Business
        {
            return (new Business)->forceFill(['id' => 12]);
        }

        protected function abortUnlessPerm(Request $request, Business $business, string $permKey): void {}
    };

    return [$controller, $service];
}

test('defaults to the last 30 days across every branch', function () {
    Carbon::setTestNow('2026-10-13 15:00:00');
    [$controller, $service] = salesReportFixture();
    $service->shouldReceive('build')->once()
        ->withArgs(fn ($business, $f) => $business->id === 12
            && $f['from']->toDateString() === '2026-09-14'
            && $f['to']->toDateString() === '2026-10-13'
            && $f['branch'] === null && $f['counter'] === null
            && $f['group_by'] === null && $f['txn_source'] === null
            && $f['page'] === 1 && $f['per_page'] === 25)
        ->andReturn(['summary' => []]);

    $response = $controller->show(Request::create('/reports/sales'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['data'])->toBe(['summary' => []]);
    Carbon::setTestNow();
});

test('swaps reversed dates and parses branch, counter and source filters', function () {
    [$controller, $service] = salesReportFixture();
    $service->shouldReceive('build')->once()
        ->withArgs(fn ($business, $f) => $f['from']->toDateString() === '2026-01-01'
            && $f['to']->toDateString() === '2026-01-31'
            && $f['branch'] === 3 && $f['counter'] === 5
            && $f['group_by'] === 'week' && $f['txn_source'] === 'quotations' && $f['txn_status'] === 'open')
        ->andReturn([]);

    $controller->show(Request::create('/reports/sales', 'GET', [
        'date_from' => '2026-01-31', 'date_to' => '2026-01-01',
        'branch_id' => '3', 'counter_id' => '5', 'group_by' => 'week',
        'source' => 'quotations', 'status' => 'open',
    ]));
});

test('branch "none" selects unassigned sales and "all" clears the filter', function (string $input, $expected) {
    [$controller, $service] = salesReportFixture();
    $service->shouldReceive('build')->once()
        ->withArgs(fn ($business, $f) => $f['branch'] === $expected)
        ->andReturn([]);

    $controller->show(Request::create('/reports/sales', 'GET', ['branch_id' => $input]));
})->with([
    ['none', 'none'],
    ['all', null],
    ['abc', null],
]);

test('export returns every row on one page', function () {
    [$controller, $service] = salesReportFixture();
    $service->shouldReceive('build')->once()
        ->withArgs(fn ($business, $f) => $f['page'] === 1 && $f['per_page'] === 5000)
        ->andReturn([]);

    $controller->show(Request::create('/reports/sales', 'GET', ['export' => '1', 'page' => '4']));
});

test('rejects an unknown source', function () {
    [$controller, $service] = salesReportFixture();
    $service->shouldNotReceive('build');

    $controller->show(Request::create('/reports/sales', 'GET', ['source' => 'payroll']));
})->throws(\Illuminate\Validation\ValidationException::class);

test('product-wise report keeps the end date and caps the range at 30 days', function () {
    Carbon::setTestNow('2026-10-28 10:00:00');
    [$controller, $service] = salesReportFixture();
    $service->shouldReceive('products')->once()
        ->withArgs(fn ($business, $f) => $f['from']->toDateString() === '2026-09-29'
            && $f['to']->toDateString() === '2026-10-28'
            && $f['branch'] === 3 && $f['source'] === 'pos')
        ->andReturn(['range' => []]);

    $response = $controller->products(Request::create('/reports/sales/products', 'GET', [
        'date_from' => '2026-01-01', 'date_to' => '2026-10-28', 'branch_id' => '3', 'source' => 'pos',
    ]));

    expect($response->getData(true)['data']['range']['max_days'])->toBe(30);
});

test('product-wise report only covers today and the 29 days before it', function () {
    Carbon::setTestNow('2026-10-28 10:00:00');
    [$controller, $service] = salesReportFixture();
    $service->shouldReceive('products')->once()
        ->withArgs(fn ($business, $f) => $f['from']->toDateString() === '2026-09-29'
            && $f['to']->toDateString() === '2026-10-05')
        ->andReturn(['range' => []]);

    $response = $controller->products(Request::create('/reports/sales/products', 'GET', [
        'date_from' => '2026-08-01', 'date_to' => '2026-10-05',
    ]));

    expect($response->getData(true)['data']['range'])
        ->toMatchArray(['min_date' => '2026-09-29', 'max_date' => '2026-10-28']);
});

test('product-wise report pulls a range older than 30 days into the window', function () {
    Carbon::setTestNow('2026-10-28 10:00:00');
    [$controller, $service] = salesReportFixture();
    $service->shouldReceive('products')->once()
        ->withArgs(fn ($business, $f) => $f['from']->toDateString() === '2026-09-29'
            && $f['to']->toDateString() === '2026-09-29')
        ->andReturn(['range' => []]);

    $controller->products(Request::create('/reports/sales/products', 'GET', [
        'date_from' => '2026-08-01', 'date_to' => '2026-08-31',
    ]));
});

test('product-wise report treats "all" sources as no source filter', function () {
    Carbon::setTestNow('2026-10-13 15:00:00');
    [$controller, $service] = salesReportFixture();
    $service->shouldReceive('products')->once()
        ->withArgs(fn ($business, $f) => $f['source'] === null && $f['from']->diffInDays($f['to']) === 6.0)
        ->andReturn(['range' => []]);

    $controller->products(Request::create('/reports/sales/products', 'GET', [
        'date_from' => '2026-10-01', 'date_to' => '2026-10-07', 'source' => 'all',
    ]));
    Carbon::setTestNow();
});

test('product-wise report rejects quotation sources', function () {
    [$controller, $service] = salesReportFixture();
    $service->shouldNotReceive('products');

    $controller->products(Request::create('/reports/sales/products', 'GET', ['source' => 'quotations']));
})->throws(\Illuminate\Validation\ValidationException::class);

test('customer-wise report allows long ranges and parses the counter', function () {
    [$controller, $service] = salesReportFixture();
    $service->shouldReceive('customers')->once()
        ->withArgs(fn ($business, $f) => $f['from']->toDateString() === '2026-01-01'
            && $f['to']->toDateString() === '2026-10-28' && $f['counter'] === 4 && $f['branch'] === 'none')
        ->andReturn(['range' => []]);

    $controller->customers(Request::create('/reports/sales/customers', 'GET', [
        'date_from' => '2026-01-01', 'date_to' => '2026-10-28', 'counter_id' => '4', 'branch_id' => 'none',
    ]));
});
