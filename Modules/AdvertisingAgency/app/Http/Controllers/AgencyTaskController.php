<?php

namespace Modules\AdvertisingAgency\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\AdvertisingAgency\Http\Controllers\Concerns\ResolvesAgencyBusiness;
use Modules\AdvertisingAgency\Models\AgencyTask;
use Modules\AdvertisingAgency\Models\Campaign;
use Modules\AdvertisingAgency\Services\AgencyTaskService;

class AgencyTaskController extends Controller
{
    use ResolvesAgencyBusiness;

    public function __construct(
        private readonly AgencyTaskService $taskService,
    ) {}

    public function store(Request $request, Campaign $campaign): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($campaign->business_id === $business->id, 403);

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority'    => ['nullable', 'string', Rule::in(AgencyTask::PRIORITIES)],
            'status'      => ['nullable', 'string', Rule::in(AgencyTask::STATUSES)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_at'      => ['nullable', 'date'],
        ]);

        $this->taskService->create($campaign, $validated);

        return redirect()->route('advertising-agency.campaigns.show', $campaign)
            ->with('status', 'Task added successfully.');
    }

    public function update(Request $request, Campaign $campaign, AgencyTask $task): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($campaign->business_id === $business->id, 403);
        abort_unless($task->campaign_id === $campaign->id, 403);

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority'    => ['nullable', 'string', Rule::in(AgencyTask::PRIORITIES)],
            'status'      => ['nullable', 'string', Rule::in(AgencyTask::STATUSES)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_at'      => ['nullable', 'date'],
        ]);

        $this->taskService->update($task, $validated);

        return redirect()->route('advertising-agency.campaigns.show', $campaign)
            ->with('status', 'Task updated successfully.');
    }

    public function destroy(Request $request, Campaign $campaign, AgencyTask $task): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($campaign->business_id === $business->id, 403);
        abort_unless($task->campaign_id === $campaign->id, 403);

        $this->taskService->delete($task);

        return redirect()->route('advertising-agency.campaigns.show', $campaign)
            ->with('status', 'Task deleted.');
    }
}
