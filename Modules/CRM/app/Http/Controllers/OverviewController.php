<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\CRM\Http\Controllers\Concerns\ResolvesCrmBusiness;
use Modules\CRM\Models\Project;
use Modules\Pos\Models\Customer;
use Modules\CRM\Services\ProjectService;
use Modules\CRM\Services\TaskService;

class OverviewController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(
        private readonly ProjectService $projectService,
        private readonly TaskService $taskService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $access   = $this->crmAccess($request, $business);
        $projects = $this->leadPolicy()->visibleProjects($access, $this->projectService->listForBusiness($business));

        return view('crm::overview', [
            'business'       => $business,
            'hasProjects'    => $projects->isNotEmpty(),
            'relationsCount' => $projects->count(),
            'contactsCount'  => $access->can('contacts_view') ? Customer::query()->where('business_id', $business->id)->count() : 0,
            'openTasksCount' => $access->can('tasks_view') ? $this->taskService->listForBusiness($business, 'open', $access)->count() : 0,
            'recentProjects' => $projects->sortByDesc('id')->take(3)->values(),
        ]);
    }
}
