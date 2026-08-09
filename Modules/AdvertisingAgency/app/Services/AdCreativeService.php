<?php

namespace Modules\AdvertisingAgency\Services;

use Illuminate\Support\Collection;
use Modules\AdvertisingAgency\Models\AdCreative;
use Modules\AdvertisingAgency\Models\Campaign;

class AdCreativeService
{
    public function listForCampaign(Campaign $campaign, ?string $status = null): Collection
    {
        $query = AdCreative::where('campaign_id', $campaign->id)
            ->orderByDesc('created_at');

        if (filled($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function create(Campaign $campaign, array $data): AdCreative
    {
        return AdCreative::create([
            'campaign_id'    => $campaign->id,
            'title'          => trim($data['title']),
            'format'         => $data['format'] ?? 'image',
            'headline'       => filled($data['headline'] ?? null) ? trim($data['headline']) : null,
            'body_copy'      => filled($data['body_copy'] ?? null) ? trim($data['body_copy']) : null,
            'call_to_action' => filled($data['call_to_action'] ?? null) ? trim($data['call_to_action']) : null,
            'file_url'       => filled($data['file_url'] ?? null) ? trim($data['file_url']) : null,
            'file_name'      => filled($data['file_name'] ?? null) ? trim($data['file_name']) : null,
            'dimensions'     => filled($data['dimensions'] ?? null) ? trim($data['dimensions']) : null,
            'status'         => $data['status'] ?? AdCreative::STATUS_DRAFT,
            'notes'          => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
        ]);
    }

    public function update(AdCreative $creative, array $data): AdCreative
    {
        $wasApproved = $creative->status !== AdCreative::STATUS_APPROVED
            && ($data['status'] ?? '') === AdCreative::STATUS_APPROVED;

        $wasPublished = $creative->status !== AdCreative::STATUS_PUBLISHED
            && ($data['status'] ?? '') === AdCreative::STATUS_PUBLISHED;

        $creative->update([
            'title'          => trim($data['title']),
            'format'         => $data['format'] ?? $creative->format,
            'headline'       => filled($data['headline'] ?? null) ? trim($data['headline']) : null,
            'body_copy'      => filled($data['body_copy'] ?? null) ? trim($data['body_copy']) : null,
            'call_to_action' => filled($data['call_to_action'] ?? null) ? trim($data['call_to_action']) : null,
            'file_url'       => filled($data['file_url'] ?? null) ? trim($data['file_url']) : null,
            'file_name'      => filled($data['file_name'] ?? null) ? trim($data['file_name']) : null,
            'dimensions'     => filled($data['dimensions'] ?? null) ? trim($data['dimensions']) : null,
            'status'         => $data['status'] ?? $creative->status,
            'notes'          => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            'approved_at'    => $wasApproved ? now() : $creative->approved_at,
            'published_at'   => $wasPublished ? now() : $creative->published_at,
        ]);

        return $creative->fresh();
    }

    public function delete(AdCreative $creative): void
    {
        $creative->delete();
    }
}
