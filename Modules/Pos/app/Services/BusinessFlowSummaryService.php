<?php

namespace Modules\Pos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Account\Models\Bill;
use Modules\Account\Models\Loan;
use Modules\Account\Models\Rental;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Sale;
use Modules\Purchase\Models\Purchase;
use Modules\Sales\Models\Invoice;
use Modules\Sales\Models\Quotation;

/**
 * Home → Business Flow (desktop). Small per-node summaries shown as cards next
 * to the Expense / Income leaves of the flow diagram. Every section degrades to
 * zeros when its module's tables are not installed.
 */
class BusinessFlowSummaryService
{
    public function build(Business $business): array
    {
        return [
            'business_name'   => $business->name,
            'salary'          => $this->salary($business),
            'modifications'   => $this->modifications($business),
            'purchase_orders' => $this->purchaseOrders($business),
            'pos_sales'       => $this->posSales($business),
            'quotations'      => $this->quotations($business),
            'invoices'        => $this->invoices($business),
            'credits'         => $this->credits($business),
            'profit'          => $this->profit($business),
        ];
    }

    /**
     * Month-to-date money in vs money out. Sales / returns / COGS follow the
     * Profit report; bill, rental and loan payments are what was actually paid
     * (ledger), and payroll is counted once its cycle for this month is finalized.
     */
    private function profit(Business $business): array
    {
        $start = now()->startOfMonth();
        $bid   = $business->id;

        // ── Money in ─────────────────────────────────────────────────────────
        $sales = $business->sales()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('sold_at', '>=', $start)
            ->pluck('total', 'id');
        $salesTotal = (float) $sales->sum();

        $returns = Schema::hasTable('pos_sale_returns')
            ? (float) DB::table('pos_sale_returns')->where('business_id', $bid)->where('returned_at', '>=', $start)->sum('total')
            : 0.0;

        $invoices = Schema::hasTable('invoices')
            ? (float) Invoice::where('business_id', $bid)
                ->where('status', Invoice::STATUS_PAID)
                ->where('issue_date', '>=', $start->toDateString())
                ->sum('total')
            : 0.0;

        // ── Money out ────────────────────────────────────────────────────────
        $cogs = $sales->isNotEmpty() && Schema::hasTable('pos_sale_items')
            ? (float) DB::table('pos_sale_items')->whereIn('pos_sale_id', $sales->keys())->sum(DB::raw('unit_cost * quantity'))
            : 0.0;

        $paid = fn (string $type) => Schema::hasTable('ledger_transactions')
            ? (float) DB::table('ledger_transactions')
                ->where('business_id', $bid)
                ->where('transactionable_type', $type)
                ->where('occurrence_date', '>=', $start)
                ->sum('amount')
            : 0.0;
        $bills   = $paid(Bill::class);
        $rentals = $paid(Rental::class);
        $loans   = $paid(Loan::class);

        $payroll = 0.0;
        if (Schema::hasTable('hr_payroll_cycles') && Schema::hasTable('hr_payroll_items')) {
            $cycleIds = DB::table('hr_payroll_cycles')
                ->where('business_id', $bid)
                ->where('status', 'finalized')
                ->where('year', $start->year)
                ->where('month', $start->month)
                ->pluck('id');
            if ($cycleIds->isNotEmpty()) {
                $payroll = (float) DB::table('hr_payroll_items')->whereIn('payroll_cycle_id', $cycleIds)->sum('gross_earnings');
            }
        }

        $income   = $salesTotal - $returns + $invoices;
        $outgoing = $cogs + $bills + $rentals + $loans + $payroll;
        $net      = $income - $outgoing;

        $r = fn (float $v) => round($v, 2);

        return [
            'period_label' => $start->format('M Y'),
            'income'   => [
                'sales'    => $r($salesTotal),
                'returns'  => $r($returns),
                'invoices' => $r($invoices),
                'total'    => $r($income),
            ],
            'outgoing' => [
                'cogs'     => $r($cogs),
                'payroll'  => $r($payroll),
                'bills'    => $r($bills),
                'rentals'  => $r($rentals),
                'loans'    => $r($loans),
                'total'    => $r($outgoing),
            ],
            'net'        => $r($net),
            'is_profit'  => $net >= 0,
            'margin'     => $income > 0 ? round(($net / $income) * 100, 1) : 0.0,
        ];
    }

    private function salary(Business $business): array
    {
        $out = ['employees' => 0, 'monthly' => 0.0, 'last_cycle_label' => null, 'last_cycle_net' => 0.0];

        if (Schema::hasTable('hr_employees')) {
            $row = DB::table('hr_employees')
                ->where('business_id', $business->id)
                ->selectRaw('COUNT(*) as cnt, SUM(COALESCE(salary, basic_salary, 0)) as cost')
                ->first();
            $out['employees'] = (int) $row->cnt;
            $out['monthly']   = round((float) $row->cost, 2);
        }

        // Latest finalized payroll — same source as PosPayrollOverviewApiController
        if (Schema::hasTable('hr_payroll_cycles') && Schema::hasTable('hr_payroll_items')) {
            $cycle = DB::table('hr_payroll_cycles')
                ->where('business_id', $business->id)
                ->where('status', 'finalized')
                ->orderByDesc('year')->orderByDesc('month')
                ->first(['id', 'name', 'year', 'month']);

            if ($cycle) {
                $out['last_cycle_label'] = $cycle->name ?? date('M Y', mktime(0, 0, 0, (int) $cycle->month, 1, (int) $cycle->year));
                $out['last_cycle_net']   = round((float) DB::table('hr_payroll_items')->where('payroll_cycle_id', $cycle->id)->sum('net_pay'), 2);
            }
        }

        return $out;
    }

    private function modifications(Business $business): array
    {
        if (! Schema::hasTable('modifications')) {
            return ['count' => 0, 'total' => 0.0];
        }

        $row = DB::table('modifications')
            ->where('business_id', $business->id)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(estimated_cost), 0) as cost')
            ->first();

        return ['count' => (int) $row->cnt, 'total' => round((float) $row->cost, 2)];
    }

    private function purchaseOrders(Business $business): array
    {
        if (! Schema::hasTable('purchases')) {
            return ['open_count' => 0, 'open_total' => 0.0, 'month_count' => 0, 'month_total' => 0.0];
        }

        $base = Purchase::where('business_id', $business->id)
            ->where('status', '!=', Purchase::STATUS_CANCELLED);

        $open = (clone $base)
            ->whereIn('status', [Purchase::STATUS_DRAFT, Purchase::STATUS_ORDERED, Purchase::STATUS_PARTIALLY_RECEIVED])
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as amt')
            ->first();

        $month = (clone $base)
            ->where('purchase_date', '>=', now()->startOfMonth()->toDateString())
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as amt')
            ->first();

        return [
            'open_count'  => (int) $open->cnt,
            'open_total'  => round((float) $open->amt, 2),
            'month_count' => (int) $month->cnt,
            'month_total' => round((float) $month->amt, 2),
        ];
    }

    private function posSales(Business $business): array
    {
        $base = $business->sales()->where('status', Sale::STATUS_COMPLETED);

        $today = (clone $base)->where('sold_at', '>=', now()->startOfDay())
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as amt')
            ->first();

        $month = (clone $base)->where('sold_at', '>=', now()->startOfMonth())
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as amt')
            ->first();

        return [
            'today_count' => (int) $today->cnt,
            'today_total' => round((float) $today->amt, 2),
            'month_count' => (int) $month->cnt,
            'month_total' => round((float) $month->amt, 2),
        ];
    }

    private function quotations(Business $business): array
    {
        if (! Schema::hasTable('quotations')) {
            return ['open_count' => 0, 'open_total' => 0.0, 'accepted_count' => 0];
        }

        $open = Quotation::where('business_id', $business->id)
            ->whereIn('status', [Quotation::STATUS_DRAFT, Quotation::STATUS_SENT])
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as amt')
            ->first();

        return [
            'open_count'     => (int) $open->cnt,
            'open_total'     => round((float) $open->amt, 2),
            'accepted_count' => Quotation::where('business_id', $business->id)->where('status', Quotation::STATUS_ACCEPTED)->count(),
        ];
    }

    private function invoices(Business $business): array
    {
        if (! Schema::hasTable('invoices')) {
            return ['unpaid_count' => 0, 'unpaid_total' => 0.0, 'overdue_count' => 0];
        }

        $unpaid = Invoice::where('business_id', $business->id)
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_OVERDUE])
            ->get(['status', 'due_date', 'total']);

        $today = now()->toDateString();

        return [
            'unpaid_count'  => $unpaid->count(),
            'unpaid_total'  => round((float) $unpaid->sum('total'), 2),
            'overdue_count' => $unpaid->filter(fn ($i) => $i->status === Invoice::STATUS_OVERDUE
                || ($i->due_date && (string) $i->due_date < $today))->count(),
        ];
    }

    /** Outstanding credit sales — same filter as PosSaleApiController@pendingCredits. */
    private function credits(Business $business): array
    {
        $sales = $business->sales()
            ->where('payment_method', Sale::PAYMENT_CREDIT)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereRaw('total - amount_paid > 0.005')
            ->get(['id', 'payment_method', 'status', 'total', 'amount_paid', 'credit_due_date']);

        $overdue = $sales->filter(fn (Sale $s) => $s->isCreditOverdue());

        return [
            'count'         => $sales->count(),
            'total'         => round((float) $sales->sum(fn (Sale $s) => $s->creditDue()), 2),
            'overdue_count' => $overdue->count(),
            'overdue_total' => round((float) $overdue->sum(fn (Sale $s) => $s->creditDue()), 2),
        ];
    }
}
