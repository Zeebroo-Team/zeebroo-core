<?php

namespace Modules\AdvertisingAgency\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\AdvertisingAgency\Http\Controllers\Concerns\ResolvesAgencyBusiness;
use Modules\AdvertisingAgency\Models\AdCreative;
use Modules\AdvertisingAgency\Models\Campaign;
use Modules\AdvertisingAgency\Services\AdCreativeService;

class AdCreativeController extends Controller
{
    use ResolvesAgencyBusiness;

    public function __construct(
        private readonly AdCreativeService $creativeService,
    ) {}

    public function store(Request $request, Campaign $campaign): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($campaign->business_id === $business->id, 403);

        $validated = $request->validate([
            'title'          => ['required', 'string', 'max:150'],
            'format'         => ['nullable', 'string', Rule::in(AdCreative::FORMATS)],
            'headline'       => ['nullable', 'string', 'max:255'],
            'body_copy'      => ['nullable', 'string', 'max:5000'],
            'call_to_action' => ['nullable', 'string', 'max:100'],
            'file_url'       => ['nullable', 'url', 'max:500'],
            'file_name'      => ['nullable', 'string', 'max:255'],
            'dimensions'     => ['nullable', 'string', 'max:40'],
            'status'         => ['nullable', 'string', Rule::in(AdCreative::STATUSES)],
            'notes'          => ['nullable', 'string', 'max:2000'],
        ]);

        $this->creativeService->create($campaign, $validated);

        return redirect()->route('advertising-agency.campaigns.show', $campaign)
            ->with('status', 'Creative added successfully.');
    }

    public function update(Request $request, Campaign $campaign, AdCreative $creative): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($campaign->business_id === $business->id, 403);
        abort_unless($creative->campaign_id === $campaign->id, 403);

        $validated = $request->validate([
            'title'          => ['required', 'string', 'max:150'],
            'format'         => ['nullable', 'string', Rule::in(AdCreative::FORMATS)],
            'headline'       => ['nullable', 'string', 'max:255'],
            'body_copy'      => ['nullable', 'string', 'max:5000'],
            'call_to_action' => ['nullable', 'string', 'max:100'],
            'file_url'       => ['nullable', 'url', 'max:500'],
            'file_name'      => ['nullable', 'string', 'max:255'],
            'dimensions'     => ['nullable', 'string', 'max:40'],
            'status'         => ['nullable', 'string', Rule::in(AdCreative::STATUSES)],
            'notes'          => ['nullable', 'string', 'max:2000'],
        ]);

        $this->creativeService->update($creative, $validated);

        return redirect()->route('advertising-agency.campaigns.show', $campaign)
            ->with('status', 'Creative updated successfully.');
    }

    public function destroy(Request $request, Campaign $campaign, AdCreative $creative): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($campaign->business_id === $business->id, 403);
        abort_unless($creative->campaign_id === $campaign->id, 403);

        $this->creativeService->delete($creative);

        return redirect()->route('advertising-agency.campaigns.show', $campaign)
            ->with('status', 'Creative deleted.');
    }
}
