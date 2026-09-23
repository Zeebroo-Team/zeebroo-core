<?php

declare(strict_types=1);

namespace Modules\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Modules\Account\Models\Bill;
use Modules\Account\Models\Loan;
use Modules\Account\Models\Rental;
use Modules\Account\Services\BillService;
use Modules\Account\Services\LoanOverviewTooltipService;
use Modules\Account\Services\RentalService;
use Modules\Business\Models\Business;

class FinanceOverviewController extends Controller
{
    public function __construct(
        private readonly BillService $billService,
        private readonly RentalService $rentalService,
        private readonly LoanOverviewTooltipService $loanService,
    ) {}

    public function index(Request $request): View
    {
        $business = Business::currentForNavbar($request->user());

        $bills = $business
            ? Bill::where('business_id', $business->id)->orderBy('name')->get()
            : collect();
        $loans = $business
            ? Loan::where('business_id', $business->id)->latest()->get()
            : collect();
        $rentals = $business
            ? Rental::where('business_id', $business->id)->latest()->get()
            : collect();

        $billsMonthly = 0.0;
        $billsOverdue = 0;
        $billNodes = $bills->map(function (Bill $b) use (&$billsMonthly, &$billsOverdue) {
            $isOverdue = $this->billService->billHasOverduePayments($b);
            if ($isOverdue) {
                $billsOverdue++;
            }

            $monthly = $b->isOneTime() ? 0.0 : $this->monthlyEquiv((float) $b->recurring_cost, (string) ($b->recurring_type ?? ''));
            $billsMonthly += $monthly;

            return [
                'label' => $b->name,
                'meta' => $b->amount_varies_by_usage
                    ? 'Varies'
                    : number_format((float) $b->recurring_cost, 2, '.', ',') . ' · ' . ($b->isOneTime() ? 'One-time' : (Bill::recurringTypes()[$b->recurring_type] ?? $b->recurring_type)),
                'overdue' => $isOverdue,
                'route' => route('account.bills.show', $b),
            ];
        })->values();

        $loansMonthly = 0.0;
        $loanNodes = $loans->map(function (Loan $l) use (&$loansMonthly) {
            $summary = $this->loanService->summarizeLoan($l);
            $loansMonthly += (float) ($summary['approx_monthly'] ?? 0);

            return [
                'label' => $l->name ?: ($l->lender_name ?? 'Loan'),
                'meta' => $summary['approx_monthly_formatted'] . ' / mo · ' . $summary['cadence_label'],
                'overdue' => false,
                'route' => route('account.loans.show', $l),
            ];
        })->values();

        $rentalsMonthly = 0.0;
        $rentalsOverdue = 0;
        $recurringTypes = Rental::recurringTypes();
        $rentalNodes = $rentals->map(function (Rental $r) use (&$rentalsMonthly, &$rentalsOverdue, $recurringTypes) {
            $isOverdue = $this->rentalService->rentalHasOverduePayments($r);
            if ($isOverdue) {
                $rentalsOverdue++;
            }

            $monthly = $this->monthlyEquiv((float) $r->recurring_cost, (string) $r->recurring_type);
            $rentalsMonthly += $monthly;

            return [
                'label' => $r->property_type,
                'meta' => number_format((float) $r->recurring_cost, 2, '.', ',') . ' · ' . ($recurringTypes[$r->recurring_type] ?? $r->recurring_type),
                'overdue' => $isOverdue,
                'route' => route('account.rentals.show', $r),
            ];
        })->values();

        $totalMonthly = $billsMonthly + $loansMonthly + $rentalsMonthly;

        $incomeNodes = [
            ['icon' => 'fa-cash-register', 'label' => 'POS Sales', 'meta' => 'Sales transactions', 'route' => Route::has('pos.sales.index') ? route('pos.sales.index') : null],
            ['icon' => 'fa-file-pen', 'label' => 'Quotations', 'meta' => 'Web panel', 'route' => Route::has('sales.quotations.index') ? route('sales.quotations.index') : null],
            ['icon' => 'fa-file-invoice', 'label' => 'Invoices', 'meta' => 'Web panel', 'route' => Route::has('sales.invoices.index') ? route('sales.invoices.index') : null],
            ['icon' => 'fa-rotate-left', 'label' => 'Credit Recovery', 'meta' => 'Outstanding credits', 'route' => null],
        ];

        return view('account::finance.index', [
            'business' => $business,
            'billNodes' => $billNodes,
            'loanNodes' => $loanNodes,
            'rentalNodes' => $rentalNodes,
            'incomeNodes' => $incomeNodes,
            'summary' => [
                'bills_count' => $bills->count(),
                'bills_overdue' => $billsOverdue,
                'loans_count' => $loans->count(),
                'rentals_count' => $rentals->count(),
                'rentals_overdue' => $rentalsOverdue,
                'total_monthly_fmt' => number_format($totalMonthly, 2, '.', ','),
                'total_items' => $bills->count() + $loans->count() + $rentals->count(),
            ],
            'currency' => $business ? (string) (get_settings('business.currency', '', $business) ?: '') : '',
        ]);
    }

    private function monthlyEquiv(float $cost, string $type): float
    {
        return match ($type) {
            'per_month' => $cost,
            'per_day' => $cost * 30.0,
            'per_year' => $cost / 12.0,
            default => $cost,
        };
    }
}
