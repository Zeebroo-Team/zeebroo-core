<?php

namespace Modules\ProjectManage\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\ProjectManage\Http\Controllers\Concerns\ResolvesProjectManageBusiness;
use Modules\ProjectManage\Models\Project;
use Modules\ProjectManage\Services\ProjectService;

class OverviewController extends Controller
{
    use ResolvesProjectManageBusiness;

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

        return view('projectmanage::overview', [
            'business'       => $business,
            'hasProjects'    => $projects->isNotEmpty(),
            'totalCount'     => $projects->count(),
            'activeCount'    => $projects->where('status', Project::STATUS_ACTIVE)->count(),
            'onHoldCount'    => $projects->where('status', Project::STATUS_ON_HOLD)->count(),
            'completedCount' => $projects->where('status', Project::STATUS_COMPLETED)->count(),
            'recentProjects' => $projects->sortByDesc('id')->take(6)->values(),
        ]);
    }
}
