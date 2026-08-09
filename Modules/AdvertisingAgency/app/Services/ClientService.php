<?php

namespace Modules\AdvertisingAgency\Services;

use Illuminate\Support\Collection;
use Modules\AdvertisingAgency\Models\Client;
use Modules\Business\Models\Business;

class ClientService
{
    public function listForBusiness(Business $business, ?string $search = null, ?string $status = null): Collection
    {
        $query = Client::where('business_id', $business->id)
            ->withCount('campaigns')
            ->orderBy('name');

        if (filled($search)) {
            $like = '%' . addcslashes(trim($search), '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('company', 'like', $like)
                  ->orWhere('email', 'like', $like);
            });
        }

        if (filled($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function businessHasClients(Business $business): bool
    {
        return Client::where('business_id', $business->id)->exists();
    }

    public function create(Business $business, array $data): Client
    {
        return Client::create([
            'business_id' => $business->id,
            'name'        => trim($data['name']),
            'company'     => filled($data['company'] ?? null) ? trim($data['company']) : null,
            'email'       => filled($data['email'] ?? null) ? trim($data['email']) : null,
            'phone'       => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'address'     => filled($data['address'] ?? null) ? trim($data['address']) : null,
            'industry'    => filled($data['industry'] ?? null) ? trim($data['industry']) : null,
            'website'     => filled($data['website'] ?? null) ? trim($data['website']) : null,
            'notes'       => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            'status'      => $data['status'] ?? 'active',
        ]);
    }

    public function update(Client $client, array $data): Client
    {
        $client->update([
            'name'     => trim($data['name']),
            'company'  => filled($data['company'] ?? null) ? trim($data['company']) : null,
            'email'    => filled($data['email'] ?? null) ? trim($data['email']) : null,
            'phone'    => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'address'  => filled($data['address'] ?? null) ? trim($data['address']) : null,
            'industry' => filled($data['industry'] ?? null) ? trim($data['industry']) : null,
            'website'  => filled($data['website'] ?? null) ? trim($data['website']) : null,
            'notes'    => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            'status'   => $data['status'] ?? $client->status,
        ]);

        return $client->fresh();
    }

    public function delete(Client $client): void
    {
        $client->delete();
    }
}
