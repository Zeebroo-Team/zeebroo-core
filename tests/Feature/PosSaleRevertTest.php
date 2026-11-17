<?php

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\PosSaleRevertApiController;
use Modules\Pos\Models\Sale;
use Modules\Pos\Models\SaleRevert;
use Modules\Pos\Services\PosOnlineApiService;
use Modules\Pos\Services\SaleRevertService;

// Exercise the revert endpoint contract without the application's database.
function revertController(SaleRevertService $reverts, PosOnlineApiService $api): PosSaleRevertApiController
{
    return new class($reverts, $api) extends PosSaleRevertApiController {
        protected function businessOrAbort(Request $request): Business
        {
            return (new Business)->forceFill(['id' => 12, 'user_id' => 1]);
        }

        protected function abortUnlessPerm(Request $request, Business $business, string $permKey): void
        {
        }
    };
}

function revertSale(): Sale
{
    return new class extends Sale {
        public function fresh($with = [])
        {
            return $this;
        }
    };
}

function revertRequest(array $body): Request
{
    $request = Request::create('/sales/7/revert', 'POST', $body);
    $request->setUserResolver(fn () => new App\Models\User);

    return $request;
}

test('a revert passes the reason and notes to the service', function () {
    $sale = revertSale()->forceFill(['id' => 7, 'business_id' => 12, 'sale_number' => 'POS-0007']);

    $revert = (new SaleRevert)->forceFill(['id' => 3, 'revert_number' => 'REV-0003', 'sale_number' => 'POS-0007']);

    $reverts = Mockery::mock(SaleRevertService::class);
    $reverts->shouldReceive('revert')->once()
        ->withArgs(fn ($s, $b, $u, $reason, $notes) => $s === $sale && $reason === 'duplicate' && $notes === 'Rang up twice')
        ->andReturn($revert);

    $api = Mockery::mock(PosOnlineApiService::class);
    $api->shouldReceive('formatSaleRevert')->once()->andReturn(['revert_number' => 'REV-0003']);
    $api->shouldReceive('formatSale')->once()->andReturn(['id' => 7, 'status' => 'void']);

    $response = revertController($reverts, $api)->store(revertRequest(['reason' => 'duplicate', 'notes' => 'Rang up twice']), $sale);

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getData(true)['data']['revert']['revert_number'])->toBe('REV-0003')
        ->and($response->getData(true)['data']['sale']['status'])->toBe('void');
});

test('a revert needs a reason', function () {
    $reverts = Mockery::mock(SaleRevertService::class);
    $reverts->shouldNotReceive('revert');

    $sale = revertSale()->forceFill(['id' => 7, 'business_id' => 12]);
    revertController($reverts, Mockery::mock(PosOnlineApiService::class))->store(revertRequest([]), $sale);
})->throws(ValidationException::class);

test('service errors come back as 422', function () {
    $reverts = Mockery::mock(SaleRevertService::class);
    $reverts->shouldReceive('revert')->andThrow(ValidationException::withMessages(['sale' => 'This bill has already been reverted or voided.']));

    $sale = revertSale()->forceFill(['id' => 7, 'business_id' => 12]);
    $response = revertController($reverts, Mockery::mock(PosOnlineApiService::class))->store(revertRequest(['reason' => 'duplicate']), $sale);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['errors']['sale'][0])->toContain('already been reverted');
});

test('the service rejects unknown reasons and an empty "other"', function (string $reason, ?string $notes) {
    $service = (new ReflectionClass(SaleRevertService::class))->newInstanceWithoutConstructor();
    $sale    = (new Sale)->forceFill(['id' => 7, 'business_id' => 12]);
    $business = (new Business)->forceFill(['id' => 12]);

    $service->revert($sale, $business, new App\Models\User, $reason, $notes);
})->with([
    ['not_a_reason', null],
    ['other', null],
    ['other', '   '],
])->throws(ValidationException::class);

test('reason labels fall back to a readable form', function () {
    expect((new SaleRevert)->forceFill(['reason' => 'duplicate'])->reasonLabel())->toBe('Duplicate bill')
        ->and((new SaleRevert)->forceFill(['reason' => 'legacy_reason'])->reasonLabel())->toBe('Legacy reason');
});
