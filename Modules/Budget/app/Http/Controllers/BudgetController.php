<?php

declare(strict_types=1);

namespace Modules\Budget\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Budget\Models\Budget;
use Modules\Budget\Models\BudgetActualEntry;
use Modules\Budget\Models\BudgetItem;
use Modules\Budget\Services\BudgetReportService;
use Modules\Business\Models\Business;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetReportService $reports) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $budgets = Budget::with('items')
            ->where('business_id', $business->id)
            ->latest()
            ->get()
            ->map(fn (Budget $b) => $this->reports->format($b));

        return view('budget::index', [
            'business' => $business,
            'budgets' => $budgets,
            'types' => Budget::typeLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

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

        return redirect()->route('budget.show', $budget)->with('status', 'Budget created.');
    }

    public function show(Request $request, Budget $budget): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $budget->business_id === (int) $business->id, 404);

        $budget->load(['items', 'actualEntries' => fn ($q) => $q->orderByDesc('month')->orderByDesc('id')]);

        return view('budget::show', [
            'business' => $business,
            'budget' => $this->reports->format($budget),
            'spending' => $this->reports->buildSpendingReport($budget, $business->id),
            'actuals' => $budget->actualEntries->map(fn (BudgetActualEntry $e) => [
                'id' => $e->id,
                'category' => $e->category,
                'month' => $e->month->format('Y-m-d'),
                'amount' => (float) $e->amount,
                'amount_fmt' => number_format((float) $e->amount, 2, '.', ','),
                'note' => $e->note,
            ])->values(),
            'types' => Budget::typeLabels(),
            'viewPeriods' => Budget::viewPeriodLabels(),
            'categoryLabels' => Budget::categoryLabels(),
        ]);
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $budget->business_id === (int) $business->id, 404);

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

        return redirect()->route('budget.show', $budget)->with('status', 'Budget updated.');
    }

    public function updateItems(Request $request, Budget $budget): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $budget->business_id === (int) $business->id, 404);

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

        return redirect()->route('budget.show', $budget)->with('status', 'Budget allocations saved.');
    }

    public function updateViewPeriod(Request $request, Budget $budget): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $budget->business_id === (int) $business->id, 404);

        $validated = $request->validate([
            'view_period' => ['required', 'string', Rule::in([Budget::VIEW_DAILY, Budget::VIEW_MONTHLY, Budget::VIEW_YEARLY])],
        ]);

        $budget->update(['view_period' => $validated['view_period']]);

        return redirect()->route('budget.show', $budget)->with('status', 'View updated.');
    }

    public function activate(Request $request, Budget $budget): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $budget->business_id === (int) $business->id, 404);

        DB::transaction(function () use ($budget, $business) {
            Budget::where('business_id', $business->id)
                ->where('id', '!=', $budget->id)
                ->update(['is_active' => false]);
            $budget->update(['is_active' => true]);
        });

        return redirect()->route('budget.show', $budget)->with('status', 'Budget activated.');
    }

    public function deactivate(Request $request, Budget $budget): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $budget->business_id === (int) $business->id, 404);

        $budget->update(['is_active' => false]);

        return redirect()->route('budget.show', $budget)->with('status', 'Budget deactivated.');
    }

    public function storeActual(Request $request, Budget $budget): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $budget->business_id === (int) $business->id, 404);

        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in(Budget::CATEGORIES)],
            'month' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        BudgetActualEntry::create([
            'budget_id' => $budget->id,
            'category' => $validated['category'],
            'month' => Carbon::parse($validated['month'])->startOfMonth(),
            'amount' => $validated['amount'],
            'note' => $validated['note'] ?? null,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return redirect()->route('budget.show', $budget)->with('status', 'Actual spend recorded.');
    }

    public function destroyActual(Request $request, Budget $budget, BudgetActualEntry $actual): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $budget->business_id === (int) $business->id && (int) $actual->budget_id === (int) $budget->id, 404);

        $actual->delete();

        return redirect()->route('budget.show', $budget)->with('status', 'Actual spend entry removed.');
    }

    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }
        abort_unless((int) $budget->business_id === (int) $business->id, 404);

        $budget->delete();

        return redirect()->route('budget.index')->with('status', 'Budget deleted.');
    }

    private function requireBusiness(Request $request): Business|RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        if (! $business) {
            return redirect()->route('dashboard')->withErrors(['business' => 'Select or create a business first.']);
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);

        return $business;
    }
}
