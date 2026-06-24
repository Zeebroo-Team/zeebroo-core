<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\HRManagement\Models\PayrollCycle;
use Modules\HRManagement\Models\PayrollRule;
use Modules\HRManagement\Models\PayrollRuleSet;
use Modules\HRManagement\Payroll\RegionalTemplates\PayrollRegionalTemplateRegistry;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosHrPayrollRuleSetApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly PayrollRegionalTemplateRegistry $templates) {}

    // ── Rule sets ──────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_payroll_rule_sets')) {
            return response()->json(['data' => []]);
        }

        $ruleSets = PayrollRuleSet::withCount('rules')
            ->where('business_id', $business->id)
            ->orderByDesc('is_default')
            ->orderByDesc('effective_from')
            ->get();

        return response()->json([
            'data' => $ruleSets->map(fn (PayrollRuleSet $rs) => $this->formatRuleSet($rs))->values(),
        ]);
    }

    public function show(Request $request, PayrollRuleSet $ruleSet): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $ruleSet->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Rule set not found.'], 404);
        }

        $ruleSet->loadCount('rules');
        $rules = $ruleSet->rules()->get();

        return response()->json([
            'data' => [
                ...$this->formatRuleSet($ruleSet),
                'rules' => $rules->map(fn (PayrollRule $r) => $this->formatRule($r))->values(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_payroll_rule_sets')) {
            return response()->json(['message' => 'Payroll module is not set up yet.'], 422);
        }

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:140'],
            'currency'       => ['required', 'string', 'max:16'],
            'effective_from' => ['required', 'date'],
            'effective_to'   => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_default'     => ['boolean'],
            'notes'          => ['nullable', 'string', 'max:5000'],
        ]);

        if (! empty($validated['is_default'])) {
            PayrollRuleSet::where('business_id', $business->id)->update(['is_default' => false]);
        }

        $ruleSet = PayrollRuleSet::create([
            ...$validated,
            'business_id' => $business->id,
            'is_active'   => true,
        ]);

        $ruleSet->loadCount('rules');

        return response()->json(['data' => $this->formatRuleSet($ruleSet)], 201);
    }

    public function update(Request $request, PayrollRuleSet $ruleSet): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $ruleSet->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Rule set not found.'], 404);
        }

        $validated = $request->validate([
            'name'           => ['sometimes', 'string', 'max:140'],
            'currency'       => ['sometimes', 'string', 'max:16'],
            'effective_from' => ['sometimes', 'date'],
            'effective_to'   => ['nullable', 'date'],
            'is_default'     => ['boolean'],
            'is_active'      => ['boolean'],
            'notes'          => ['nullable', 'string', 'max:5000'],
        ]);

        if (! empty($validated['is_default'])) {
            PayrollRuleSet::where('business_id', $business->id)
                ->where('id', '!=', $ruleSet->id)
                ->update(['is_default' => false]);
        }

        $ruleSet->update($validated);
        $ruleSet->loadCount('rules');

        return response()->json(['data' => $this->formatRuleSet($ruleSet)]);
    }

    public function destroy(Request $request, PayrollRuleSet $ruleSet): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $ruleSet->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Rule set not found.'], 404);
        }

        if (PayrollCycle::where('rule_set_id', $ruleSet->id)->exists()) {
            return response()->json(['message' => 'Cannot delete a rule set that has payroll cycles attached.'], 422);
        }

        $ruleSet->delete();

        return response()->json(['message' => 'Rule set deleted.']);
    }

    // ── Rules ──────────────────────────────────────────────────────────────

    public function storeRule(Request $request, PayrollRuleSet $ruleSet): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $ruleSet->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Rule set not found.'], 404);
        }

        $validated = $request->validate([
            'code'             => ['required', 'string', 'max:64'],
            'name'             => ['required', 'string', 'max:140'],
            'component_type'   => ['required', 'in:earning,deduction,statutory,overtime,informational,employer_tracking'],
            'calculation_mode' => ['required', 'in:fixed,percentage,slab,formula'],
            'sort_order'       => ['nullable', 'integer', 'min:0'],
            'is_taxable'       => ['boolean'],
            'is_statutory'     => ['boolean'],
            'config_json'      => ['nullable', 'string'],
        ]);

        $code = strtoupper(preg_replace('/[^A-Z0-9_]/i', '_', $validated['code']));

        if ($ruleSet->rules()->where('code', $code)->exists()) {
            return response()->json(['message' => "A rule with code {$code} already exists in this rule set."], 422);
        }

        $configJson = null;
        if (! empty($validated['config_json'])) {
            $decoded = json_decode($validated['config_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['message' => 'config_json is not valid JSON.'], 422);
            }
            $configJson = $decoded;
        }

        $rule = $ruleSet->rules()->create([
            'code'             => $code,
            'name'             => $validated['name'],
            'component_type'   => $validated['component_type'],
            'calculation_mode' => $validated['calculation_mode'],
            'sort_order'       => $validated['sort_order'] ?? 0,
            'is_taxable'       => $validated['is_taxable'] ?? false,
            'is_statutory'     => $validated['is_statutory'] ?? false,
            'is_active'        => true,
            'config_json'      => $configJson,
        ]);

        return response()->json(['data' => $this->formatRule($rule)], 201);
    }

    public function updateRule(Request $request, PayrollRuleSet $ruleSet, PayrollRule $rule): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $ruleSet->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Rule set not found.'], 404);
        }

        if ((int) $rule->rule_set_id !== (int) $ruleSet->id) {
            return response()->json(['message' => 'Rule does not belong to this rule set.'], 404);
        }

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:140'],
            'sort_order'  => ['sometimes', 'integer', 'min:0'],
            'is_taxable'  => ['boolean'],
            'is_statutory'=> ['boolean'],
            'is_active'   => ['boolean'],
            'config_json' => ['nullable', 'string'],
        ]);

        if (array_key_exists('config_json', $validated)) {
            if (! empty($validated['config_json'])) {
                $decoded = json_decode($validated['config_json'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return response()->json(['message' => 'config_json is not valid JSON.'], 422);
                }
                $validated['config_json'] = $decoded;
            } else {
                $validated['config_json'] = null;
            }
        }

        $rule->update($validated);

        return response()->json(['data' => $this->formatRule($rule->fresh())]);
    }

    public function destroyRule(Request $request, PayrollRuleSet $ruleSet, PayrollRule $rule): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $ruleSet->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Rule set not found.'], 404);
        }

        if ((int) $rule->rule_set_id !== (int) $ruleSet->id) {
            return response()->json(['message' => 'Rule does not belong to this rule set.'], 404);
        }

        $rule->delete();

        return response()->json(['message' => 'Rule deleted.']);
    }

    // ── Regional templates ─────────────────────────────────────────────────

    public function templateIndex(): JsonResponse
    {
        return response()->json(['data' => $this->templates->cards()]);
    }

    public function templateInstall(Request $request, string $key): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_payroll_rule_sets')) {
            return response()->json(['message' => 'Payroll module is not set up yet.'], 422);
        }

        $template = $this->templates->get($key);

        if (! $template) {
            return response()->json(['message' => "Template '{$key}' not found."], 404);
        }

        $message = $template->install($business);

        return response()->json(['message' => $message ?: 'Template installed successfully.']);
    }

    // ── Formatters ─────────────────────────────────────────────────────────

    private function formatRuleSet(PayrollRuleSet $rs): array
    {
        return [
            'id'             => $rs->id,
            'name'           => $rs->name,
            'currency'       => $rs->currency ?? 'LKR',
            'effective_from' => $rs->effective_from?->format('Y-m-d'),
            'effective_to'   => $rs->effective_to?->format('Y-m-d'),
            'is_default'     => (bool) $rs->is_default,
            'is_active'      => (bool) $rs->is_active,
            'notes'          => $rs->notes,
            'rules_count'    => (int) ($rs->rules_count ?? 0),
        ];
    }

    private function formatRule(PayrollRule $r): array
    {
        return [
            'id'               => $r->id,
            'rule_set_id'      => $r->rule_set_id,
            'code'             => $r->code,
            'name'             => $r->name,
            'component_type'   => $r->component_type,
            'calculation_mode' => $r->calculation_mode,
            'sort_order'       => (int) $r->sort_order,
            'is_taxable'       => (bool) $r->is_taxable,
            'is_statutory'     => (bool) $r->is_statutory,
            'is_active'        => (bool) $r->is_active,
            'config_json'      => $r->config_json ?? [],
        ];
    }
}
