<?php

declare(strict_types=1);

namespace Modules\Budget\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Account\Models\Bill;
use Modules\Account\Models\Loan;
use Modules\Account\Models\Rental;
use Modules\Budget\Models\BudgetActualEntry;
use Modules\HRManagement\Models\PayrollCycle;
use Modules\Pos\Models\Sale;
use Modules\Purchase\Models\GoodsReceiveNote;
use Modules\Transaction\Models\LedgerTransaction;

/**
 * Sums real (auto-tracked) and manually-logged spend for one budget category within
 * a date window. Shared by the "spending vs budget" report and by the pre-payment
 * budget limit check, so both agree on what counts as "spent".
 *
 * Two different dates matter for a ledger row: `occurrence_date` (the bill/loan/etc.'s
 * own scheduled date — used for the yearly view's "which month did this belong to")
 * and `created_at` (the real day someone actually recorded the payment — used for the
 * monthly/daily view, so paying off several future months' bills in one sitting all
 * counts against today, not each bill's own future due month).
 */
class BudgetSpendCalculator
{
    private const LEDGER_CLASS_BY_CATEGORY = [
        'purchasing' => GoodsReceiveNote::class,
        'payroll' => PayrollCycle::class,
        'bill' => Bill::class,
        'loans' => Loan::class,
        'rental' => Rental::class,
    ];

    public static function autoSpendForCategory(int $businessId, string $category, Carbon $start, Carbon $end): float
    {
        return self::sumAutoLedgerSpend($businessId, $category, $start, $end, 'occurrence_date');
    }

    public static function autoSpendForCategoryByRecordedDate(int $businessId, string $category, Carbon $start, Carbon $end): float
    {
        return self::sumAutoLedgerSpend($businessId, $category, $start, $end, 'created_at');
    }

    private static function sumAutoLedgerSpend(int $businessId, string $category, Carbon $start, Carbon $end, string $dateColumn): float
    {
        if ($category === 'sales') {
            return (float) DB::table('pos_sales')
                ->where('business_id', $businessId)
                ->where('status', Sale::STATUS_COMPLETED)
                ->whereBetween('sold_at', [$start, $end])
                ->sum('total');
        }

        $query = self::ledgerQueryForCategory($businessId, $category);
        if ($query === null) {
            return 0.0;
        }

        $range = $dateColumn === 'occurrence_date'
            ? [$start->toDateString(), $end->toDateString()]
            : [$start->copy()->startOfDay(), $end->copy()->endOfDay()];

        return (float) $query->whereBetween($dateColumn, $range)->sum('amount');
    }

    private static function ledgerQueryForCategory(int $businessId, string $category): ?Builder
    {
        if ($category === 'renovation') {
            $billIds = Bill::where('business_id', $businessId)
                ->whereNotNull('modification_id')
                ->pluck('id');

            if ($billIds->isEmpty()) {
                return null;
            }

            return LedgerTransaction::where('business_id', $businessId)
                ->where('transactionable_type', Bill::class)
                ->whereIn('transactionable_id', $billIds);
        }

        $class = self::LEDGER_CLASS_BY_CATEGORY[$category] ?? null;
        if (! $class) {
            return null;
        }

        return LedgerTransaction::where('business_id', $businessId)
            ->where('transactionable_type', $class);
    }

    public static function manualSpendForCategory(int $budgetId, string $category, Carbon $start, Carbon $end): float
    {
        return (float) BudgetActualEntry::where('budget_id', $budgetId)
            ->where('category', $category)
            ->whereBetween('month', [$start->copy()->startOfMonth()->toDateString(), $end->copy()->endOfMonth()->toDateString()])
            ->sum('amount');
    }

    public static function totalSpendForCategory(int $businessId, int $budgetId, string $category, Carbon $start, Carbon $end): float
    {
        return round(
            self::autoSpendForCategory($businessId, $category, $start, $end)
            + self::manualSpendForCategory($budgetId, $category, $start, $end),
            2
        );
    }

    public static function totalSpendForCategoryByRecordedDate(int $businessId, int $budgetId, string $category, Carbon $start, Carbon $end): float
    {
        return round(
            self::autoSpendForCategoryByRecordedDate($businessId, $category, $start, $end)
            + self::manualSpendForCategory($budgetId, $category, $start, $end),
            2
        );
    }
}
