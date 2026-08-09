<?php

namespace Modules\EventManagement\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\EventManagement\Models\TicketType;
use Modules\EventManagement\Models\Event;

class TicketTypeService
{
    public function list(Business $business, ?int $eventId = null): Collection
    {
        $query = TicketType::query()
            ->join('em_events', 'em_events.id', '=', 'em_ticket_types.event_id')
            ->where('em_events.business_id', $business->id)
            ->select('em_ticket_types.*', 'em_events.name as event_name')
            ->withCount('attendees as sold_count');

        if ($eventId) {
            $query->where('em_ticket_types.event_id', $eventId);
        }

        return $query->orderBy('em_ticket_types.created_at')->get();
    }

    public function create(Event $event, array $data): TicketType
    {
        return TicketType::create(array_merge($data, ['event_id' => $event->id]));
    }

    public function update(TicketType $type, array $data): TicketType
    {
        $type->update($data);

        return $type->fresh();
    }

    public function delete(TicketType $type): void
    {
        $type->delete();
    }
}
