<?php

declare(strict_types=1);

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\Account\Models\Bill;
use Modules\Account\Models\Loan;
use Modules\Account\Models\Rental;
use Modules\Budget\Models\Budget;
use Modules\Budget\Models\BudgetActualEntry;
use Modules\Budget\Models\BudgetItem;
use Modules\HRManagement\Models\PayrollCycle;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\Sale;
use Modules\Purchase\Models\GoodsReceiveNote;
use Modules\Transaction\Models\LedgerTransaction;

class PosBudgetApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('budgets')) {
            return response()->json(['data' => [], 'total_count' => 0]);
        }

        $budgets = Budget::with('items')
            ->where('business_id', $business->id)
            ->latest()
            ->get();

        return response()->json([
            'data' => $budgets->map(fn (Budget $b) => $this->format($b))->values(),
            'total_count' => $budgets->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_budget');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in([Budget::TYPE_MONTHLY, Budget::TYPE_YEARLY])],
            'start_date' => ['required', 'date'],
        ]);

        $endDate = Budget::computeEndDate($validated['type'], $validated['start_date']);

        $budget = DB::transaction(function () use ($request, $business, $validated, $endDate) {
            $budget = Budget::create([
                'business_id' => $business->id,
                'created_by_user_id' => $request->user()?->id,
                'name' => $validated['name'],
                'type' => $validated['type'],
                'start_date' => $validated['start_date'],
                'end_date' => $endDate,
                'view_period' => Budget::VIEW_MONTHLY,
                'is_active' => false,
            ]);

            foreach (Budget::CATEGORIES as $category) {
                BudgetItem::create([
                    'budget_id' => $budget->id,
                    'category' => $category,
                    'monthly_amount' => 0,
                    'yearly_amount' => 0,
                ]);
            }

            return $budget;
        });

        $budget->load('items');

        return response()->json([
            'message' => 'Budget created.',
            'data' => $this->format($budget),
        ], 201);
    }

    public function show(Request $request, Budget $budget): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $budget->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Budget not found.'], 404);
        }

        $budget->load('items');

        return response()->json(['data' => $this->format($budget)]);
    }

    public function updateItems(Request $request, Budget $budget): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_budget');
        if ((int) $budget->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Budget not found.'], 404);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.category' => ['required', 'string', Rule::in(Budget::CATEGORIES)],
            'items.*.label' => ['nullable', 'string', 'max:100'],
            'items.*.monthly_amount' => ['required', 'numeric', 'min:0'],
            'items.*.yearly_amount' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($budget, $validated) {
            foreach ($validated['items'] as $item) {
                $label = trim((string) ($item['label'] ?? ''));
                BudgetItem::updateOrCreate(
                    ['budget_id' => $budget->id, 'category' => $item['category']],
                    [
                        'label' => $label !== '' ? $label : null,
                        'monthly_amount' => $item['monthly_amount'],
                        'yearly_amount' => $item['yearly_amount'],
                    ]
                );
            }
        });

        $budget->load('items');

        return response()->json([
            'message' => 'Budget saved.',
            'data' => $this->format($budget),
        ]);
    }

    public function updateViewPeriod(Request $request, Budget $budget): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_budget');
        if ((int) $budget->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Budget not found.'], 404);
        }

        $validated = $request->validate([
            'view_period' => ['required', 'string', Rule::in([Budget::VIEW_DAILY, Budget::VIEW_MONTHLY, Budget::VIEW_YEARLY])],
        ]);

        $budget->update(['view_period' => $validated['view_period']]);
        $budget->load('items');

        return response()->json([
            'message' => 'Budget view updated.',
            'data' => $this->format($budget),
        ]);
    }

    public function activate(Request $request, Budget $budget): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_budget');
        if ((int) $budget->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Budget not found.'], 404);
        }

        DB::transaction(function () use ($budget, $business) {
            Budget::where('business_id', $business->id)
                ->where('id', '!=', $budget->id)
                ->update(['is_active' => false]);
            $budget->update(['is_active' => true]);
        });

        $budget->load('items');

        return response()->json([
            'message' => 'Budget activated.',
            'data' => $this->format($budget),
        ]);
    }

    public function deactivate(Request $request, Budget $budget): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_budget');
        if ((int) $budget->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Budget not found.'], 404);
        }

        $budget->update(['is_active' => false]);
        $budget->load('items');

        return response()->json([
            'message' => 'Budget deactivated.',
            'data' => $this->format($budget),
        ]);
    }

    public function updateDetails(Request $request, Budget $budget): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_budget');
        if ((int) $budget->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Budget not found.'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in([Budget::TYPE_MONTHLY, Budget::TYPE_YEARLY])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
        ]);

        $endDate = $validated['end_date'] ?? Budget::computeEndDate($validated['type'], $validated['start_date']);

        $budget->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'start_date' => $validated['start_date'],
            'end_date' => $endDate,
        ]);
        $budget->load('items');

        return response()->json([
            'message' => 'Budget updated.',
            'data' => $this->format($budget),
        ]);
    }

    public function destroy(Request $request, Budget $budget): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_budget');
        if ((int) $budget->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Budget not found.'], 404);
        }

        $budget->delete();

        return response()->json(['message' => 'Budget deleted.']);
    }

    public function spending(Request $request, Budget $budget): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $budget->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Budget not found.'], 404);
        }

        return response()->json($this->buildSpendingReport($budget, $business->id));
    }

    public function listActuals(Request $request, Budget $budget): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $budget->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Budget not found.'], 404);
        }

        $entries = $budget->actualEntries()->orderByDesc('month')->orderByDesc('id')->get();

        return response()->json([
            'data' => $entries->map(fn (BudgetActualEntry $e) => [
                'id' => $e->id,
                'category' => $e->category,
                'month' => $e->month->format('Y-m-d'),
                'amount' => (float) $e->amount,
                'amount_fmt' => number_format((float) $e->amount, 2, '.', ','),
                'note' => $e->note,
            ])->values(),
        ]);
    }

    public function storeActual(Request $request, Budget $budget): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_budget');
        if ((int) $budget->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Budget not found.'], 404);
        }

        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in(Budget::CATEGORIES)],
            'month' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = BudgetActualEntry::create([
            'budget_id' => $budget->id,
            'category' => $validated['category'],
            'month' => Carbon::parse($validated['month'])->startOfMonth(),
            'amount' => $validated['amount'],
            'note' => $validated['note'] ?? null,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Actual spend recorded.',
            'data' => $this->buildSpendingReport($budget, $business->id),
            'entry_id' => $entry->id,
        ], 201);
    }

    public function destroyActual(Request $request, Budget $budget, BudgetActualEntry $actual): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'fin_budget');
        if ((int) $budget->business_id !== (int) $business->id || (int) $actual->budget_id !== (int) $budget->id) {
            return response()->json(['message' => 'Entry not found.'], 404);
        }

        $actual->delete();

        return response()->json([
            'message' => 'Actual spend removed.',
            'data' => $this->buildSpendingReport($budget, $business->id),
        ]);
    }

    /**
     * Actual spend per category is pulled automatically from the modules that already
     * record real money movement (ledger_transactions + completed POS sales) for the
     * budget's own date range, then combined with manual entries where no automatic
     * source exists (e.g. marketing) or where the user wants to log an adjustment.
     */
    private function buildSpendingReport(Budget $budget, int $businessId): array
    {
        $start = $budget->start_date->copy()->startOfMonth();
        $end = $budget->end_date->copy()->endOfMonth();

        $months = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        $auto = [];
        foreach (Budget::CATEGORIES as $category) {
            $auto[$category] = array_fill_keys($months, 0.0);
        }

        $ledgerMap = [
            Bill::class => 'bill',
            Loan::class => 'loans',
            Rental::class => 'rental',
            PayrollCycle::class => 'payroll',
            GoodsReceiveNote::class => 'purchasing',
        ];

        $txns = LedgerTransaction::where('business_id', $businessId)
            ->whereIn('transactionable_type', array_keys($ledgerMap))
            ->whereBetween('occurrence_date', [$start->toDateString(), $end->toDateString()])
            ->with('transactionable:id,modification_id')
            ->get();

        foreach ($txns as $t) {
            $key = $t->occurrence_date?->format('Y-m');
            if (! $key || ! isset($auto[$ledgerMap[$t->transactionable_type]][$key])) {
                continue;
            }
            $amount = (float) $t->amount;
            $auto[$ledgerMap[$t->transactionable_type]][$key] += $amount;

            if ($t->transactionable_type === Bill::class && $t->transactionable?->modification_id) {
                $auto['renovation'][$key] = ($auto['renovation'][$key] ?? 0) + $amount;
            }
        }

        $sales = DB::table('pos_sales')
            ->where('business_id', $businessId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sold_at', [$start, $end])
            ->select('total', 'sold_at')
            ->get();

        foreach ($sales as $s) {
            $key = Carbon::parse($s->sold_at)->format('Y-m');
            if (isset($auto['sales'][$key])) {
                $auto['sales'][$key] += (float) $s->total;
            }
        }

        $manual = [];
        foreach (Budget::CATEGORIES as $category) {
            $manual[$category] = array_fill_keys($months, 0.0);
        }
        foreach ($budget->actualEntries as $entry) {
            $key = $entry->month->format('Y-m');
            if (isset($manual[$entry->category][$key])) {
                $manual[$entry->category][$key] += (float) $entry->amount;
            }
        }

        $catLabels = Budget::categoryLabels();
        $items = $budget->items->keyBy('category');

        $categories = collect(Budget::CATEGORIES)->map(function (string $category) use ($months, $auto, $manual, $items, $catLabels) {
            $item = $items->get($category);
            $yearlyLimit = (float) ($item->yearly_amount ?? 0);

            $monthsBreakdown = [];
            $cumulative = 0.0;
            foreach ($months as $key) {
                $autoAmount = round($auto[$category][$key] ?? 0, 2);
                $manualAmount = round($manual[$category][$key] ?? 0, 2);
                $spent = round($autoAmount + $manualAmount, 2);
                $cumulative = round($cumulative + $spent, 2);

                $monthsBreakdown[] = [
                    'month' => $key,
                    'month_label' => Carbon::createFromFormat('Y-m', $key)->format('M Y'),
                    'auto_amount' => $autoAmount,
                    'manual_amount' => $manualAmount,
                    'spent' => $spent,
                    'cumulative_spent' => $cumulative,
                    'remaining' => round($yearlyLimit - $cumulative, 2),
                    'over_limit' => $yearlyLimit > 0 && $cumulative > $yearlyLimit,
                ];
            }

            $totalSpent = $cumulative;

            return [
                'category' => $category,
                'category_label' => $item?->label ?: ($catLabels[$category] ?? $category),
                'yearly_limit' => $yearlyLimit,
                'monthly_limit' => (float) ($item->monthly_amount ?? 0),
                'total_spent' => $totalSpent,
                'remaining' => round($yearlyLimit - $totalSpent, 2),
                'percent_used' => $yearlyLimit > 0 ? round(min(999, ($totalSpent / $yearlyLimit) * 100), 1) : null,
                'over_limit' => $yearlyLimit > 0 && $totalSpent > $yearlyLimit,
                'has_auto_source' => $category !== 'marketing',
                'months' => $monthsBreakdown,
            ];
        })->values();

        return [
            'months' => $months,
            'categories' => $categories,
        ];
    }

    private function format(Budget $budget): array
    {
        $typeLabels = Budget::typeLabels();
        $viewLabels = Budget::viewPeriodLabels();
        $catLabels = Budget::categoryLabels();

        $items = $budget->items->keyBy('category');

        $itemRows = collect(Budget::CATEGORIES)->map(function (string $category) use ($items, $catLabels) {
            $item = $items->get($category);
            $monthly = (float) ($item->monthly_amount ?? 0);
            $yearly = (float) ($item->yearly_amount ?? 0);
            $label = $item->label ?? null;

            return [
                'category' => $category,
                'label' => $label,
                'category_label' => $label ?: ($catLabels[$category] ?? $category),
                'monthly_amount' => $monthly,
                'monthly_amount_fmt' => number_format($monthly, 2, '.', ','),
                'yearly_amount' => $yearly,
                'yearly_amount_fmt' => number_format($yearly, 2, '.', ','),
            ];
        })->values();

        $totalMonthly = (float) $itemRows->sum('monthly_amount');
        $totalYearly = (float) $itemRows->sum('yearly_amount');

        return [
            'id' => $budget->id,
            'name' => $budget->name,
            'type' => $budget->type,
            'type_label' => $typeLabels[$budget->type] ?? $budget->type,
            'start_date' => $budget->start_date?->format('Y-m-d'),
            'end_date' => $budget->end_date?->format('Y-m-d'),
            'view_period' => $budget->view_period,
            'view_period_label' => $viewLabels[$budget->view_period] ?? $budget->view_period,
            'is_active' => (bool) $budget->is_active,
            'items' => $itemRows,
            'total_monthly' => $totalMonthly,
            'total_monthly_fmt' => number_format($totalMonthly, 2, '.', ','),
            'total_yearly' => $totalYearly,
            'total_yearly_fmt' => number_format($totalYearly, 2, '.', ','),
            'created_at' => $budget->created_at?->format('Y-m-d'),
        ];
    }
}
