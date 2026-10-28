<?php

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\PosSaleChequeApiController;
use Modules\Pos\Models\SaleCheque;
use Modules\Pos\Services\PosOnlineApiService;
use Modules\Pos\Services\SaleChequeService;
use Modules\Pos\Services\SaleService;

// Exercise the cheque endpoints without the application's database.
function chequeController(SaleChequeService $cheques, PosOnlineApiService $api): PosSaleChequeApiController
{
    return new class($cheques, $api) extends PosSaleChequeApiController {
        protected function businessOrAbort(Request $request): Business
        {
            return (new Business)->forceFill(['id' => 12, 'user_id' => 1]);
        }

        protected function abortUnlessPerm(Request $request, Business $business, string $permKey): void
        {
        }
    };
}

function chequeRequest(string $uri, array $body = []): Request
{
    $request = Request::create($uri, 'POST', $body);
    $request->setUserResolver(fn () => new App\Models\User);

    return $request;
}

test('claiming passes the chosen deposit account to the service', function () {
    $cheque = (new SaleCheque)->forceFill(['id' => 4, 'business_id' => 12, 'cheque_number' => '000123']);

    $cheques = Mockery::mock(SaleChequeService::class);
    $cheques->shouldReceive('claim')->once()
        ->withArgs(fn ($c, $b, $u, $accountId, $notes) => $c === $cheque && $accountId === 9 && $notes === 'Banked at BOC')
        ->andReturn($cheque);

    $api = Mockery::mock(PosOnlineApiService::class);
    $api->shouldReceive('formatSaleCheque')->once()->andReturn(['id' => 4, 'status' => 'cleared']);

    $response = chequeController($cheques, $api)->claim(
        chequeRequest('/sale-cheques/4/claim', ['deposit_account_id' => 9, 'notes' => 'Banked at BOC']),
        $cheque,
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['data']['status'])->toBe('cleared');
});

test('claim errors come back as 422 with the first message', function () {
    $cheque = (new SaleCheque)->forceFill(['id' => 4, 'business_id' => 12]);

    $cheques = Mockery::mock(SaleChequeService::class);
    $cheques->shouldReceive('claim')->andThrow(ValidationException::withMessages(['cheque' => 'This cheque has already been claimed.']));

    $response = chequeController($cheques, Mockery::mock(PosOnlineApiService::class))
        ->claim(chequeRequest('/sale-cheques/4/claim'), $cheque);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['message'])->toBe('This cheque has already been claimed.');
});

test('a cheque from another business is not found', function () {
    $cheque = (new SaleCheque)->forceFill(['id' => 4, 'business_id' => 99]);

    chequeController(Mockery::mock(SaleChequeService::class), Mockery::mock(PosOnlineApiService::class))
        ->claim(chequeRequest('/sale-cheques/4/claim'), $cheque);
})->throws(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

test('bouncing needs a reason', function () {
    $cheques = Mockery::mock(SaleChequeService::class);
    $cheques->shouldNotReceive('bounce');

    $cheque = (new SaleCheque)->forceFill(['id' => 4, 'business_id' => 12]);
    chequeController($cheques, Mockery::mock(PosOnlineApiService::class))->bounce(chequeRequest('/sale-cheques/4/bounce'), $cheque);
})->throws(ValidationException::class);

test('only pending or bounced cheques can be claimed', function (string $status, bool $claimable) {
    expect((new SaleCheque)->forceFill(['status' => $status])->isClaimable())->toBe($claimable);
})->with([
    ['pending', true],
    ['bounced', true],
    ['cleared', false],
    ['cancelled', false],
]);

test('a pending cheque is due once its date arrives', function () {
    Carbon::setTestNow('2026-10-28 10:00:00');

    $due    = (new SaleCheque)->forceFill(['status' => 'pending', 'cheque_date' => '2026-10-28']);
    $future = (new SaleCheque)->forceFill(['status' => 'pending', 'cheque_date' => '2026-11-15']);

    expect($due->isDue())->toBeTrue()
        ->and($future->isDue())->toBeFalse()
        ->and($due->statusLabel())->toBe('Pending')
        ->and((new SaleCheque)->forceFill(['status' => 'cleared'])->statusLabel())->toBe('Claimed');

    Carbon::setTestNow();
});

test('a cheque sale needs a customer, a cheque number and a date', function (?int $customerId, array $cheque) {
    $service = (new ReflectionClass(SaleService::class))->newInstanceWithoutConstructor();
    $method  = new ReflectionMethod(SaleService::class, 'normalizeCheque');

    $method->invoke($service, (new Business)->forceFill(['id' => 12]), $customerId, $cheque);
})->with([
    'no customer' => [null, ['cheque_number' => '000123', 'cheque_date' => '2026-11-01']],
])->throws(ValidationException::class);
