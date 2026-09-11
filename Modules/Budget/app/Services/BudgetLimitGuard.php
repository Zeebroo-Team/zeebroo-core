<?php

declare(strict_types=1);

namespace Modules\Budget\Services;

use Carbon\Carbon;
use Modules\Budget\Models\Budget;

/**
 * Pre-payment check: would recording this amount against a category push the
 * business's active budget past its limit for that category?
 *
 * The enforcement window follows the active budget's own "Budget element view"
 * setting (Budget::view_period):
 *  - Yearly: tracks the running total across the WHOLE budget period, bucketed
 *    by each transaction's own scheduled date (occurrence_date) — so spend can
 *    swing month to month (24 one month, 0 the next) as long as the cumulative
 *    total for the budget period stays under the yearly limit.
 *  - Monthly / Daily: tracks how much has actually been RECORDED (paid) today
 *    or this real calendar month — using each ledger row's created_at, not the
 *    bill/loan/etc.'s own due date. This matters when someone pays off several
 *    future months' bills in one sitting: each payment counts against today's
 *    bucket, not the future month its due date happens to fall in.
 *
 * Revenue categories (sales) are never blocked — a budget limit caps spending,
 * not income.
 */
class BudgetLimitGuard
{
    /**
     * @return array{
     *     allowed: bool,
     *     message?: string,
     *     category?: string,
     *     category_label?: string,
     *     limit?: float,
     *     already_spent?: float,
     *     attempted_amount?: float,
     *     prospective_total?: float,
     *     over_by?: float,
     *     window_label?: string,
     *     budget_name?: string,
     * }
     */
    public static function evaluate(int $businessId, string $category, float $newAmount, ?Carbon $date = null): array
    {
        if ($category === 'sales' || $newAmount <= 0) {
            return ['allowed' => true];
        }

        $date ??= Carbon::now();

        $budget = Budget::with('items')
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->first();

        if (! $budget) {
            return ['allowed' => true];
        }

        $item = $budget->items->firstWhere('category', $category);
        if (! $item) {
            return ['allowed' => true];
        }

        $usesRecordedDate = in_array($budget->view_period, [Budget::VIEW_MONTHLY, Budget::VIEW_DAILY], true);

        // Eligibility: for Monthly/Daily we're checking "what happens today", so
        // today is what must fall inside the budget's period. For Yearly we're
        // bucketing by the transaction's own scheduled date, so that's what must
        // fall inside the period instead.
        $eligibilityDate = $usesRecordedDate ? Carbon::now() : $date;
        if ($eligibilityDate->lt($budget->start_date) || $eligibilityDate->gt($budget->end_date)) {
            return ['allowed' => true];
        }

        [$start, $end, $limit, $windowLabel] = self::windowFor($budget, $item, $date);

        if ($limit <= 0) {
            return ['allowed' => true];
        }

        $alreadySpent = $usesRecordedDate
            ? BudgetSpendCalculator::totalSpendForCategoryByRecordedDate($businessId, $budget->id, $category, $start, $end)
            : BudgetSpendCalculator::totalSpendForCategory($businessId, $budget->id, $category, $start, $end);

        $prospective = round($alreadySpent + $newAmount, 2);

        if ($prospective <= $limit + 0.005) {
            return ['allowed' => true];
        }

        $catLabel = $item->label ?: (Budget::categoryLabels()[$category] ?? $category);
        $overBy = round($prospective - $limit, 2);

        return [
            'allowed' => false,
            'category' => $category,
            'category_label' => $catLabel,
            'limit' => round($limit, 2),
            'already_spent' => round($alreadySpent, 2),
            'attempted_amount' => round($newAmount, 2),
            'prospective_total' => $prospective,
            'over_by' => $overBy,
            'window_label' => $windowLabel,
            'budget_name' => $budget->name,
            'message' => sprintf(
                'Blocked by budget "%s": %s spend for %s would reach %s against a %s limit of %s — %s over.',
                $budget->name,
                $catLabel,
                $windowLabel,
                number_format($prospective, 2),
                $windowLabel,
                number_format($limit, 2),
                number_format($overBy, 2)
            ),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon, 2: float, 3: string} */
    private static function windowFor(Budget $budget, $item, Carbon $date): array
    {
        return match ($budget->view_period) {
            Budget::VIEW_MONTHLY => [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
                (float) $item->monthly_amount,
                'this month',
            ],
            Budget::VIEW_DAILY => [
                Carbon::now()->startOfDay(),
                Carbon::now()->endOfDay(),
                self::dailyLimit($item, Carbon::now()),
                'today',
            ],
            default => [
                $budget->start_date->copy(),
                $budget->end_date->copy(),
                (float) $item->yearly_amount,
                'the budget period',
            ],
        };
    }

    private static function dailyLimit($item, Carbon $date): float
    {
        $monthly = (float) $item->monthly_amount;
        if ($monthly <= 0) {
            return 0.0;
        }

        return round($monthly / $date->daysInMonth, 2);
    }
}
