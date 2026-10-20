<?php

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\PosCustomerApiController;
use Modules\Pos\Models\Customer;
use Modules\Pos\Services\CustomerCreditService;
use Modules\Pos\Services\PosSettingsService;
use Modules\Pos\Services\SalePaymentSettlementService;

// Exercise credit-limit maths and the payment endpoint without the application's database.
function creditService(): CustomerCreditService
{
    return new CustomerCreditService(
        Mockery::mock(SalePaymentSettlementService::class),
        Mockery::mock(PosSettingsService::class),
    );
}

function creditController(CustomerCreditService $credit): PosCustomerApiController
{
    return new class($credit) extends PosCustomerApiController {
        protected function businessOrAbort(Request $request): Business
        {
            return (new Business)->forceFill(['id' => 12, 'user_id' => 1]);
        }

        protected function abortUnlessPerm(Request $request, Business $business, string $permKey): void
        {
        }
    };
}

function creditCustomer(?float $limit): Customer
{
    return (new Customer)->forceFill(['id' => 40, 'business_id' => 12, 'name' => 'Anura', 'credit_limit' => $limit]);
}

test('available credit is the limit minus what is still owed', function () {
    expect(creditService()->summary(creditCustomer(1000), 1000.0))
        ->toBe(['credit_limit' => 1000.0, 'credit_outstanding' => 1000.0, 'credit_available' => 0.0]);

    // settling 100 of a 1000 due frees 100 of the limit again
    expect(creditService()->summary(creditCustomer(1000), 900.0)['credit_available'])->toBe(100.0);
});

test('a customer without a limit has no available figure', function () {
    expect(creditService()->summary(creditCustomer(null), 250.0))
        ->toBe(['credit_limit' => null, 'credit_outstanding' => 250.0, 'credit_available' => null]);
});

test('a credit payment is passed to the service and returns the new balance', function () {
    $credit = Mockery::mock(CustomerCreditService::class);
    $credit->shouldReceive('receivePayment')->once()
        ->withArgs(fn ($business, $customer, $user, $amount, $method) => $amount === 100.0 && $method === 'cash')
        ->andReturn(['amount' => 100.0, 'applied' => [['sale_id' => 1, 'sale_number' => 'POS-0001', 'amount' => 100.0, 'remaining_due' => 900.0]]]);
    $credit->shouldReceive('summary')->once()
        ->andReturn(['credit_limit' => 1000.0, 'credit_outstanding' => 900.0, 'credit_available' => 100.0]);

    $request = Request::create('/credit-payments', 'POST', ['amount' => 100]);
    $request->setUserResolver(fn () => new App\Models\User);

    $response = creditController($credit)->creditPayment($request, creditCustomer(1000));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['data']['credit_available'])->toEqual(100);
});

test('a credit payment needs a positive amount', function (array $body) {
    $credit = Mockery::mock(CustomerCreditService::class);
    $credit->shouldNotReceive('receivePayment');

    $request = Request::create('/credit-payments', 'POST', $body);
    $request->setUserResolver(fn () => new App\Models\User);

    creditController($credit)->creditPayment($request, creditCustomer(1000));
})->with([
    [[]],
    [['amount' => 0]],
    [['amount' => 50, 'payment_method' => 'credit']],
])->throws(ValidationException::class);
