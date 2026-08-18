<?php

namespace Modules\EventManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\EventManagement\Http\Controllers\Api\Concerns\ResolvesEvtBusinessForApi;
use Modules\EventManagement\Models\Event;
use Modules\EventManagement\Services\EventService;

class EventApiController extends Controller
{
    use ResolvesEvtBusinessForApi;

    public function __construct(private readonly EventService $service) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $filter   = (string) $request->query('filter', 'upcoming');
        $q        = trim((string) $request->query('q', ''));

        $events = $this->service->list($business, $filter, $q)
            ->map(fn (Event $e) => [
                'id'              => $e->id,
                'name'            => $e->name,
                'description'     => $e->description,
                'venue'           => $e->venue,
                'start_at'        => $e->start_at?->toIso8601String(),
                'end_at'          => $e->end_at?->toIso8601String(),
                'capacity'        => $e->capacity,
                'category'        => $e->category,
                'status'          => $e->status,
                'attendees_count' => $e->attendees_count,
            ]);

        return response()->json(['data' => $events]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'venue'       => ['nullable', 'string', 'max:255'],
            'start_at'    => ['nullable', 'date'],
            'end_at'      => ['nullable', 'date', 'after_or_equal:start_at'],
            'capacity'    => ['nullable', 'integer', 'min:1'],
            'category'    => ['nullable', 'string', Rule::in(Event::CATEGORIES)],
            'status'      => ['nullable', 'string', Rule::in(Event::STATUSES)],
        ]);

        $event = $this->service->create($business, $validated);

        return response()->json(['data' => $event, 'message' => 'Event created.'], 201);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless($event->business_id === $business->id, 403);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'venue'       => ['nullable', 'string', 'max:255'],
            'start_at'    => ['nullable', 'date'],
            'end_at'      => ['nullable', 'date', 'after_or_equal:start_at'],
            'capacity'    => ['nullable', 'integer', 'min:1'],
            'category'    => ['nullable', 'string', Rule::in(Event::CATEGORIES)],
            'status'      => ['nullable', 'string', Rule::in(Event::STATUSES)],
        ]);

        $event = $this->service->update($event, $validated);

        return response()->json(['data' => $event, 'message' => 'Event updated.']);
    }

    public function destroy(Request $request, Event $event): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_unless($event->business_id === $business->id, 403);

        $this->service->delete($event);

        return response()->json(['message' => 'Event deleted.']);
    }
}
