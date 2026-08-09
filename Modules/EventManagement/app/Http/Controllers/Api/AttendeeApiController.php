<?php

namespace Modules\EventManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\EventManagement\Http\Controllers\Api\Concerns\ResolvesEvtBusinessForApi;
use Modules\EventManagement\Models\Attendee;
use Modules\EventManagement\Models\Event;
use Modules\EventManagement\Services\AttendeeService;

class AttendeeApiController extends Controller
{
    use ResolvesEvtBusinessForApi;

    public function __construct(private readonly AttendeeService $service) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $eventId  = $request->query('event_id') ? (int) $request->query('event_id') : null;
        $q        = trim((string) $request->query('q', ''));

        $rows = $this->service->list($business, $eventId, $q);

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'event_id'       => ['required', 'integer'],
            'ticket_type_id' => ['nullable', 'integer'],
            'name'           => ['required', 'string', 'max:150'],
            'email'          => ['nullable', 'email', 'max:150'],
            'phone'          => ['nullable', 'string', 'max:40'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $event = Event::where('id', $validated['event_id'])
            ->where('business_id', $business->id)
            ->firstOrFail();

        $attendee = $this->service->create($event, $validated);

        return response()->json(['data' => $attendee, 'message' => 'Attendee added.'], 201);
    }

    public function checkIn(Request $request, Attendee $attendee): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $event    = Event::where('id', $attendee->event_id)->where('business_id', $business->id)->firstOrFail();

        $attendee = $this->service->checkIn($attendee);

        return response()->json(['data' => $attendee, 'message' => 'Checked in.']);
    }

    public function destroy(Request $request, Attendee $attendee): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $event    = Event::where('id', $attendee->event_id)->where('business_id', $business->id)->firstOrFail();

        $this->service->delete($attendee);

        return response()->json(['message' => 'Attendee removed.']);
    }
}
