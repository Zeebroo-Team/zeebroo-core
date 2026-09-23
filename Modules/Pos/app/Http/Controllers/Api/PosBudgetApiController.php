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
use Modules\Budget\Models\Budget;
use Modules\Budget\Models\BudgetActualEntry;
use Modules\Budget\Models\BudgetItem;
use Modules\Budget\Services\BudgetReportService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosBudgetApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly BudgetReportService $reports) {}

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
            'data' => $budgets->map(fn (Budget $b) => $this->reports->format($b))->values(),
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
            'data' => $this->reports->format($budget),
        ], 201);
    }

    public function show(Request $request, Budget $budget): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        if ((int) $budget->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Budget not found.'], 404);
        }

        $budget->load('items');

        return response()->json(['data' => $this->reports->format($budget)]);
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
            'data' => $this->reports->format($budget),
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
            'data' => $this->reports->format($budget),
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
            'data' => $this->reports->format($budget),
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
            'data' => $this->reports->format($budget),
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
            'data' => $this->reports->format($budget),
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

        return response()->json($this->reports->buildSpendingReport($budget, $business->id));
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
            'data' => $this->reports->buildSpendingReport($budget, $business->id),
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
            'data' => $this->reports->buildSpendingReport($budget, $business->id),
        ]);
    }

}
