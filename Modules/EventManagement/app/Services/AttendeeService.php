<?php

namespace Modules\EventManagement\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Business;
use Modules\EventManagement\Models\Attendee;
use Modules\EventManagement\Models\Event;

class AttendeeService
{
    public function list(Business $business, ?int $eventId = null, string $q = ''): Collection
    {
        $query = Attendee::query()
            ->join('em_events', 'em_events.id', '=', 'em_attendees.event_id')
            ->leftJoin('em_ticket_types', 'em_ticket_types.id', '=', 'em_attendees.ticket_type_id')
            ->where('em_events.business_id', $business->id)
            ->select(
                'em_attendees.*',
                'em_events.name as event_name',
                'em_ticket_types.name as ticket_name'
            );

        if ($eventId) {
            $query->where('em_attendees.event_id', $eventId);
        }

        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('em_attendees.name', 'like', "%{$q}%")
                   ->orWhere('em_attendees.email', 'like', "%{$q}%")
                   ->orWhere('em_attendees.phone', 'like', "%{$q}%");
            });
        }

        return $query->orderBy('em_attendees.created_at', 'desc')->get();
    }

    public function create(Event $event, array $data): Attendee
    {
        return Attendee::create(array_merge($data, ['event_id' => $event->id]));
    }

    public function checkIn(Attendee $attendee): Attendee
    {
        $attendee->update(['checked_in_at' => now()]);

        return $attendee->fresh();
    }

    public function delete(Attendee $attendee): void
    {
        $attendee->delete();
    }
}
