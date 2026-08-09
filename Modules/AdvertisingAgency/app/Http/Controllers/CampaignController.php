<?php

namespace Modules\AdvertisingAgency\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\AdvertisingAgency\Http\Controllers\Concerns\ResolvesAgencyBusiness;
use Modules\AdvertisingAgency\Models\Campaign;
use Modules\AdvertisingAgency\Models\Client;
use Modules\AdvertisingAgency\Services\AdCreativeService;
use Modules\AdvertisingAgency\Services\AgencyTaskService;
use Modules\AdvertisingAgency\Services\CampaignService;
use Modules\AdvertisingAgency\Services\ClientService;

class CampaignController extends Controller
{
    use ResolvesAgencyBusiness;

    public function __construct(
        private readonly CampaignService $campaignService,
        private readonly ClientService $clientService,
        private readonly AdCreativeService $creativeService,
        private readonly AgencyTaskService $taskService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $search   = trim((string) $request->query('q', ''));
        $status   = (string) $request->query('status', 'all');
        $clientId = $request->query('client_id') ? (int) $request->query('client_id') : null;

        return view('advertising-agency::campaigns.index', [
            'business'     => $business,
            'campaigns'    => $this->campaignService->listForBusiness($business, $search, $status, $clientId),
            'hasCampaigns' => $this->campaignService->businessHasCampaigns($business),
            'clients'      => $this->clientService->listForBusiness($business),
            'summary'      => $this->campaignService->summary($business),
            'search'       => $search,
            'status'       => $status,
            'clientId'     => $clientId,
            'statuses'     => Campaign::STATUSES,
            'channels'     => Campaign::CHANNELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'client_id'       => ['required', 'integer', Rule::exists('aa_clients', 'id')->where('business_id', $business->id)],
            'name'            => ['required', 'string', 'max:150'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'channel'         => ['nullable', 'string', Rule::in(Campaign::CHANNELS)],
            'budget'          => ['nullable', 'numeric', 'min:0'],
            'start_date'      => ['nullable', 'date'],
            'end_date'        => ['nullable', 'date', 'after_or_equal:start_date'],
            'status'          => ['nullable', 'string', Rule::in(Campaign::STATUSES)],
            'goal'            => ['nullable', 'string', 'max:255'],
            'target_audience' => ['nullable', 'string', 'max:255'],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ]);

        $client = Client::whereKey($validated['client_id'])->where('business_id', $business->id)->firstOrFail();
        $campaign = $this->campaignService->create($business, $client, $validated);

        return redirect()->route('advertising-agency.campaigns.show', $campaign)
            ->with('status', 'Campaign created successfully.');
    }

    public function show(Request $request, Campaign $campaign): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($campaign->business_id === $business->id, 403);

        $campaign->load('client');
        $creatives = $this->creativeService->listForCampaign($campaign);
        $tasks     = $this->taskService->listForCampaign($campaign);

        return view('advertising-agency::campaigns.show', [
            'business'  => $business,
            'campaign'  => $campaign,
            'creatives' => $creatives,
            'tasks'     => $tasks,
            'statuses'  => Campaign::STATUSES,
            'channels'  => Campaign::CHANNELS,
        ]);
    }

    public function edit(Request $request, Campaign $campaign): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($campaign->business_id === $business->id, 403);
        $campaign->load('client');

        return view('advertising-agency::campaigns.edit', [
            'business'  => $business,
            'campaign'  => $campaign,
            'clients'   => $this->clientService->listForBusiness($business),
            'statuses'  => Campaign::STATUSES,
            'channels'  => Campaign::CHANNELS,
        ]);
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($campaign->business_id === $business->id, 403);

        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:150'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'channel'         => ['nullable', 'string', Rule::in(Campaign::CHANNELS)],
            'budget'          => ['nullable', 'numeric', 'min:0'],
            'start_date'      => ['nullable', 'date'],
            'end_date'        => ['nullable', 'date', 'after_or_equal:start_date'],
            'status'          => ['nullable', 'string', Rule::in(Campaign::STATUSES)],
            'goal'            => ['nullable', 'string', 'max:255'],
            'target_audience' => ['nullable', 'string', 'max:255'],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ]);

        $this->campaignService->update($campaign, $validated);

        return redirect()->route('advertising-agency.campaigns.show', $campaign)
            ->with('status', 'Campaign updated successfully.');
    }

    public function destroy(Request $request, Campaign $campaign): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($campaign->business_id === $business->id, 403);

        $this->campaignService->delete($campaign);

        return redirect()->route('advertising-agency.campaigns.index')
            ->with('status', 'Campaign deleted.');
    }
}
