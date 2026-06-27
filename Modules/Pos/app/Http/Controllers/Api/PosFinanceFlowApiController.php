<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Account\Models\Bill;
use Modules\Account\Models\Loan;
use Modules\Account\Models\Rental;
use Modules\Account\Services\BillService;
use Modules\Account\Services\RentalService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Account\Services\LoanOverviewTooltipService;

class PosFinanceFlowApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly BillService $billService,
        private readonly RentalService $rentalService,
        private readonly LoanOverviewTooltipService $loanService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        // ── Bills ────────────────────────────────────────────────────────────
        $bills = Bill::where('business_id', $business->id)
            ->with(['ledgerTransactions'])
            ->orderBy('name')
            ->get(['id', 'name', 'recurring_cost', 'due_date', 'first_installment_due_date',
                   'payment_mode', 'bill_category', 'bill_category_other', 'recurring_type',
                   'amount_varies_by_usage', 'business_id', 'property_id']);

        $billsMonthly = 0.0;
        $billsOverdue = 0;

        $billsData = $bills->map(function (Bill $b) use (&$billsMonthly, &$billsOverdue) {
            $isOverdue = $this->billService->billHasOverduePayments($b);
            if ($isOverdue) {
                $billsOverdue++;
            }

            $monthly = $b->isOneTime() ? 0.0 : $this->billMonthlyEquiv(
                (float) $b->recurring_cost,
                (string) ($b->recurring_type ?? '')
            );
            $billsMonthly += $monthly;

            return [
                'id'             => $b->id,
                'name'           => $b->name,
                'category_label' => $b->categoryDisplayLabel(),
                'is_one_time'    => $b->isOneTime(),
                'amount_fmt'     => $b->amount_varies_by_usage
                    ? 'Varies'
                    : number_format((float) $b->recurring_cost, 2, '.', ','),
                'cadence'        => $b->isOneTime()
                    ? 'One-time'
                    : (Bill::recurringTypes()[$b->recurring_type] ?? $b->recurring_type),
                'overdue'        => $isOverdue,
                'property_id'    => $b->property_id,
            ];
        })->values();

        // ── Loans ────────────────────────────────────────────────────────────
        $loans = Loan::where('business_id', $business->id)
            ->latest()
            ->get();

        $loansMonthly = 0.0;

        $loansData = $loans->map(function (Loan $l) use (&$loansMonthly) {
            $summary     = $this->loanService->summarizeLoan($l);
            $monthly     = (float) ($summary['approx_monthly'] ?? 0);
            $loansMonthly += $monthly;

            return [
                'id'          => $l->id,
                'name'        => $l->name ?: ($l->lender_name ?? 'Loan'),
                'monthly_fmt' => $summary['approx_monthly_formatted'],
                'cadence'     => $summary['cadence_label'],
                'overdue'     => false,
            ];
        })->values();

        // ── Rentals ──────────────────────────────────────────────────────────
        $rentals = Rental::where('business_id', $business->id)
            ->latest()
            ->get();

        $rentalsMonthly = 0.0;
        $rentalsOverdue = 0;
        $recurringTypes = Rental::recurringTypes();

        $rentalsData = $rentals->map(function (Rental $r) use (&$rentalsMonthly, &$rentalsOverdue, $recurringTypes) {
            $isOverdue = $this->rentalService->rentalHasOverduePayments($r);
            if ($isOverdue) {
                $rentalsOverdue++;
            }

            $monthly = $this->rentalMonthlyEquiv((float) $r->recurring_cost, (string) $r->recurring_type);
            $rentalsMonthly += $monthly;

            return [
                'id'         => $r->id,
                'name'       => $r->property_type,
                'cost_fmt'   => number_format((float) $r->recurring_cost, 2, '.', ','),
                'cadence'    => $recurringTypes[$r->recurring_type] ?? $r->recurring_type,
                'overdue'    => $isOverdue,
            ];
        })->values();

        $totalMonthly = $billsMonthly + $loansMonthly + $rentalsMonthly;

        return response()->json([
            'business_name' => $business->name,
            'bills'   => $billsData,
            'loans'   => $loansData,
            'rentals' => $rentalsData,
            'summary' => [
                'bills_count'         => $bills->count(),
                'bills_overdue'       => $billsOverdue,
                'bills_monthly_fmt'   => number_format($billsMonthly,   2, '.', ','),
                'loans_count'         => $loans->count(),
                'loans_monthly_fmt'   => number_format($loansMonthly,   2, '.', ','),
                'rentals_count'       => $rentals->count(),
                'rentals_overdue'     => $rentalsOverdue,
                'rentals_monthly_fmt' => number_format($rentalsMonthly, 2, '.', ','),
                'total_monthly_fmt'   => number_format($totalMonthly,   2, '.', ','),
                'total_items'         => $bills->count() + $loans->count() + $rentals->count(),
            ],
        ]);
    }

    private function billMonthlyEquiv(float $cost, string $type): float
    {
        return match ($type) {
            Bill::RECURRING_PER_MONTH => $cost,
            Bill::RECURRING_PER_DAY   => $cost * 30.0,
            Bill::RECURRING_PER_YEAR  => $cost / 12.0,
            default                   => $cost,
        };
    }

    private function rentalMonthlyEquiv(float $cost, string $type): float
    {
        return match ($type) {
            Rental::RECURRING_PER_MONTH => $cost,
            Rental::RECURRING_PER_DAY   => $cost * 30.0,
            Rental::RECURRING_PER_YEAR  => $cost / 12.0,
            default                     => $cost,
        };
    }
}
