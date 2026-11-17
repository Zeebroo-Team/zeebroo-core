<?php

use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\PosProductSyncApiController;
use Modules\Pos\Services\PosProductSyncService;

// Exercise request parsing and branch scoping without the application's database.
function productSyncFixture(array $scope = [7, true, false]): array
{
    $service = Mockery::mock(PosProductSyncService::class);
    $controller = new class($service, $scope) extends PosProductSyncApiController {
        public function __construct(PosProductSyncService $sync, private readonly array $scope)
        {
            parent::__construct($sync);
        }

        protected function businessOrAbort(Request $request): Business
        {
            return (new Business)->forceFill(['id' => 12]);
        }

        protected function branchScope(Request $request, Business $business): array
        {
            return $this->scope;
        }
    };

    return [$controller, $service];
}

test('sync index returns the version and fingerprints for the branch scope', function () {
    [$controller, $service] = productSyncFixture();
    $service->shouldReceive('index')->once()
        ->withArgs(fn ($business, $branchId, $productSeparate, $stockSeparate) => $business->id === 12
            && $branchId === 7 && $productSeparate === true && $stockSeparate === false)
        ->andReturn(['version' => 'v1', 'items' => [['id' => 5, 'fp' => 'abc']]]);

    $response = $controller->index(Request::create('/sync-index'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['data'])->toBe(['version' => 'v1', 'items' => [['id' => 5, 'fp' => 'abc']]]);
});

test('by-ids parses, de-duplicates and ignores non-numeric ids', function () {
    [$controller, $service] = productSyncFixture();
    $service->shouldReceive('cardsByIds')->once()
        ->withArgs(fn ($business, $ids) => $ids === [3, 9, 4])
        ->andReturn([['id' => 3], ['id' => 4], ['id' => 9]]);

    $response = $controller->byIds(Request::create('/by-ids', 'GET', ['ids' => '3, 9,abc,3,,4']));

    expect($response->getStatusCode())->toBe(200)
        ->and(array_column($response->getData(true)['data'], 'id'))->toBe([3, 4, 9]);
});

test('by-ids rejects more than 100 ids', function () {
    [$controller, $service] = productSyncFixture();
    $service->shouldNotReceive('cardsByIds');

    $response = $controller->byIds(Request::create('/by-ids', 'GET', ['ids' => implode(',', range(1, 101))]));

    expect($response->getStatusCode())->toBe(422);
});
