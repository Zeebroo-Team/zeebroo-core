<?php

namespace Modules\Developers\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Developers\Models\Webhook;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class DeveloperWebhookApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $hooks = Webhook::where('business_id', $business->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($w) => $this->formatHook($w));

        return response()->json([
            'data'            => $hooks,
            'available_events'=> Webhook::availableEvents(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'name'   => 'required|string|max:100',
            'url'    => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => ['string', 'in:' . implode(',', array_keys(Webhook::availableEvents()))],
        ]);

        $hook = Webhook::create([
            'business_id'  => $business->id,
            'name'         => $validated['name'],
            'url'          => $validated['url'],
            'events'       => $validated['events'],
            'secret'       => Webhook::generateSecret(),
            'is_active'    => true,
            'failure_count'=> 0,
        ]);

        return response()->json(['data' => $this->formatHook($hook)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $hook = Webhook::where('business_id', $business->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'name'     => 'sometimes|string|max:100',
            'url'      => 'sometimes|url|max:500',
            'events'   => 'sometimes|array|min:1',
            'events.*' => ['string', 'in:' . implode(',', array_keys(Webhook::availableEvents()))],
            'is_active'=> 'sometimes|boolean',
        ]);

        $hook->update($validated);

        return response()->json(['data' => $this->formatHook($hook->fresh())]);
    }

    public function regenerateSecret(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $hook = Webhook::where('business_id', $business->id)->where('id', $id)->firstOrFail();
        $hook->update(['secret' => Webhook::generateSecret(), 'failure_count' => 0]);

        return response()->json(['data' => $this->formatHook($hook->fresh())]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        Webhook::where('business_id', $business->id)->where('id', $id)->delete();

        return response()->json(['message' => 'Webhook deleted.']);
    }

    private function formatHook(Webhook $w): array
    {
        return [
            'id'               => $w->id,
            'name'             => $w->name,
            'url'              => $w->url,
            'secret'           => $w->secret,
            'events'           => $w->events,
            'is_active'        => $w->is_active,
            'failure_count'    => $w->failure_count,
            'last_triggered_at'=> $w->last_triggered_at?->toDateTimeString(),
            'created_at'       => $w->created_at?->toDateTimeString(),
        ];
    }
}
