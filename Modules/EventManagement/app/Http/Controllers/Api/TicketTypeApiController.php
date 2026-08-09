<?php

namespace Modules\EventManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\EventManagement\Http\Controllers\Api\Concerns\ResolvesEvtBusinessForApi;
use Modules\EventManagement\Models\Event;
use Modules\EventManagement\Models\TicketType;
use Modules\EventManagement\Services\TicketTypeService;

class TicketTypeApiController extends Controller
{
    use ResolvesEvtBusinessForApi;

    public function __construct(private readonly TicketTypeService $service) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $eventId  = $request->query('event_id') ? (int) $request->query('event_id') : null;

        $rows = $this->service->list($business, $eventId);

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'event_id'    => ['required', 'integer'],
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'price'       => ['nullable', 'numeric', 'min:0'],
            'capacity'    => ['nullable', 'integer', 'min:1'],
        ]);

        $event = Event::where('id', $validated['event_id'])
            ->where('business_id', $business->id)
            ->firstOrFail();

        $type = $this->service->create($event, $validated);

        return response()->json(['data' => $type, 'message' => 'Ticket type created.'], 201);
    }

    public function update(Request $request, TicketType $ticketType): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $event = Event::where('id', $ticketType->event_id)
            ->where('business_id', $business->id)
            ->firstOrFail();

        $validated = $request->validate([
            'event_id'    => ['sometimes', 'integer'],
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'price'       => ['nullable', 'numeric', 'min:0'],
            'capacity'    => ['nullable', 'integer', 'min:1'],
        ]);

        unset($validated['event_id']); // event cannot change
        $type = $this->service->update($ticketType, $validated);

        return response()->json(['data' => $type, 'message' => 'Ticket type updated.']);
    }

    public function destroy(Request $request, TicketType $ticketType): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        Event::where('id', $ticketType->event_id)
            ->where('business_id', $business->id)
            ->firstOrFail();

        $this->service->delete($ticketType);

        return response()->json(['message' => 'Ticket type deleted.']);
    }
}
