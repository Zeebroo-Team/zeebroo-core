<?php

namespace Modules\AutomationEditor\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AutomationEditor\Models\AutomationFlow;
use Modules\AutomationEditor\Services\AutomationRunnerService;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Project;

class AutomationController extends Controller
{
    public function __construct(
        private readonly AutomationRunnerService $runner,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $flows = AutomationFlow::where('business_id', $business->id)
            ->orderByDesc('updated_at')
            ->get();

        return view('automationeditor::index', [
            'business'      => $business,
            'flows'         => $flows,
            'triggerGroups' => AutomationFlow::availableTriggerGroups(),
            'triggerLabels' => AutomationFlow::availableTriggers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'description'  => ['nullable', 'string', 'max:500'],
            'trigger_type' => ['nullable', 'string', 'max:60'],
        ]);

        $flow = AutomationFlow::create([
            'business_id'  => $business->id,
            'name'         => $validated['name'],
            'description'  => $validated['description'] ?? null,
            'trigger_type' => $validated['trigger_type'] ?? null,
            'flow_data'    => $this->seedTriggerFlowData($validated['trigger_type'] ?? null),
            'is_active'    => false,
        ]);

        return redirect()->route('automations.edit', $flow)->with('status', 'Automation created — build your flow below.');
    }

    public function edit(Request $request, AutomationFlow $automation): View|RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        if ((int) $automation->business_id !== (int) $business->id) {
            abort(403);
        }

        $relations = Project::where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('automationeditor::builder', [
            'business'  => $business,
            'flow'      => $automation,
            'relations' => $relations,
        ]);
    }

    public function update(Request $request, AutomationFlow $automation): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if ((int) $automation->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'flow_data'   => ['nullable', 'array'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        // Keep trigger_type/trigger_config in sync with the trigger node saved in the flow
        // (mirrors AutomationFlowApiController::update — the runner dispatches by trigger_type).
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

        $automation->update($validated);

        return response()->json(['data' => $this->format($automation->fresh())]);
    }

    public function destroy(Request $request, AutomationFlow $automation): RedirectResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        if ((int) $automation->business_id !== (int) $business->id) {
            abort(403);
        }

        $automation->delete();

        return redirect()->route('automations.index')->with('status', 'Automation deleted.');
    }

    public function runs(Request $request, AutomationFlow $automation): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if ((int) $automation->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $runs = $automation->runs()->orderByDesc('created_at')->limit(50)->get()->map(fn ($r) => [
            'id'              => $r->id,
            'status'          => $r->status,
            'error'           => $r->error_message,
            'result'          => $r->result,
            'trigger_payload' => $r->trigger_payload,
            'created_at'      => $r->created_at?->toDateTimeString(),
        ]);

        return response()->json(['data' => $runs]);
    }

    public function trigger(Request $request, AutomationFlow $automation): JsonResponse
    {
        $business = $this->resolveBusiness($request);
        if ($business instanceof RedirectResponse) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if ((int) $automation->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($automation->trigger_type !== 'manual') {
            return response()->json(['message' => 'This flow does not use a manual trigger.'], 422);
        }

        $this->runner->dispatch('manual', $business, [
            'event'   => 'manual',
            'trigger' => ['by' => $request->user()?->email ?? 'web', 'at' => now()->toIso8601String()],
            'payload' => $request->input('payload', []),
        ]);

        return response()->json(['message' => 'Flow triggered.']);
    }

    /**
     * Build a Drawflow payload containing just a pre-configured trigger node so a flow
     * created with a known trigger_type is runnable immediately. Mirrors
     * AutomationFlowApiController::seedTriggerFlowData() — same shape the editor itself
     * saves (flow_data.drawflow.drawflow.Home.data), consumed by
     * AutomationRunnerService::extractNodes().
     */
    private function seedTriggerFlowData(?string $triggerType): array
    {
        if (!$triggerType) {
            return ['nodes' => [], 'edges' => []];
        }

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
                                    'config' => ['trigger' => $triggerType],
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

    /**
     * Mirrors AutomationFlowApiController::format($f, withFlowData: true) — the builder's
     * JS replaces its local `flow` object with this response after every save/toggle, so
     * flow_data must round-trip or a later read of flow.flow_data would go stale.
     */
    private function format(AutomationFlow $f): array
    {
        return [
            'id'             => $f->id,
            'name'           => $f->name,
            'description'    => $f->description,
            'trigger_type'   => $f->trigger_type,
            'trigger_config' => $f->trigger_config,
            'is_active'      => $f->is_active,
            'run_count'      => $f->run_count,
            'last_run_at'    => $f->last_run_at?->toDateTimeString(),
            'updated_at'     => $f->updated_at?->toDateTimeString(),
            'flow_data'      => $f->flow_data ?? ['nodes' => [], 'edges' => []],
        ];
    }

    /**
     * Resolves the business currently selected in the navbar/session — the same
     * source the sidebar uses (Business::currentForNavbar()) to decide whether the
     * "Automations" link is even shown. A naive `where('user_id', ...)->first()`
     * would ignore the session's selected_business_id and any businesses the user
     * only has member (non-owner) access to, resolving the wrong business for
     * multi-business accounts.
     */
    private function resolveBusiness(Request $request): Business|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        $business = Business::currentForNavbar($user);

        if ($business === null) {
            return redirect()->route('business.create');
        }

        return $business;
    }
}
