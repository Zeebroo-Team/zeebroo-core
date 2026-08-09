<?php

namespace Modules\EventManagement\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\EventManagement\Models\Event;

class EventService
{
    public function list(Business $business, string $filter = 'upcoming', string $q = ''): Collection
    {
        $query = Event::where('business_id', $business->id)
            ->withCount('attendees');

        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                   ->orWhere('venue', 'like', "%{$q}%");
            });
        }

        if ($filter === 'upcoming') {
            $query->whereNotNull('start_at')->where('start_at', '>=', now());
        } elseif ($filter === 'past') {
            $query->where(function ($qb) {
                $qb->whereNotNull('end_at')->where('end_at', '<', now())
                   ->orWhere(function ($qb2) {
                       $qb2->whereNull('end_at')->whereNotNull('start_at')->where('start_at', '<', now());
                   });
            });
        }

        return $query->orderBy('start_at')->get();
    }

    public function create(Business $business, array $data): Event
    {
        return Event::create(array_merge($data, ['business_id' => $business->id]));
    }

    public function update(Event $event, array $data): Event
    {
        $event->update($data);

        return $event->fresh();
    }

    public function delete(Event $event): void
    {
        $event->delete();
    }
}
