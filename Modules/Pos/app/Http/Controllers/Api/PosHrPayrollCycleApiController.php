<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\HRManagement\Models\PayrollCycle;
use Modules\HRManagement\Models\PayrollItem;
use Modules\HRManagement\Models\PayrollRuleSet;
use Modules\HRManagement\Services\PayrollComputationService;
use Modules\HRManagement\Services\PayrollCyclePaymentService;
use Modules\HRManagement\Services\PayrollSalarySheetPresentationService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosHrPayrollCycleApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    private const MONTH_NAMES = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    public function __construct(
        private readonly PayrollComputationService $computationService,
        private readonly PayrollCyclePaymentService $paymentService,
        private readonly PayrollSalarySheetPresentationService $salarySheetService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_payroll_cycles')) {
            return response()->json(['data' => [], 'total_count' => 0, 'draft_count' => 0, 'computed_count' => 0, 'finalized_count' => 0]);
        }

        $cycles = PayrollCycle::with('ruleSet')
            ->withCount('items')
            ->withSum('items', 'net_pay')
            ->where('business_id', $business->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        return response()->json([
            'data'            => $cycles->map(fn (PayrollCycle $c) => $this->formatCycle($c))->values(),
            'total_count'     => $cycles->count(),
            'draft_count'     => $cycles->where('status', PayrollCycle::STATUS_DRAFT)->count(),
            'computed_count'  => $cycles->where('status', PayrollCycle::STATUS_COMPUTED)->count(),
            'finalized_count' => $cycles->where('status', PayrollCycle::STATUS_FINALIZED)->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_payroll_cycles')) {
            return response()->json(['message' => 'Payroll module is not set up. Apply a regional template first.'], 422);
        }

        $validated = $request->validate([
            'rule_set_id'  => ['required', 'integer'],
            'name'         => ['required', 'string', 'max:140'],
            'year'         => ['required', 'integer', 'min:2020', 'max:2100'],
            'month'        => ['required', 'integer', 'min:1', 'max:12'],
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        if (! PayrollRuleSet::where('business_id', $business->id)->where('id', $validated['rule_set_id'])->exists()) {
            return response()->json(['message' => 'Rule set not found for this business.'], 422);
        }

        if (PayrollCycle::where('business_id', $business->id)->where('year', $validated['year'])->where('month', $validated['month'])->exists()) {
            $m = self::MONTH_NAMES[$validated['month']] ?? $validated['month'];
            return response()->json(['message' => "A payroll cycle for {$m} {$validated['year']} already exists."], 422);
        }

        $cycle = PayrollCycle::create([...$validated, 'business_id' => $business->id, 'status' => PayrollCycle::STATUS_DRAFT]);
        $cycle->load('ruleSet');
        $cycle->loadCount('items');

        return response()->json(['data' => $this->formatCycle($cycle)], 201);
    }

    public function show(Request $request, PayrollCycle $cycle): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $cycle->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Cycle not found.'], 404);
        }

        $cycle->load(['ruleSet', 'items.employee', 'items.components']);

        $allComponents = $cycle->items->flatMap->components;

        $summary = [
            'gross_earnings'   => (float) $cycle->items->sum('gross_earnings'),
            'total_deductions' => (float) $cycle->items->sum('total_deductions'),
            'net_pay'          => (float) $cycle->items->sum('net_pay'),
            'epf_employee'     => (float) $allComponents->where('code', 'EPF_EMPLOYEE')->sum('amount'),
            'etf_employer'     => (float) $allComponents->where('code', 'ETF_EMPLOYER')->sum('amount'),
            'apit'             => (float) $allComponents->where('code', 'APIT')->sum('amount'),
        ];

        foreach ($summary as $k => $v) {
            $summary[$k . '_fmt'] = number_format($v, 2, '.', ',');
        }

        return response()->json([
            'data' => [
                ...$this->formatCycle($cycle),
                'summary' => $summary,
                'is_paid' => $cycle->ledgerTransactions()->exists(),
                'items'   => $cycle->items->map(fn (PayrollItem $i) => $this->formatItem($i))->values(),
            ],
        ]);
    }

    public function destroy(Request $request, PayrollCycle $cycle): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $cycle->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Cycle not found.'], 404);
        }

        if ($cycle->ledgerTransactions()->exists()) {
            return response()->json(['message' => 'Cannot delete a cycle that has a recorded payment.'], 422);
        }

        $cycle->delete();

        return response()->json(['message' => 'Payroll cycle deleted.']);
    }

    public function compute(Request $request, PayrollCycle $cycle): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $cycle->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Cycle not found.'], 404);
        }

        if ($cycle->isFinalized()) {
            return response()->json(['message' => 'Cannot recompute a finalized cycle.'], 422);
        }

        $result = $this->computationService->computeCycle($cycle);

        $cycle->refresh()->load(['ruleSet', 'items.employee', 'items.components']);

        $allComponents = $cycle->items->flatMap->components;
        $summary = [
            'gross_earnings'   => (float) $cycle->items->sum('gross_earnings'),
            'total_deductions' => (float) $cycle->items->sum('total_deductions'),
            'net_pay'          => (float) $cycle->items->sum('net_pay'),
            'epf_employee'     => (float) $allComponents->where('code', 'EPF_EMPLOYEE')->sum('amount'),
            'etf_employer'     => (float) $allComponents->where('code', 'ETF_EMPLOYER')->sum('amount'),
            'apit'             => (float) $allComponents->where('code', 'APIT')->sum('amount'),
        ];
        foreach ($summary as $k => $v) {
            $summary[$k . '_fmt'] = number_format($v, 2, '.', ',');
        }

        return response()->json([
            'message'          => "Computed {$result['computed']} employee(s).",
            'computation_errors' => $result['errors'],
            'data' => [
                ...$this->formatCycle($cycle),
                'summary' => $summary,
                'is_paid' => false,
                'items'   => $cycle->items->map(fn (PayrollItem $i) => $this->formatItem($i))->values(),
            ],
        ]);
    }

    public function finalize(Request $request, PayrollCycle $cycle): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $cycle->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Cycle not found.'], 404);
        }

        if ($cycle->isFinalized()) {
            return response()->json(['message' => 'Cycle is already finalized.'], 422);
        }

        try {
            $this->computationService->finalizeCycle($cycle, $request->user()->id);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Cycle finalized successfully.', 'status' => PayrollCycle::STATUS_FINALIZED]);
    }

    public function payment(Request $request, PayrollCycle $cycle): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $cycle->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Cycle not found.'], 404);
        }

        $validated = $request->validate([
            'deduct_account_id' => ['required', 'integer'],
        ]);

        try {
            $this->paymentService->recordPayment($request->user(), $business, $cycle, $validated['deduct_account_id']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->validator->errors()->first()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Payment recorded successfully.']);
    }

    public function salarySheet(Request $request, PayrollCycle $cycle): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $cycle->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Cycle not found.'], 404);
        }

        $sheet = $this->salarySheetService->forCycle($cycle, $business);

        return response()->json(['data' => $sheet]);
    }

    private function formatCycle(PayrollCycle $c): array
    {
        return [
            'id'                => $c->id,
            'name'              => $c->name,
            'year'              => (int) $c->year,
            'month'             => (int) $c->month,
            'month_label'       => self::MONTH_NAMES[(int) $c->month] ?? '',
            'period_start'      => $c->period_start?->format('Y-m-d'),
            'period_end'        => $c->period_end?->format('Y-m-d'),
            'status'            => $c->status,
            'status_label'      => ucfirst($c->status ?? 'draft'),
            'rule_set_id'       => $c->rule_set_id,
            'rule_set_name'     => $c->ruleSet?->name,
            'currency'          => $c->ruleSet?->currency ?? 'LKR',
            'computed_at'       => $c->computed_at?->format('Y-m-d H:i'),
            'finalized_at'      => $c->finalized_at?->format('Y-m-d H:i'),
            'items_count'       => (int) ($c->items_count ?? $c->items?->count() ?? 0),
            'total_net_pay'     => (float) ($c->items_net_pay_sum ?? 0),
            'total_net_pay_fmt' => number_format((float) ($c->items_net_pay_sum ?? 0), 2, '.', ','),
        ];
    }

    private function formatItem(PayrollItem $i): array
    {
        return [
            'id'                   => $i->id,
            'employee_id'          => $i->employee_id,
            'employee_name'        => $i->employee?->full_name,
            'employee_code'        => $i->employee?->employee_id,
            'status'               => $i->status,
            'basic_salary'         => (float) $i->basic_salary,
            'basic_salary_fmt'     => number_format((float) $i->basic_salary, 2, '.', ','),
            'overtime_amount'      => (float) $i->overtime_amount,
            'overtime_amount_fmt'  => number_format((float) $i->overtime_amount, 2, '.', ','),
            'gross_earnings'       => (float) $i->gross_earnings,
            'gross_earnings_fmt'   => number_format((float) $i->gross_earnings, 2, '.', ','),
            'total_deductions'     => (float) $i->total_deductions,
            'total_deductions_fmt' => number_format((float) $i->total_deductions, 2, '.', ','),
            'net_pay'              => (float) $i->net_pay,
            'net_pay_fmt'          => number_format((float) $i->net_pay, 2, '.', ','),
            'inputs_json'          => $i->inputs_json ?? [],
            'has_errors'           => $i->status === 'error',
            'errors'               => $i->snapshot_json['errors'] ?? [],
        ];
    }
}
