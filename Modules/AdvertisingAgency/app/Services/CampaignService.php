<?php

namespace Modules\AdvertisingAgency\Services;

use Illuminate\Support\Collection;
use Modules\AdvertisingAgency\Models\Campaign;
use Modules\AdvertisingAgency\Models\Client;
use Modules\Business\Models\Business;

class CampaignService
{
    public function listForBusiness(Business $business, ?string $search = null, ?string $status = null, ?int $clientId = null): Collection
    {
        $query = Campaign::where('business_id', $business->id)
            ->with('client')
            ->withCount(['creatives', 'tasks'])
            ->orderByDesc('created_at');

        if (filled($search)) {
            $like = '%' . addcslashes(trim($search), '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('description', 'like', $like);
            });
        }

        if (filled($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        return $query->get();
    }

    public function listForClient(Client $client, ?string $status = null): Collection
    {
        $query = Campaign::where('client_id', $client->id)
            ->withCount(['creatives', 'tasks'])
            ->orderByDesc('created_at');

        if (filled($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function businessHasCampaigns(Business $business): bool
    {
        return Campaign::where('business_id', $business->id)->exists();
    }

    public function create(Business $business, Client $client, array $data): Campaign
    {
        return Campaign::create([
            'business_id'     => $business->id,
            'client_id'       => $client->id,
            'name'            => trim($data['name']),
            'description'     => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'channel'         => filled($data['channel'] ?? null) ? $data['channel'] : null,
            'budget'          => max(0, (float) ($data['budget'] ?? 0)),
            'spent'           => 0,
            'start_date'      => filled($data['start_date'] ?? null) ? $data['start_date'] : null,
            'end_date'        => filled($data['end_date'] ?? null) ? $data['end_date'] : null,
            'status'          => $data['status'] ?? Campaign::STATUS_DRAFT,
            'goal'            => filled($data['goal'] ?? null) ? trim($data['goal']) : null,
            'target_audience' => filled($data['target_audience'] ?? null) ? trim($data['target_audience']) : null,
            'notes'           => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
        ]);
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        $campaign->update([
            'name'            => trim($data['name']),
            'description'     => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'channel'         => filled($data['channel'] ?? null) ? $data['channel'] : null,
            'budget'          => max(0, (float) ($data['budget'] ?? 0)),
            'start_date'      => filled($data['start_date'] ?? null) ? $data['start_date'] : null,
            'end_date'        => filled($data['end_date'] ?? null) ? $data['end_date'] : null,
            'status'          => $data['status'] ?? $campaign->status,
            'goal'            => filled($data['goal'] ?? null) ? trim($data['goal']) : null,
            'target_audience' => filled($data['target_audience'] ?? null) ? trim($data['target_audience']) : null,
            'notes'           => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
        ]);

        return $campaign->fresh();
    }

    public function recordSpend(Campaign $campaign, float $amount): void
    {
        $campaign->increment('spent', $amount);
    }

    public function delete(Campaign $campaign): void
    {
        $campaign->delete();
    }

    public function summary(Business $business): array
    {
        $campaigns = Campaign::where('business_id', $business->id)->get();

        return [
            'total'     => $campaigns->count(),
            'active'    => $campaigns->where('status', Campaign::STATUS_ACTIVE)->count(),
            'draft'     => $campaigns->where('status', Campaign::STATUS_DRAFT)->count(),
            'completed' => $campaigns->where('status', Campaign::STATUS_COMPLETED)->count(),
            'budget'    => round($campaigns->sum('budget'), 2),
            'spent'     => round($campaigns->sum('spent'), 2),
        ];
    }
}
