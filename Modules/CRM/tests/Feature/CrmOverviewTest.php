<?php

require_once __DIR__ . '/Support/CrmWorkflowFixture.php';

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Task;
use Modules\CRM\Services\TaskService;
use Modules\Pos\Http\Controllers\Api\PosCrmApiController;

class CrmwfCrmApi extends PosCrmApiController
{
    protected function businessOrAbort(Request $request): Business
    {
        return Business::find(7);
    }
}

beforeEach(function () {
    crmwfSetUp();
});

test('the overview returns the tile counts of the business only', function () {
    DB::table('pos_customers')->insert([
        ['business_id' => 7, 'name' => 'A'],
        ['business_id' => 7, 'name' => 'B'],
        ['business_id' => 8, 'name' => 'Other business'],
    ]);
    $tasks = app(TaskService::class);
    $tasks->create($this->business, ['title' => 'Call back'], $this->lead, 3);
    $tasks->create($this->business, ['title' => 'Send brochure'], null, 3);
    $done = $tasks->create($this->business, ['title' => 'Done already'], null, 3);
    $done->update(['status' => Task::STATUS_COMPLETED]);

    $data = crmwf(CrmwfCrmApi::class, 'overview', 1)->getData(true)['data'];

    expect($data)->toBe(['contacts' => 2, 'open_tasks' => 2])
        ->and($data['open_tasks'])->toBe(count(crmwf(CrmwfCrmApi::class, 'tasksList', 1)->getData(true)['data']));
});

test('a user without CRM access in the business gets no counts', function () {
    expect(crmwf(CrmwfCrmApi::class, 'overview', 9)->getData(true)['data'])->toBe(['contacts' => null, 'open_tasks' => null]);
});
