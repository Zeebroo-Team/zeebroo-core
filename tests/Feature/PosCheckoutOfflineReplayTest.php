<?php

use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\PosCheckoutApiController;
use Modules\Pos\Models\Sale;
use Modules\Pos\Services\PosOnlineApiService;
use Modules\Pos\Services\PosSettingsService;
use Modules\Pos\Services\SaleService;

// Desktop offline mode: POST online/checkout replays a queued sale with
// client_uuid + sold_at. Exercised without the application's database.
function checkoutReplayFixture(?Sale $existing = null): array
{
    $sales = Mockery::mock(SaleService::class);
    $api = Mockery::mock(PosOnlineApiService::class);
    $api->shouldReceive('formatSale')->andReturnUsing(fn (Sale $s) => ['id' => $s->id, 'sale_number' => $s->sale_number]);
    $settings = Mockery::mock(PosSettingsService::class);
    $settings->shouldReceive('forBusiness')->andReturn(['payment_settlement_mode' => 'immediate']);

    $controller = new class($sales, $api, $settings, $existing) extends PosCheckoutApiController {
        public function __construct($sales, $api, $settings, private readonly ?Sale $existing)
        {
            parent::__construct($sales, $api, $settings);
        }

        protected function businessOrAbort(Request $request): Business
        {
            return (new Business)->forceFill(['id' => 12]);
        }

        protected function abortUnlessPerm(Request $request, Business $business, string $permKey): void
        {
        }

        protected function findByClientUuid(Business $business, string $clientUuid): ?Sale
        {
            return $this->existing;
        }
    };

    return [$controller, $sales];
}

function checkoutReplayRequest(array $body): Request
{
    $request = Request::create('/online/checkout', 'POST', array_merge([
        'payment_method'  => 'cash',
        'pos_customer_id' => 5,
        'items'           => [['product_id' => 3, 'quantity' => 2, 'custom_unit_price' => 2900]],
    ], $body));
    $request->setUserResolver(fn () => new App\Models\User);

    return $request;
}

const REPLAY_UUID = '7b1d2c3e-4f50-4a61-8b72-9c83d4e5f601';

// Mockery hands withArgs() the arguments by position — name the ones under test.
function checkoutReplayArgs(array $args): array
{
    return [
        'soldAt'             => $args[20] ?? null,
        'honourClientPrices' => $args[21] ?? false,
        'clientUuid'         => $args[22] ?? null,
        'offlineRef'         => $args[23] ?? null,
    ];
}

test('a replayed offline sale keeps the time it happened and the price paid', function () {
    [$controller, $sales] = checkoutReplayFixture();
    $soldAt = now()->subDays(2)->startOfMinute();
    $sales->shouldReceive('checkout')->once()
        ->withArgs(function (...$args) use ($soldAt) {
            $args = checkoutReplayArgs($args);

            return $args['soldAt']?->equalTo($soldAt)
                && $args['honourClientPrices'] === true
                && $args['clientUuid'] === REPLAY_UUID
                && $args['offlineRef'] === 'OFF-AB12-261102-0001';
        })
        ->andReturn((new Sale)->forceFill(['id' => 81, 'sale_number' => 'S-81', 'client_uuid' => REPLAY_UUID, 'sold_at' => $soldAt]));

    $response = $controller->store(checkoutReplayRequest([
        'client_uuid' => REPLAY_UUID,
        'offline_ref' => 'OFF-AB12-261102-0001',
        'sold_at'     => $soldAt->copy()->utc()->format('Y-m-d\TH:i:s.v\Z'),
    ]));

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getData(true)['duplicate'])->toBeFalse()
        ->and($response->getData(true)['data']['client_uuid'])->toBe(REPLAY_UUID);
});

test('an upload that was already saved returns that sale instead of a second one', function () {
    $saved = (new Sale)->forceFill(['id' => 81, 'sale_number' => 'S-81', 'client_uuid' => REPLAY_UUID]);
    [$controller, $sales] = checkoutReplayFixture($saved);
    $sales->shouldNotReceive('checkout');

    $response = $controller->store(checkoutReplayRequest([
        'client_uuid' => REPLAY_UUID,
        'offline_ref' => 'OFF-AB12-261102-0001',
        'sold_at'     => now()->subHour()->toIso8601String(),
    ]));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['duplicate'])->toBeTrue()
        ->and($response->getData(true)['data']['sale_number'])->toBe('S-81');
});

test('a normal online checkout is unchanged apart from carrying its uuid', function () {
    [$controller, $sales] = checkoutReplayFixture();
    $sales->shouldReceive('checkout')->once()
        ->withArgs(fn (...$args) => checkoutReplayArgs($args)['soldAt'] === null
            && checkoutReplayArgs($args)['honourClientPrices'] === false
            && checkoutReplayArgs($args)['clientUuid'] === REPLAY_UUID)
        ->andReturn((new Sale)->forceFill(['id' => 82, 'sale_number' => 'S-82']));

    $response = $controller->store(checkoutReplayRequest(['client_uuid' => REPLAY_UUID]));

    expect($response->getStatusCode())->toBe(201);
});

test('offline sales older than the limit are refused', function () {
    [$controller, $sales] = checkoutReplayFixture();
    $sales->shouldNotReceive('checkout');

    $response = $controller->store(checkoutReplayRequest([
        'client_uuid' => REPLAY_UUID,
        'sold_at'     => now()->subDays(8)->toIso8601String(),
    ]));

    expect($response->getStatusCode())->toBe(422);
});

test('gift cards and coupons on an offline sale are checked as of the sale time', function (array $body, int $codeArg) {
    [$controller, $sales] = checkoutReplayFixture();
    $soldAt = now()->subDays(2)->startOfMinute();
    $sales->shouldReceive('checkout')->once()
        ->withArgs(fn (...$args) => $args[$codeArg] !== null && checkoutReplayArgs($args)['soldAt']?->equalTo($soldAt))
        ->andReturn((new Sale)->forceFill(['id' => 83, 'sale_number' => 'S-83', 'sold_at' => $soldAt]));

    $response = $controller->store(checkoutReplayRequest(array_merge([
        'client_uuid' => REPLAY_UUID,
        'sold_at'     => $soldAt->copy()->utc()->toIso8601String(),
    ], $body)));

    expect($response->getStatusCode())->toBe(201);
})->with([
    'gift card' => [['gift_card_code' => 'GC1', 'gift_card_amount' => 50], 17],
    'coupon'    => [['coupon_code' => 'SAVE10'], 19],
]);

test('a rental sold offline may have a return date before today', function () {
    [$controller, $sales] = checkoutReplayFixture();
    $sales->shouldReceive('checkout')->once()
        ->andReturn((new Sale)->forceFill(['id' => 84, 'sale_number' => 'S-84', 'sold_at' => now()->subDays(3)]));

    $response = $controller->store(checkoutReplayRequest([
        'client_uuid' => REPLAY_UUID,
        'sold_at'     => now()->subDays(3)->toIso8601String(),
        'items'       => [['product_id' => 3, 'quantity' => 1, 'rental_return_date' => now()->subDay()->toDateString()]],
    ]));

    expect($response->getStatusCode())->toBe(201);
});

test('sold_at without a client_uuid is refused', function () {
    [$controller, $sales] = checkoutReplayFixture();
    $sales->shouldNotReceive('checkout');

    $request = checkoutReplayRequest(['sold_at' => now()->subHour()->toIso8601String()]);

    expect($controller->store($request)->getStatusCode())->toBe(422);
});
