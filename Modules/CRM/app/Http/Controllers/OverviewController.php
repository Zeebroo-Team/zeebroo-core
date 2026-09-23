<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\CRM\Http\Controllers\Concerns\ResolvesCrmBusiness;
use Modules\CRM\Models\Project;
use Modules\CRM\Models\Task;
use Modules\Pos\Models\Customer;
use Modules\CRM\Services\ProjectService;

class OverviewController extends Controller
{
    use ResolvesCrmBusiness;

    public function __construct(
        private readonly ProjectService $projectService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $projects = $this->projectService->listForBusiness($business);

        return view('crm::overview', [
            'business'       => $business,
            'hasProjects'    => $projects->isNotEmpty(),
            'relationsCount' => $projects->count(),
            'contactsCount'  => Customer::query()->where('business_id', $business->id)->count(),
            'openTasksCount' => Task::query()->where('business_id', $business->id)->where('status', Task::STATUS_PENDING)->count(),
            'recentProjects' => $projects->sortByDesc('id')->take(3)->values(),
        ]);
    }
}
