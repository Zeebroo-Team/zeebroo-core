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
            'data'                     => $flows,
            'triggers'                 => AutomationFlow::availableTriggers(),
            'trigger_groups'           => AutomationFlow::availableTriggerGroups(),
            'relation_scoped_triggers' => AutomationFlow::relationScopedTriggers(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $validated = $request->validate([
            'name'           => 'required|string|max:120',
            'description'    => 'nullable|string|max:500',
            'trigger_type'   => 'nullable|string|max:60',
            'trigger_config' => 'nullable|array',
            'flow_data'      => 'nullable|array',
            'is_active'      => 'boolean',
        ]);

        $flow = AutomationFlow::create([
            'business_id'    => $business->id,
            'name'           => $validated['name'],
            'description'    => $validated['description'] ?? null,
            'trigger_type'   => $validated['trigger_type'] ?? null,
            'trigger_config' => $validated['trigger_config'] ?? null,
            'flow_data'      => $validated['flow_data']
                ?? $this->seedTriggerFlowData($validated['trigger_type'] ?? null, $validated['trigger_config'] ?? null),
            'is_active'      => $validated['is_active'] ?? false,
        ]);

        return response()->json(['data' => $this->format($flow)], 201);
    }

    /**
     * Build a Drawflow payload containing just a pre-configured trigger node,
     * so a flow created with a known trigger_type is runnable immediately —
     * without requiring the user to open the editor and manually pick + save
     * the trigger. Mirrors the shape the editor itself saves (flow_data.drawflow
     * .drawflow.Home.data), consumed by AutomationRunnerService::extractNodes().
     */
    private function seedTriggerFlowData(?string $triggerType, ?array $triggerConfig): array
    {
        if (!$triggerType) {
            return ['nodes' => [], 'edges' => []];
        }

        $config = array_filter([
            'trigger'     => $triggerType,
            'relation_id' => $triggerConfig['relation_id'] ?? null,
        ], fn ($v) => $v !== null);

        return [
            'drawflow' => [
                'drawflow' => [
                    'Home' => [
                        'data' => [
                            '1' => [
                                'id'       => 1,
                                'name'     => 'trigger',
                                'data'     => [
                                    'type'   => 'trigger',
                                    'config' => $config,
                                    'preset' => null,
                                ],
                                'class'    => 'ae-node ae-node--trigger',
                                'html'     => '',
                                'typenode' => false,
                                'inputs'   => (object) [],
                                'outputs'  => ['output_1' => ['connections' => []]],
                                'pos_x'    => 100,
                                'pos_y'    => 100,
                            ],
                        ],
                    ],
                ],
            ],
        ];
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
            'name'           => 'sometimes|string|max:120',
            'description'    => 'nullable|string|max:500',
            'trigger_type'   => 'nullable|string|max:60',
            'trigger_config' => 'nullable|array',
            'flow_data'      => 'nullable|array',
            'is_active'      => 'sometimes|boolean',
        ]);

        // Keep trigger_type/trigger_config in sync with what the trigger node has in the saved flow
        if (isset($validated['flow_data'])) {
            $nodes = $validated['flow_data']['drawflow']['drawflow']['Home']['data'] ?? [];
            foreach ($nodes as $node) {
                if (($node['data']['type'] ?? '') === 'trigger') {
                    $config = $node['data']['config'] ?? [];
                    $t = $config['trigger'] ?? null;
                    if ($t) {
                        $validated['trigger_type']   = $t;
                        $validated['trigger_config'] = isset($config['relation_id'])
                            ? ['relation_id' => $config['relation_id']]
                            : null;
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
            'trigger_type'   => $f->trigger_type,
            'trigger_config' => $f->trigger_config,
            'is_active'      => $f->is_active,
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
