<?php

namespace Modules\ProjectManage\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\ProjectManage\Models\Project;
use Modules\ProjectManage\Models\Task;

trait ResolvesProjectManageBusiness
{
    protected function requireBusiness(Request $request): Business|RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (!$business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select or create a business first.']);
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);

        return $business;
    }

    protected function requireProject(Request $request, Project $project): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless((int) $project->business_id === (int) $business->id, 404);

        return $business;
    }

    protected function requireTask(Request $request, Task $task): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $task->loadMissing('project');
        abort_unless((int) $task->project->business_id === (int) $business->id, 404);

        return $business;
    }
}
