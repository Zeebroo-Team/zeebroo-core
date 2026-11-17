<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\Bill;
use Modules\Account\Services\BillService;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\PosExpenseBillApiController;
use Modules\Transaction\Services\BillManualPaymentSettlementService;

// Exercise validation and update dispatch without connecting to the application's database.
function billUpdateFixture(array $fields = []): array
{
    $service = Mockery::mock(BillService::class);
    $controller = new class($service, Mockery::mock(BillManualPaymentSettlementService::class)) extends PosExpenseBillApiController {
        protected function businessOrAbort(Request $request): Business
        {
            return (new Business)->forceFill(['id' => 12]);
        }

        protected function abortUnlessPerm(Request $request, Business $business, string $permKey): void
        {
            expect($permKey)->toBe('fin_bills');
        }
    };
    $request = Request::create('/bills/42', 'PUT', array_merge([
        'name' => 'Updated electricity bill', 'bill_category' => 'electricity',
        'payment_mode' => 'recurring', 'recurring_type' => 'per_month',
        'agreement_valid_until_year' => 2027, 'recurring_cost' => 500,
        'amount_varies_by_usage' => false, 'allow_split_payment' => true,
        'assignment_type' => 'none', 'description' => null, 'notes' => null,
    ], $fields));
    $request->setUserResolver(fn () => (new User)->forceFill(['id' => 3]));
    $bill = Mockery::mock(Bill::class)->makePartial();
    $bill->forceFill(['id' => 42, 'business_id' => 12, 'user_id' => 3]);
    return [$controller, $service, $request, $bill];
}

test('bill update uses the existing record and clears optional fields', function () {
    [$controller, $service, $request, $bill] = billUpdateFixture();
    Schema::shouldReceive('hasTable')->with('hr_departments')->once()->andReturn(false);
    $service->shouldReceive('billForUser')->once()->andReturn($bill);
    $service->shouldNotReceive('create');
    $service->shouldReceive('updateForUser')->once()->withArgs(function ($user, $existing, $data) use ($bill) {
        expect($existing)->toBe($bill);
        expect($data['name'])->toBe('Updated electricity bill')
            ->and($data['description'])->toBeNull()
            ->and($data['notes'])->toBeNull()
            ->and($data['deduct_account_id'])->toBeNull()
            ->and($data['branch_id'])->toBeNull();
        $bill->forceFill($data);
        return true;
    })->andReturn(true);
    $bill->shouldReceive('refresh')->once()->andReturnSelf();
    $response = $controller->update($request, $bill);
    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['data']['id'])->toBe(42);
});

test('bill update rejects bills from another business', function () {
    [$controller, $service, $request, $bill] = billUpdateFixture();
    $bill->business_id = 99;
    $service->shouldNotReceive('updateForUser');
    expect($controller->update($request, $bill)->getStatusCode())->toBe(404);
});

test('bill update rejects bills not owned by the user', function () {
    [$controller, $service, $request, $bill] = billUpdateFixture();
    $service->shouldReceive('billForUser')->once()->andReturnNull();
    $service->shouldNotReceive('updateForUser');
    expect($controller->update($request, $bill)->getStatusCode())->toBe(404);
});

test('bill update validates required fields before writing', function () {
    [$controller, $service, $request, $bill] = billUpdateFixture(['name' => '']);
    Schema::shouldReceive('hasTable')->with('hr_departments')->once()->andReturn(false);
    $service->shouldReceive('billForUser')->once()->andReturn($bill);
    $service->shouldNotReceive('updateForUser');
    expect(fn () => $controller->update($request, $bill))->toThrow(ValidationException::class);
});
