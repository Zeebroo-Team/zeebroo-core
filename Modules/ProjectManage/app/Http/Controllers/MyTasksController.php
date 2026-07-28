<?php

namespace Modules\ProjectManage\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\ProjectManage\Http\Controllers\Concerns\ResolvesProjectManageBusiness;
use Modules\ProjectManage\Services\TaskService;

class MyTasksController extends Controller
{
    use ResolvesProjectManageBusiness;

    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $filter = (string) $request->query('filter', 'open');
        $tasks  = $this->taskService->listForBusiness($business, $filter);

        $filterTabs = [
            'open'    => 'Open',
            'overdue' => 'Overdue',
            'done'    => 'Done',
            'all'     => 'All',
        ];

        return view('projectmanage::my-tasks.index', [
            'business'   => $business,
            'tasks'      => $tasks,
            'filter'     => $filter,
            'filterTabs' => $filterTabs,
        ]);
    }
}
