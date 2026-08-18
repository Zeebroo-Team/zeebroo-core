<?php

namespace Modules\AdvertisingAgency\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AdvertisingAgency\Http\Controllers\Concerns\ResolvesAgencyBusiness;
use Modules\AdvertisingAgency\Models\Client;
use Modules\AdvertisingAgency\Services\ClientService;

class ClientController extends Controller
{
    use ResolvesAgencyBusiness;

    public function __construct(
        private readonly ClientService $clientService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');

        return view('advertising-agency::clients.index', [
            'business'   => $business,
            'clients'    => $this->clientService->listForBusiness($business, $search, $status),
            'hasClients' => $this->clientService->businessHasClients($business),
            'search'     => $search,
            'status'     => $status,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'company'  => ['nullable', 'string', 'max:150'],
            'email'    => ['nullable', 'email', 'max:150'],
            'phone'    => ['nullable', 'string', 'max:40'],
            'address'  => ['nullable', 'string', 'max:300'],
            'industry' => ['nullable', 'string', 'max:100'],
            'website'  => ['nullable', 'url', 'max:255'],
            'notes'    => ['nullable', 'string', 'max:2000'],
            'status'   => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $this->clientService->create($business, $validated);

        return redirect()->route('advertising-agency.clients.index')
            ->with('status', 'Client created successfully.');
    }

    public function show(Request $request, Client $client): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($client->business_id === $business->id, 403);

        $client->loadCount('campaigns');
        $client->load(['campaigns' => fn ($q) => $q->withCount(['creatives', 'tasks'])->orderByDesc('created_at')]);

        return view('advertising-agency::clients.show', [
            'business' => $business,
            'client'   => $client,
        ]);
    }

    public function edit(Request $request, Client $client): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($client->business_id === $business->id, 403);

        return view('advertising-agency::clients.edit', [
            'business' => $business,
            'client'   => $client,
        ]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($client->business_id === $business->id, 403);

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'company'  => ['nullable', 'string', 'max:150'],
            'email'    => ['nullable', 'email', 'max:150'],
            'phone'    => ['nullable', 'string', 'max:40'],
            'address'  => ['nullable', 'string', 'max:300'],
            'industry' => ['nullable', 'string', 'max:100'],
            'website'  => ['nullable', 'url', 'max:255'],
            'notes'    => ['nullable', 'string', 'max:2000'],
            'status'   => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $this->clientService->update($client, $validated);

        return redirect()->route('advertising-agency.clients.show', $client)
            ->with('status', 'Client updated successfully.');
    }

    public function destroy(Request $request, Client $client): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($client->business_id === $business->id, 403);

        $this->clientService->delete($client);

        return redirect()->route('advertising-agency.clients.index')
            ->with('status', 'Client deleted.');
    }
}
