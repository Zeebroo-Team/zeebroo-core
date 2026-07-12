<?php

namespace Modules\Mail\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Mail\Models\MailFilter;

class MailFilterService
{
    public function listForBusiness(Business $business): Collection
    {
        return MailFilter::where('business_id', $business->id)->orderBy('sort_order')->get();
    }

    public function create(Business $business, array $data): MailFilter
    {
        $nextOrder = (int) MailFilter::where('business_id', $business->id)->max('sort_order') + 1;

        return MailFilter::create([
            'business_id' => $business->id,
            'field'       => $data['field'],
            'value'       => $data['value'],
            'action'      => $data['action'],
            'is_active'   => (bool) ($data['is_active'] ?? true),
            'sort_order'  => $nextOrder,
        ]);
    }

    public function update(MailFilter $filter, array $data): MailFilter
    {
        $filter->update([
            'field'     => $data['field'],
            'value'     => $data['value'],
            'action'    => $data['action'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return $filter->fresh();
    }

    public function delete(MailFilter $filter): void
    {
        $filter->delete();
    }

    public function reorder(Business $business, array $ids): void
    {
        foreach ($ids as $order => $id) {
            MailFilter::where('id', $id)->where('business_id', $business->id)->update(['sort_order' => $order + 1]);
        }
    }

    public function filterForBusiness(Business $business, MailFilter $filter): ?MailFilter
    {
        return $filter->business_id === $business->id ? $filter : null;
    }

    /**
     * Apply this business's active filters, in order, to an about-to-be-stored
     * inbound message. Returns the action to take: null (store normally),
     * 'mark_read' (store but pre-marked read), or 'delete' (don't store it at all).
     * The first matching filter wins.
     */
    public function resolveAction(Business $business, ?string $fromAddress, ?string $fromName, ?string $subject): ?string
    {
        foreach ($this->listForBusiness($business) as $filter) {
            if (!$filter->is_active) {
                continue;
            }

            if ($filter->matches($fromAddress, $fromName, $subject)) {
                return $filter->action;
            }
        }

        return null;
    }
}
