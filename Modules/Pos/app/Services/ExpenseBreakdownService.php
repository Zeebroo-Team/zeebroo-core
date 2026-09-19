<?php

namespace Modules\Pos\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Account\Models\Bill;
use Modules\Account\Models\Loan;
use Modules\Account\Models\Rental;
use Modules\Account\Services\BillService;
use Modules\Account\Services\LoanOverviewTooltipService;
use Modules\Account\Services\RentalService;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\PayrollCycle;
use Modules\Modification\Models\Modification;
use Modules\Purchase\Models\GoodsReceiveNote;
use Modules\Purchase\Models\Purchase;
use Modules\Transaction\Models\LedgerTransaction;

/**
 * Expenses per type over a rolling window of calendar months.
 *
 * Bills, rents and loan installments are counted from each one's own billing
 * schedule (the same due dates, paid / partly-paid / "paid elsewhere" status and
 * lease/agreement end the Bills, Rentals and Loans screens use), so every
 * installment falling due in the window counts — paid or not — and the chart
 * always reconciles with those screens. On top of that, money paid in the window
 * for an installment dated outside it (an overdue one settled today, or one paid
 * in advance) counts as paid, because the ledger files a payment under the
 * installment's date, not the day it was paid.
 *
 * A modification is its tied bills' schedule when it has any, otherwise its
 * estimate (as due) in the window it was created. Payroll and goods-receipt
 * payments have no schedule, so they are paid-only, by the day recorded. POS
 * sales are income and are excluded.
 */
class ExpenseBreakdownService
{
    public const PERIOD_MONTH = 'month';

    public const PERIOD_HALF_YEAR = '6m';

    public const PERIOD_YEAR = 'year';

    public const PERIODS = [self::PERIOD_MONTH, self::PERIOD_HALF_YEAR, self::PERIOD_YEAR];

    /** Display order — also the order slices appear in the chart. */
    private const KIND_LABELS = [
        'bills' => 'Bills',
        'loans' => 'Loans',
        'rentals' => 'Rentals',
        'modifications' => 'Modifications',
        'payroll' => 'Payroll',
        'purchases' => 'Purchases',
    ];

    /** Ledger source class => kind (bills tied to a modification are refined below). */
    private const KIND_BY_TYPE = [
        Bill::class => 'bills',
        Loan::class => 'loans',
        Rental::class => 'rentals',
        PayrollCycle::class => 'payroll',
        GoodsReceiveNote::class => 'purchases',
        Purchase::class => 'purchases',
    ];

    private const PAYMENTS_LIMIT = 15;

    public function __construct(
        private readonly LoanOverviewTooltipService $loans,
        private readonly RentalService $rentals,
        private readonly BillService $bills,
    ) {}

    /**
     * @return array{period: string, from: string, to: string, range_label: string, total: float, items: list<array{key: string, label: string, total: float, paid: float, due: float, count: int}>, payments: list<array<string, mixed>>}
     */
    public function forBusiness(Business $business, string $period): array
    {
        [$from, $to] = $this->windowFor($period);

        $kinds = array_fill_keys(array_keys(self::KIND_LABELS), ['total' => 0.0, 'paid' => 0.0, 'count' => 0]);

        $add = function (string $kind, float $paid, float $due) use (&$kinds): void {
            if ($paid + $due <= 0) {
                return;
            }
            $kinds[$kind]['total'] += $paid + $due;
            $kinds[$kind]['paid'] += $paid;
            $kinds[$kind]['count']++;
        };

        $seen = [];
        $modificationBills = [];
        $modificationOfBill = [];

        $billModels = Bill::query()->where('business_id', $business->id)->with('ledgerTransactions')->get();
        foreach ($billModels as $bill) {
            $seen[Bill::class.'#'.$bill->id] = true;
            $this->ensureScheduleAnchor($bill);

            $paid = 0.0;
            $due = 0.0;
            $slotDates = [];
            foreach ($this->bills->billBillingScheduleWithPaymentStatus($bill) as $slot) {
                if (! $slot['due']->between($from, $to)) {
                    continue;
                }
                $slotDates[] = $slot['due']->toDateString();
                $slotPaid = (float) $slot['paid_total'];
                $paid += $slotPaid;
                $due += $slot['outstanding_raw'] !== null
                    ? (float) $slot['outstanding_raw']
                    : max(0.0, (float) $slot['amount'] - $slotPaid);
            }
            $paid += $this->paidOffSchedule($bill->ledgerTransactions, $slotDates, $from, $to);

            if ($bill->modification_id !== null) {
                $modificationOfBill[$bill->id] = $bill->modification_id;
                $modificationBills[$bill->modification_id][] = [$paid, $due];

                continue;
            }

            $add('bills', $paid, $due);
        }

        foreach (Modification::query()->where('business_id', $business->id)->get(['id', 'estimated_cost', 'created_at']) as $modification) {
            $tied = $modificationBills[$modification->id] ?? [];
            unset($modificationBills[$modification->id]);

            if ($tied !== []) {
                $add('modifications', array_sum(array_column($tied, 0)), array_sum(array_column($tied, 1)));

                continue;
            }

            $planned = $modification->created_at?->between($from, $to) ? (float) $modification->estimated_cost : 0.0;
            $add('modifications', 0.0, $planned);
        }

        // Bills tied to a modification that no longer exists.
        foreach ($modificationBills as $tied) {
            $add('modifications', array_sum(array_column($tied, 0)), array_sum(array_column($tied, 1)));
        }

        $rentalModels = Rental::query()->where('business_id', $business->id)->with(['ledgerTransactions', 'externalBillingMarks'])->get();
        foreach ($rentalModels as $rental) {
            $seen[Rental::class.'#'.$rental->id] = true;
            $this->ensureScheduleAnchor($rental);

            $paid = 0.0;
            $due = 0.0;
            $slotDates = [];
            foreach ($this->rentals->rentalBillingScheduleWithPaymentStatus($rental) as $slot) {
                if (! $slot['due']->between($from, $to)) {
                    continue;
                }
                $slotDates[] = $slot['due']->toDateString();
                $slot['paid'] ? $paid += (float) $slot['amount'] : $due += (float) $slot['amount'];
            }
            $paid += $this->paidOffSchedule($rental->ledgerTransactions, $slotDates, $from, $to);

            $add('rentals', $paid, $due);
        }

        $loanModels = Loan::query()->where('business_id', $business->id)->with(['ledgerTransactions', 'externalInstallmentMarks'])->get();
        foreach ($loanModels as $loan) {
            $seen[Loan::class.'#'.$loan->id] = true;
            if ($loan->first_installment_due_date === null && $loan->created_at !== null) {
                $loan->first_installment_due_date = $loan->created_at->copy()->startOfDay();
            }

            $perInstallment = (float) ($this->loans->summarizeLoan($loan)['payment_per_period'] ?? 0);
            $paid = 0.0;
            $due = 0.0;
            $slotDates = [];
            foreach ($this->loans->installmentScheduleDates($loan) as $date) {
                if (! $date->between($from, $to)) {
                    continue;
                }
                $slotDates[] = $date->toDateString();
                $this->loans->loanInstallmentSatisfied($loan, $date) ? $paid += $perInstallment : $due += $perInstallment;
            }
            $paid += $this->paidOffSchedule($loan->ledgerTransactions, $slotDates, $from, $to);

            $add('loans', $paid, $due);
        }

        // Payroll, goods receipts, and payments whose bill/loan/rental was since deleted.
        foreach ($this->recordedByRecord($business, $from, $to) as $key => $row) {
            if (! isset($seen[$key])) {
                $add(self::KIND_BY_TYPE[$row['type']], $row['total'], 0.0);
            }
        }

        $items = [];
        foreach (self::KIND_LABELS as $key => $label) {
            $items[] = [
                'key' => $key,
                'label' => $label,
                'total' => round($kinds[$key]['total'], 2),
                'paid' => round($kinds[$key]['paid'], 2),
                'due' => round($kinds[$key]['total'] - $kinds[$key]['paid'], 2),
                'count' => $kinds[$key]['count'],
            ];
        }

        $payments = LedgerTransaction::query()
            ->where('business_id', $business->id)
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('transactionable_type', array_keys(self::KIND_BY_TYPE))
            ->with('transactionable')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PAYMENTS_LIMIT)
            ->get()
            ->map(fn (LedgerTransaction $t) => $this->formatPayment($t, array_keys($modificationOfBill)))
            ->all();

        return [
            'period' => $period,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'range_label' => $this->rangeLabel($from, $to),
            'total' => round(array_sum(array_column($items, 'total')), 2),
            'items' => $items,
            'payments' => $payments,
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} calendar-month aligned, ending with the current month */
    private function windowFor(string $period): array
    {
        $monthsBack = match ($period) {
            self::PERIOD_HALF_YEAR => 5,
            self::PERIOD_YEAR => 11,
            default => 0,
        };

        return [
            now()->startOfMonth()->subMonths($monthsBack),
            now()->endOfMonth(),
        ];
    }

    /**
     * A bill or rent registered with neither a due date nor a first-installment date
     * has no billing schedule of its own, which would hide its cost entirely. Bill it
     * from the day it was registered (in memory only — never saved).
     */
    private function ensureScheduleAnchor(Bill|Rental $model): void
    {
        if ($model->due_date === null && $model->first_installment_due_date === null && $model->created_at !== null) {
            $model->due_date = $model->created_at->copy()->startOfDay();
        }
    }

    /**
     * Money recorded in the window for installments that are not among the
     * window's own scheduled dates (settled late, or paid in advance).
     *
     * @param  Collection<int, LedgerTransaction>  $ledgerRows
     * @param  list<string>  $slotDates  Y-m-d due dates of the installments counted in the window
     */
    private function paidOffSchedule(Collection $ledgerRows, array $slotDates, Carbon $from, Carbon $to): float
    {
        return (float) $ledgerRows
            ->filter(fn (LedgerTransaction $t) => $t->created_at?->between($from, $to)
                && ! in_array($t->occurrence_date?->toDateString(), $slotDates, true))
            ->sum(fn (LedgerTransaction $t) => (float) $t->amount);
    }

    /**
     * Ledger totals per source record recorded in the window.
     *
     * @return array<string, array{type: string, total: float}> keyed "Class#id"
     */
    private function recordedByRecord(Business $business, Carbon $from, Carbon $to): array
    {
        $grouped = LedgerTransaction::query()
            ->where('business_id', $business->id)
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('transactionable_type', array_keys(self::KIND_BY_TYPE))
            ->selectRaw('transactionable_type, transactionable_id, SUM(amount) as total')
            ->groupBy('transactionable_type', 'transactionable_id')
            ->get();

        $rows = [];
        foreach ($grouped as $row) {
            $rows[$row->transactionable_type.'#'.$row->transactionable_id] = [
                'type' => $row->transactionable_type,
                'total' => (float) $row->total,
            ];
        }

        return $rows;
    }

    private function rangeLabel(Carbon $from, Carbon $to): string
    {
        if ($from->isSameMonth($to)) {
            return $to->format('M Y');
        }

        return $from->isSameYear($to)
            ? $from->format('M').' – '.$to->format('M Y')
            : $from->format('M Y').' – '.$to->format('M Y');
    }

    /** @param  list<int|string>  $modificationBillIds */
    private function formatPayment(LedgerTransaction $t, array $modificationBillIds): array
    {
        $kind = self::KIND_BY_TYPE[$t->transactionable_type] ?? 'bills';
        if ($kind === 'bills' && in_array($t->transactionable_id, $modificationBillIds, false)) {
            $kind = 'modifications';
        }

        return [
            'id' => $t->id,
            'amount' => round((float) $t->amount, 2),
            'date_fmt' => $t->created_at?->format('M j, Y') ?? '',
            'kind' => $kind,
            'source_label' => self::KIND_LABELS[$kind],
            'source_title' => $t->sourceTitle(),
        ];
    }
}
