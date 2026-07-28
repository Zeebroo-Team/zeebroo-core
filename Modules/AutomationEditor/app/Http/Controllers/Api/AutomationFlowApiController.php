<?php

namespace Modules\AutomationEditor\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\AutomationEditor\Models\AutomationFlow;
use Modules\AutomationEditor\Models\AutomationNotification;
use Modules\AutomationEditor\Services\AutomationRunnerService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class AutomationFlowApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly AutomationRunnerService $runner,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $flows = AutomationFlow::where('business_id', $business->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($f) => $this->format($f));

        return response()->json([
            'data'     => $flows,
            'triggers' => AutomationFlow::availableTriggers(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'name'         => 'required|string|max:120',
            'description'  => 'nullable|string|max:500',
            'trigger_type' => 'nullable|string|max:60',
            'flow_data'    => 'nullable|array',
            'is_active'    => 'boolean',
        ]);

        $flow = AutomationFlow::create([
            'business_id'  => $business->id,
            'name'         => $validated['name'],
            'description'  => $validated['description'] ?? null,
            'trigger_type' => $validated['trigger_type'] ?? null,
            'flow_data'    => $validated['flow_data'] ?? ['nodes' => [], 'edges' => []],
            'is_active'    => $validated['is_active'] ?? false,
        ]);

        return response()->json(['data' => $this->format($flow)], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $flow = AutomationFlow::where('business_id', $business->id)->where('id', $id)->firstOrFail();

        return response()->json(['data' => $this->format($flow, true)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $flow = AutomationFlow::where('business_id', $business->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:120',
            'description'  => 'nullable|string|max:500',
            'trigger_type' => 'nullable|string|max:60',
            'flow_data'    => 'nullable|array',
            'is_active'    => 'sometimes|boolean',
        ]);

        // Keep trigger_type in sync with what the trigger node has in the saved flow
        if (isset($validated['flow_data'])) {
            $nodes = $validated['flow_data']['drawflow']['drawflow']['Home']['data'] ?? [];
            foreach ($nodes as $node) {
                if (($node['data']['type'] ?? '') === 'trigger') {
                    $t = $node['data']['config']['trigger'] ?? null;
                    if ($t) {
                        $validated['trigger_type'] = $t;
                    }
                    break;
                }
            }
        }

        $flow->update($validated);

        return response()->json(['data' => $this->format($flow->fresh(), true)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        AutomationFlow::where('business_id', $business->id)->where('id', $id)->delete();

        return response()->json(['message' => 'Automation deleted.']);
    }

    public function runs(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $flow = AutomationFlow::where('business_id', $business->id)->where('id', $id)->firstOrFail();

        $runs = $flow->runs()->orderByDesc('created_at')->limit(50)->get()->map(fn ($r) => [
            'id'              => $r->id,
            'status'          => $r->status,
            'error'           => $r->error_message,
            'result'          => $r->result,
            'trigger_payload' => $r->trigger_payload,
            'created_at'      => $r->created_at?->toDateTimeString(),
        ]);

        return response()->json(['data' => $runs]);
    }

    public function triggerManual(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $flow     = AutomationFlow::where('business_id', $business->id)->where('id', $id)->firstOrFail();

        if ($flow->trigger_type !== 'manual') {
            return response()->json(['message' => 'This flow does not use a manual trigger.'], 422);
        }

        $user = Auth::user();
        $this->runner->dispatch('manual', $business, [
            'event'   => 'manual',
            'trigger' => ['by' => $user?->email ?? 'api', 'at' => now()->toIso8601String()],
            'payload' => $request->input('payload', []),
        ]);

        return response()->json(['message' => 'Flow triggered.']);
    }

    public function notifications(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $notifs = AutomationNotification::query()
            ->where('business_id', $business->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($n) => [
                'id'         => $n->id,
                'flow_id'    => $n->flow_id,
                'title'      => $n->title,
                'message'    => $n->message,
                'read'       => $n->isRead(),
                'created_at' => $n->created_at?->toDateTimeString(),
            ]);

        $unread = AutomationNotification::query()
            ->where('business_id', $business->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['data' => $notifs, 'unread' => $unread]);
    }

    public function markNotificationsRead(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        AutomationNotification::query()
            ->where('business_id', $business->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Notifications marked as read.']);
    }

    private function format(AutomationFlow $f, bool $withFlowData = false): array
    {
        $out = [
            'id'           => $f->id,
            'name'         => $f->name,
            'description'  => $f->description,
            'trigger_type' => $f->trigger_type,
            'is_active'    => $f->is_active,
            'run_count'    => $f->run_count,
            'last_run_at'  => $f->last_run_at?->toDateTimeString(),
            'updated_at'   => $f->updated_at?->toDateTimeString(),
            'created_at'   => $f->created_at?->toDateTimeString(),
        ];
        if ($withFlowData) {
            $out['flow_data'] = $f->flow_data ?? ['nodes' => [], 'edges' => []];
        }
        return $out;
    }
}
