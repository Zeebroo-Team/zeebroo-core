<?php

declare(strict_types=1);

namespace Modules\Budget\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Account\Models\Bill;
use Modules\Account\Models\Loan;
use Modules\Account\Models\Rental;
use Modules\Budget\Models\Budget;
use Modules\Budget\Models\BudgetActualEntry;
use Modules\HRManagement\Models\PayrollCycle;
use Modules\Pos\Models\Sale;
use Modules\Purchase\Models\GoodsReceiveNote;
use Modules\Transaction\Models\LedgerTransaction;

/**
 * Shared budget formatting/spend-aggregation logic used by both the mobile
 * JSON API (PosBudgetApiController) and the web UI (BudgetController), so
 * "actual spend" is computed identically everywhere.
 */
class BudgetReportService
{
    public function format(Budget $budget): array
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

    /**
     * Actual spend per category is pulled automatically from the modules that already
     * record real money movement (ledger_transactions + completed POS sales) for the
     * budget's own date range, then combined with manual entries where no automatic
     * source exists (e.g. marketing) or where the user wants to log an adjustment.
     */
    public function buildSpendingReport(Budget $budget, int $businessId): array
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
}
