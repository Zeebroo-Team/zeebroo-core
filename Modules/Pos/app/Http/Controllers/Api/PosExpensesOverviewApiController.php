<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Account\Models\Bill;
use Modules\Account\Models\Rental;
use Modules\Modification\Models\Modification;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Transaction\Models\LedgerTransaction;

class PosExpensesOverviewApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function show(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        // ── Bills ─────────────────────────────────────────────────────────────
        $bills = [];
        $billsMonthly  = 0.0;
        $overdueCount  = 0;
        $categoryTotals = [];

        if (Schema::hasTable('bills')) {
            $billRows = Bill::where('business_id', $business->id)
                ->with('ledgerTransactions')
                ->orderBy('name')
                ->get();

            foreach ($billRows as $b) {
                $paid    = $b->ledgerTransactions->sum('amount');
                $amount  = (float) ($b->recurring_cost ?? 0);
                $isOverdue   = $amount > 0 && (float) $paid < $amount && $b->due_date && $b->due_date->isPast();
                $isFullyPaid = $amount > 0 && (float) $paid >= $amount;
                $dueDate = $b->due_date ?? $b->first_installment_due_date;
                $cat     = $b->bill_category ?? Bill::CATEGORY_OTHER;

                $bills[] = [
                    'id'             => $b->id,
                    'name'           => $b->name,
                    'amount'         => $amount,
                    'category'       => $cat,
                    'category_label' => $b->categoryDisplayLabel(),
                    'payment_mode'   => $b->payment_mode ?? Bill::PAYMENT_MODE_RECURRING,
                    'recurring_type' => $b->recurring_type ?? '',
                    'due_date_fmt'   => $dueDate?->format('M j, Y') ?? '',
                    'overdue'        => $isOverdue,
                    'fully_paid'     => $isFullyPaid,
                    'amount_varies'  => (bool) $b->amount_varies_by_usage,
                    'paid_total'     => round((float) $paid, 2),
                    'notes'          => $b->notes ?? '',
                ];

                if ($isOverdue) {
                    $overdueCount++;
                }

                if (! $isFullyPaid) {
                    $billsMonthly += $amount;
                    $categoryTotals[$cat] = ($categoryTotals[$cat] ?? 0.0) + $amount;
                }
            }
        }

        // ── Rentals ───────────────────────────────────────────────────────────
        $rentals = [];
        $rentalsMonthly = 0.0;

        if (Schema::hasTable('rentals')) {
            $rentalRows = Rental::where('business_id', $business->id)
                ->orderBy('property_type')
                ->get(['id', 'property_type', 'purpose', 'recurring_cost']);

            foreach ($rentalRows as $r) {
                $amount = (float) ($r->recurring_cost ?? 0);
                $rentals[] = [
                    'id'     => $r->id,
                    'name'   => $r->property_type . ($r->purpose ? ' · ' . $r->purpose : ''),
                    'amount' => $amount,
                ];
                $rentalsMonthly += $amount;
            }
        }

        // ── Modifications ─────────────────────────────────────────────────────
        $modifications = [];
        $modsTotal = 0.0;

        if (Schema::hasTable('modifications')) {
            $modRows = Modification::where('business_id', $business->id)
                ->orderBy('name')
                ->get(['id', 'name', 'estimated_cost']);

            foreach ($modRows as $m) {
                $amount = (float) ($m->estimated_cost ?? 0);
                $modifications[] = [
                    'id'     => $m->id,
                    'name'   => $m->name,
                    'amount' => $amount,
                ];
                $modsTotal += $amount;
            }
        }

        // ── Recent payments + monthly trend ────────────────────────────────────
        $recentPayments = [];
        $monthlyTrend   = ['months' => [], 'bills' => [], 'rentals' => []];

        if (Schema::hasTable('ledger_transactions')) {
            // Build 12-month slot keys
            $slotKeys   = [];
            $slotLabels = [];
            for ($i = 11; $i >= 0; $i--) {
                $m            = now()->startOfMonth()->subMonths($i);
                $key          = $m->format('Y-m');
                $slotKeys[]   = $key;
                $slotLabels[] = $m->format('M y');
            }

            $windowStart = now()->startOfMonth()->subMonths(11);

            // All expense transactions in window
            $allTxns = LedgerTransaction::where('business_id', $business->id)
                ->whereIn('transactionable_type', [Bill::class, Rental::class])
                ->where('occurrence_date', '>=', $windowStart)
                ->with('transactionable')
                ->orderByDesc('occurrence_date')
                ->orderByDesc('id')
                ->get();

            // Monthly trend buckets
            $trendBuckets = array_fill_keys($slotKeys, ['bills' => 0.0, 'rentals' => 0.0]);
            foreach ($allTxns as $t) {
                $key = $t->occurrence_date?->format('Y-m');
                if ($key && isset($trendBuckets[$key])) {
                    if ($t->transactionable_type === Bill::class) {
                        $trendBuckets[$key]['bills'] += (float) $t->amount;
                    } else {
                        $trendBuckets[$key]['rentals'] += (float) $t->amount;
                    }
                }
            }

            $monthlyTrend = [
                'months'  => $slotLabels,
                'bills'   => array_map(fn ($k) => round($trendBuckets[$k]['bills'], 2),   $slotKeys),
                'rentals' => array_map(fn ($k) => round($trendBuckets[$k]['rentals'], 2), $slotKeys),
            ];

            // Recent payments (last 15, all time)
            foreach ($allTxns->take(15) as $t) {
                $recentPayments[] = [
                    'id'           => $t->id,
                    'amount'       => round((float) $t->amount, 2),
                    'date_fmt'     => $t->occurrence_date?->format('M j, Y') ?? '',
                    'source_label' => $t->sourceKindLabel(),
                    'source_title' => $t->sourceTitle(),
                ];
            }
        }

        // Build category breakdown as sorted array
        arsort($categoryTotals);
        $breakdown = [];
        foreach ($categoryTotals as $cat => $total) {
            $breakdown[] = ['category' => $cat, 'total' => round($total, 2)];
        }

        return response()->json([
            'data' => [
                'summary' => [
                    'bills_monthly'   => round($billsMonthly, 2),
                    'bills_count'     => count($bills),
                    'overdue_count'   => $overdueCount,
                    'rentals_monthly' => round($rentalsMonthly, 2),
                    'mods_total'      => round($modsTotal, 2),
                    'total_monthly'   => round($billsMonthly + $rentalsMonthly, 2),
                ],
                'category_breakdown' => $breakdown,
                'monthly_trend'      => $monthlyTrend,
                'bills'              => $bills,
                'rentals'            => $rentals,
                'modifications'      => $modifications,
                'recent_payments'    => $recentPayments,
            ],
        ]);
    }
}
