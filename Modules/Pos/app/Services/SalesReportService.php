<?php

namespace Modules\Pos\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Sale;

/**
 * Finance → Sales Reports.
 *
 * Combines every income stream into one filterable report:
 *   - POS sales        (pos_sales, by sold_at)        → realised revenue
 *   - Invoices         (invoices, by issue_date)      → paid = realised revenue, sent/overdue = receivables
 *   - Quotations       (quotations, by quote_date)    → pipeline / win rate (accepted = won)
 *   - Sales orders     (sales_orders, by order_date)  → order book
 *   - Sale returns     (pos_sale_returns)             → deducted from net sales
 *
 * Invoices / quotations / sales orders never create POS sales, so summing POS + paid
 * invoices does not double count.
 *
 * Filters: date range, branch / warehouse (`branches` table; 'none' = unassigned),
 * counter (POS-only — other documents have no counter, so they drop out when set).
 * Aggregation runs in PHP so it works the same on SQLite and MySQL.
 */
class SalesReportService
{
    public const SOURCES = ['pos', 'invoices', 'quotations', 'sales_orders'];

    private const OPEN_ORDER_STATUSES = ['pending', 'confirmed', 'processing'];

    /** @var array<string, bool> */
    private array $tables = [];

    /**
     * @param  array{from: Carbon, to: Carbon, branch: int|string|null, counter: int|null, group_by: ?string,
     *               txn_source: ?string, txn_status: ?string, q: ?string, page: int, per_page: int}  $f
     */
    public function build(Business $business, array $f): array
    {
        $from    = $f['from']->copy()->startOfDay();
        $to      = $f['to']->copy()->endOfDay();
        $days    = (int) $from->diffInDays($to->copy()->startOfDay()) + 1;
        $groupBy = in_array($f['group_by'] ?? null, ['day', 'week', 'month'], true)
            ? $f['group_by']
            : ($days <= 31 ? 'day' : ($days <= 184 ? 'week' : 'month'));

        // A counter only exists on POS sales — documents are excluded when one is picked.
        $posOnly = $f['counter'] !== null;

        [$buckets, $bucketKey] = $this->makeBuckets($from, $to, $groupBy);

        $branchNames = $this->branchNames($business);

        // ── POS sales ─────────────────────────────────────────────────────────
        $pos = $this->posSales($business, $from, $to, $f['branch'], $f['counter']);

        $posCompleted = $pos->where('status', Sale::STATUS_COMPLETED);
        $posVoid      = $pos->where('status', Sale::STATUS_VOID);

        $posTotal     = (float) $posCompleted->sum('total');
        $posDiscounts = (float) $posCompleted->sum(fn ($s) => $this->saleDiscount($s));
        $creditOpen   = (float) $posCompleted
            ->filter(fn ($s) => $s->payment_method === Sale::PAYMENT_CREDIT && ! $s->is_settled)
            ->sum(fn ($s) => max(0, (float) $s->total - (float) $s->amount_paid));

        // Per-sale fan-out: trend, payment mix, branch, cashier, counter, channel, heatmap, customers.
        $byPayment  = [];
        $byBranch   = [];
        $byCashier  = [];
        $byCounter  = [];
        $byChannel  = [];
        $byCustomer = [];
        $heatmap    = array_fill(0, 7, array_fill(0, 24, 0.0));
        $heatCount  = array_fill(0, 7, array_fill(0, 24, 0));
        $byDate     = [];
        $discountedCount = 0;
        $walkinCount     = 0;
        $largestSale     = 0.0;

        foreach ($posCompleted as $s) {
            $amt  = (float) $s->total;
            $when = Carbon::parse($s->sold_at);

            if ($this->saleDiscount($s) > 0) {
                $discountedCount++;
            }
            if ($s->pos_customer_id === null) {
                $walkinCount++;
            }
            $largestSale = max($largestSale, $amt);
            $byDate[$when->toDateString()] = ($byDate[$when->toDateString()] ?? 0.0) + $amt;
            $this->bump($byChannel, (string) ($s->channel ?? Sale::CHANNEL_RETAIL), $amt);
            $heatCount[$when->dayOfWeekIso - 1][(int) $when->format('G')]++;

            $k = $bucketKey($when);
            if (isset($buckets[$k])) {
                $buckets[$k]['pos'] += $amt;
                $buckets[$k]['pos_count']++;
            }

            // Split tender: the gift card part and the rest each count as one payment
            $gift = min($amt, (float) ($s->gift_card_amount ?? 0));
            if ($gift > 0) {
                $this->bump($byPayment, 'gift_card', $gift);
            }
            if ($amt - $gift > 0 || $gift <= 0) {
                $this->bump($byPayment, (string) $s->payment_method, $amt - $gift);
            }

            $this->bump($byBranch, $s->branch_id === null ? 'none' : (string) $s->branch_id, $amt);
            $this->bump($byCashier, (string) $s->user_id, $amt);
            $this->bump($byCounter, $s->pos_counter_id === null ? 'none' : (string) $s->pos_counter_id, $amt);
            $this->bump($byCustomer, $s->pos_customer_id === null ? 'walkin' : (string) $s->pos_customer_id, $amt);

            $heatmap[$when->dayOfWeekIso - 1][(int) $when->format('G')] += $amt;
        }

        // ── POS line items (COGS, units, top products) ───────────────────────
        $items = $this->posItemTotals($business, $from, $to, $f['branch'], $f['counter']);

        $byCategory = $this->posCategoryTotals($business, $from, $to, $f['branch'], $f['counter']);

        // ── Returns ───────────────────────────────────────────────────────────
        [$returnsTotal, $returnsCount, $returnsDetail] = $this->returns($business, $from, $to, $f['branch'], $f['counter'], $buckets, $bucketKey);

        // ── Invoices ──────────────────────────────────────────────────────────
        $invoices = $posOnly ? collect() : $this->documents('invoices', 'issue_date', $business, $from, $to, $f['branch'], ['payment_method']);

        $invPaid       = $invoices->where('status', 'paid');
        $invPaidTotal  = (float) $invPaid->sum('total');
        $receivables   = (float) $invoices->whereIn('status', ['sent', 'overdue'])->sum('total');
        $overdueTotal  = (float) $invoices->where('status', 'overdue')->sum('total');

        foreach ($invPaid as $inv) {
            $amt  = (float) $inv->total;
            $when = Carbon::parse($inv->issue_date);
            $byDate[$when->toDateString()] = ($byDate[$when->toDateString()] ?? 0.0) + $amt;
            $k    = $bucketKey($when);
            if (isset($buckets[$k])) {
                $buckets[$k]['invoices'] += $amt;
            }
            $this->bump($byPayment, (string) ($inv->payment_method ?: 'invoice'), $amt);
            $this->bump($byBranch, $inv->branch_id === null ? 'none' : (string) $inv->branch_id, $amt);
            $this->bump($byCustomer, $inv->customer_id === null ? 'walkin' : (string) $inv->customer_id, $amt);
        }

        $invoiceItems = $posOnly ? [] : $this->invoiceItemTotals($business, $from, $to, $f['branch']);

        // ── Quotations ────────────────────────────────────────────────────────
        $quotes = $posOnly ? collect() : $this->documents('quotations', 'quote_date', $business, $from, $to, $f['branch']);

        foreach ($quotes->where('status', 'accepted') as $q) {
            $k = $bucketKey(Carbon::parse($q->quote_date));
            if (isset($buckets[$k])) {
                $buckets[$k]['quotes_accepted'] += (float) $q->total;
            }
        }

        $qAccepted = $quotes->where('status', 'accepted');
        $qDecided  = $quotes->whereIn('status', ['accepted', 'rejected', 'expired'])->count();

        // ── Sales orders ──────────────────────────────────────────────────────
        $orders = $posOnly ? collect() : $this->documents('sales_orders', 'order_date', $business, $from, $to, $f['branch']);

        // ── Summary ───────────────────────────────────────────────────────────
        $revenue      = $posTotal + $invPaidTotal;
        $netSales     = $revenue - $returnsTotal;
        $txCount      = $posCompleted->count() + $invPaid->count();
        // POS only — invoice lines carry no unit cost. Covers lines whose cost is known,
        // after their share of the bill discount; lines without a cost price are
        // reported as uncosted instead of counting as 100% margin.
        $costedSales   = $items['costed_revenue'];
        $grossProfit   = $costedSales - $items['cogs'];
        $uncostedSales = max(0.0, $posTotal - $costedSales);

        $prev = $this->previousPeriod($business, $from, $days, $f['branch'], $f['counter'], $posOnly);

        $customers = $this->customerInsights($business, $from, array_keys($byCustomer));

        arsort($byDate);
        $bestDay = $byDate === [] ? null : ['date' => array_key_first($byDate), 'total' => round(reset($byDate), 2)];

        $summary = [
            'revenue'            => round($revenue, 2),
            // Before order / coupon discounts — POS discounts added back on top of revenue
            'gross_sales'        => round($revenue + $posDiscounts, 2),
            'net_sales'          => round($netSales, 2),
            'discounted_count'   => $discountedCount,
            'discount_rate'      => $revenue + $posDiscounts > 0 ? round($posDiscounts / ($revenue + $posDiscounts) * 100, 1) : 0.0,
            'return_rate'        => $revenue > 0 ? round($returnsTotal / $revenue * 100, 1) : 0.0,
            'avg_items_per_sale' => $posCompleted->count() > 0 ? round($items['qty'] / $posCompleted->count(), 2) : 0.0,
            'avg_daily_revenue'  => round($revenue / max(1, $days), 2),
            'largest_sale'       => round($largestSale, 2),
            'best_day'           => $bestDay,
            'active_days'        => count($byDate),
            'walkin_count'       => $walkinCount,
            'unique_customers'   => $customers['unique'],
            'new_customers'      => $customers['new'],
            'returning_customers'=> $customers['returning'],
            'products_sold'      => count($items['products']),
            'transactions'       => $txCount,
            'avg_ticket'         => $txCount > 0 ? round($revenue / $txCount, 2) : 0.0,
            'items_sold'         => round($items['qty'], 3),
            'discounts'          => round($posDiscounts, 2),
            'returns_total'      => round($returnsTotal, 2),
            'returns_count'      => $returnsCount,
            'cogs'               => round($items['cogs'], 2),
            'gross_profit'       => round($grossProfit, 2),
            'gross_margin'       => $costedSales > 0 ? round($grossProfit / $costedSales * 100, 1) : 0.0,
            'costed_sales'       => round($costedSales, 2),
            'uncosted_sales'     => round($uncostedSales, 2),
            'uncosted_lines'     => $items['uncosted_lines'],
            'below_cost_lines'   => $items['below_cost_lines'],
            'below_cost_loss'    => round($items['below_cost_loss'], 2),
            'pos_total'          => round($posTotal, 2),
            'pos_count'          => $posCompleted->count(),
            'void_total'         => round((float) $posVoid->sum('total'), 2),
            'void_count'         => $posVoid->count(),
            'credit_outstanding' => round($creditOpen, 2),
            'invoices_paid_total'  => round($invPaidTotal, 2),
            'invoices_paid_count'  => $invPaid->count(),
            'receivables'          => round($receivables, 2),
            'overdue_total'        => round($overdueTotal, 2),
            'prev_revenue'         => round($prev['revenue'], 2),
            'prev_transactions'    => $prev['count'],
            'revenue_change_pct'   => $this->changePct($revenue, $prev['revenue']),
            'transactions_change_pct' => $this->changePct($txCount, $prev['count']),
            'quotations' => [
                'count'          => $quotes->count(),
                'total'          => round((float) $quotes->sum('total'), 2),
                'accepted_count' => $qAccepted->count(),
                'accepted_total' => round((float) $qAccepted->sum('total'), 2),
                'open_count'     => $quotes->whereIn('status', ['draft', 'sent'])->count(),
                'open_total'     => round((float) $quotes->whereIn('status', ['draft', 'sent'])->sum('total'), 2),
                'rejected_count' => $quotes->where('status', 'rejected')->count(),
                'expired_count'  => $quotes->where('status', 'expired')->count(),
                'win_rate'       => $qDecided > 0 ? round($qAccepted->count() / $qDecided * 100, 1) : null,
            ],
            'sales_orders' => [
                'count'           => $orders->count(),
                'completed_count' => $orders->where('status', 'completed')->count(),
                'completed_total' => round((float) $orders->where('status', 'completed')->sum('total'), 2),
                'open_count'      => $orders->whereIn('status', self::OPEN_ORDER_STATUSES)->count(),
                'open_total'      => round((float) $orders->whereIn('status', self::OPEN_ORDER_STATUSES)->sum('total'), 2),
                'cancelled_count' => $orders->where('status', 'cancelled')->count(),
            ],
            'invoices' => [
                'count'          => $invoices->count(),
                'paid_count'     => $invPaid->count(),
                'sent_count'     => $invoices->where('status', 'sent')->count(),
                'overdue_count'  => $invoices->where('status', 'overdue')->count(),
                'draft_count'    => $invoices->where('status', 'draft')->count(),
                'cancelled_count'=> $invoices->where('status', 'cancelled')->count(),
            ],
        ];

        $bucketValues = array_values($buckets);

        return [
            'range' => [
                'from'     => $from->toDateString(),
                'to'       => $to->toDateString(),
                'days'     => $days,
                'group_by' => $groupBy,
                'prev_from'=> $from->copy()->subDays($days)->toDateString(),
                'prev_to'  => $from->copy()->subDay()->toDateString(),
            ],
            'filters' => $this->filterOptions($business, $branchNames, $posOnly),
            'summary' => $summary,
            'trend' => [
                'labels'          => array_column($bucketValues, 'label'),
                'pos'             => array_map(fn ($b) => round($b['pos'], 2), $bucketValues),
                'invoices'        => array_map(fn ($b) => round($b['invoices'], 2), $bucketValues),
                'returns'         => array_map(fn ($b) => round($b['returns'], 2), $bucketValues),
                'quotes_accepted' => array_map(fn ($b) => round($b['quotes_accepted'], 2), $bucketValues),
                'revenue'         => array_map(fn ($b) => round($b['pos'] + $b['invoices'], 2), $bucketValues),
                'transactions'    => array_map(fn ($b) => $b['pos_count'], $bucketValues),
            ],
            'by_source'   => [
                ['key' => 'pos',          'label' => 'POS sales',        'count' => $posCompleted->count(),  'total' => round($posTotal, 2)],
                ['key' => 'invoices',     'label' => 'Paid invoices',    'count' => $invPaid->count(),       'total' => round($invPaidTotal, 2)],
                ['key' => 'quotations',   'label' => 'Accepted quotes',  'count' => $qAccepted->count(),     'total' => round((float) $qAccepted->sum('total'), 2)],
                ['key' => 'sales_orders', 'label' => 'Completed orders', 'count' => $summary['sales_orders']['completed_count'], 'total' => $summary['sales_orders']['completed_total']],
            ],
            'by_payment'  => $this->labelPayments($byPayment, $revenue),
            'by_branch'   => $this->labelled($byBranch, $revenue, fn ($id) => $id === 'none' ? 'Main / unassigned' : ($branchNames[(int) $id] ?? "Branch #{$id}")),
            'by_cashier'  => array_slice($this->labelled($byCashier, $posTotal, $this->nameLookup('users', array_keys($byCashier), 'Unknown user')), 0, 10),
            'by_counter'  => $this->labelled($byCounter, $posTotal, fn ($id) => $id === 'none' ? 'No counter' : ($this->counterNames($business)[(int) $id] ?? "Counter #{$id}")),
            'by_channel'  => $this->labelled($byChannel, $posTotal, fn ($k) => Sale::channelLabels()[$k] ?? ucfirst($k)),
            'by_category' => $byCategory,
            'heatmap'     => array_map(fn ($row) => array_map(fn ($v) => round($v, 2), $row), $heatmap),
            'heatmap_counts' => $heatCount,
            'returns'     => $returnsDetail,
            'top_products'  => $this->mergeTopProducts($items['products'], $invoiceItems),
            'top_customers' => array_slice($this->labelled($byCustomer, $revenue, $this->nameLookup('pos_customers', array_keys($byCustomer), 'Walk-in customers', 'walkin')), 0, 10),
            'transactions'  => $this->transactions($business, $from, $to, $f, $posOnly, $branchNames),
        ];
    }

    // ── Product-wise report ───────────────────────────────────────────────────

    /**
     * Every product sold in the period with its quantity and revenue per day, so the
     * desktop can show product totals, a day-by-day list and a product × day grid.
     * POS lines are net of their share of the bill discount; paid-invoice lines are
     * added unless `source` is 'pos' or a counter is picked. Returns (POS only) are
     * shown per product but not deducted from its revenue.
     *
     * @param  array{from: Carbon, to: Carbon, branch: int|string|null, counter: int|null, source: ?string}  $f
     */
    public function products(Business $business, array $f): array
    {
        $from    = $f['from']->copy()->startOfDay();
        $to      = $f['to']->copy()->endOfDay();
        $posOnly = $f['counter'] !== null;
        $withPos = ($f['source'] ?? null) !== 'invoices';
        $withInv = ($f['source'] ?? null) !== 'pos' && ! $posOnly;

        $days = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $days[$d->toDateString()] = [
                'date' => $d->toDateString(), 'label' => $d->format('D, M j'),
                'qty' => 0.0, 'revenue' => 0.0, 'products' => 0, 'sales' => 0,
            ];
        }

        $products = [];

        if ($withPos && $this->hasTable('pos_sale_items') && $this->hasTable('pos_sales')) {
            $q   = $this->posLines($business, $from, $to, $f['branch'], $f['counter']);
            $cat = $this->joinFirstCategory($q, 'i.product_id');
            $rows = $q->selectRaw("DATE(s.sold_at) as day, i.product_id as pid, MAX(i.product_name) as name, MAX(i.sku) as sku, MAX({$cat}) as category, COUNT(DISTINCT s.id) as sales, ".$this->lineAggregatesSql())
                ->groupByRaw('DATE(s.sold_at), i.product_id')
                ->get();
            foreach ($rows as $r) {
                $this->addProductDay($products, $days, $r, 'pos');
            }

            $counts = $this->posBase($business, $from, $to, $f['branch'], $f['counter'])
                ->where('pos_sales.status', Sale::STATUS_COMPLETED)
                ->selectRaw('DATE(pos_sales.sold_at) as day, COUNT(*) as c')
                ->groupByRaw('DATE(pos_sales.sold_at)')
                ->get();
            foreach ($counts as $c) {
                $day = substr((string) $c->day, 0, 10);
                if (isset($days[$day])) {
                    $days[$day]['sales'] += (int) $c->c;
                }
            }
        }

        if ($withInv && $this->hasTable('invoices') && $this->hasTable('invoice_items')) {
            $q = DB::table('invoices as v')
                ->join('invoice_items as i', 'i.invoice_id', '=', 'v.id')
                ->leftJoin('products as p', 'p.id', '=', 'i.product_id')
                ->where('v.business_id', $business->id)
                ->where('v.status', 'paid')
                ->whereBetween('v.issue_date', [$from->toDateString(), $to->toDateString()]);
            $this->applyBranch($q, 'v.branch_id', $f['branch']);
            $cat  = $this->joinFirstCategory($q, 'i.product_id');
            $name = "COALESCE(p.name, i.description, 'Item')";
            $rows = $q->selectRaw("v.issue_date as day, i.product_id as pid, {$name} as name, MAX(p.sku) as sku, MAX({$cat}) as category, COUNT(DISTINCT v.id) as sales,"
                    .' SUM(i.quantity) as qty, SUM(i.line_total) as revenue, 0 as costed_revenue, 0 as costed_qty, 0 as cogs')
                ->groupByRaw("v.issue_date, i.product_id, {$name}")
                ->get();
            foreach ($rows as $r) {
                $this->addProductDay($products, $days, $r, 'invoice');
            }

            $counts = DB::table('invoices')
                ->where('business_id', $business->id)
                ->where('status', 'paid')
                ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()]);
            $this->applyBranch($counts, 'branch_id', $f['branch']);
            foreach ($counts->selectRaw('issue_date as day, COUNT(*) as c')->groupBy('issue_date')->get() as $c) {
                $day = substr((string) $c->day, 0, 10);
                if (isset($days[$day])) {
                    $days[$day]['sales'] += (int) $c->c;
                }
            }
        }

        $this->foldNamedIntoProducts($products);

        // Returns per product (POS only)
        $returnsQty = 0.0;
        $returnsTotal = 0.0;
        if ($withPos && $this->hasTable('pos_sale_returns') && $this->hasTable('pos_sale_return_items')) {
            $q = DB::table('pos_sale_returns as r')
                ->join('pos_sale_return_items as ri', 'ri.pos_sale_return_id', '=', 'r.id')
                ->where('r.business_id', $business->id)
                ->whereBetween('r.returned_at', [$from, $to]);
            if ($f['branch'] !== null || $f['counter'] !== null) {
                $q->join('pos_sales as s', 's.id', '=', 'r.pos_sale_id');
                $this->applyBranch($q, 's.branch_id', $f['branch']);
                if ($f['counter'] !== null) {
                    $q->where('s.pos_counter_id', $f['counter']);
                }
            }
            $rows = $q->selectRaw('ri.product_id as pid, MAX(ri.product_name) as name, MAX(ri.sku) as sku, SUM(ri.quantity) as qty, SUM(ri.line_total) as total')
                ->groupBy('ri.product_id')
                ->get();
            foreach ($rows as $r) {
                $key = 'p'.$r->pid;
                // Returned now but sold before the period — still listed so the refund is visible
                $products[$key] ??= $this->blankProduct($key, (int) $r->pid, (string) $r->name, $r->sku, null);
                $products[$key]['returns_qty']   += (float) $r->qty;
                $products[$key]['returns_total'] += (float) $r->total;
                $returnsQty   += (float) $r->qty;
                $returnsTotal += (float) $r->total;
            }
        }

        $revenue = array_sum(array_column($products, 'revenue'));
        $costed  = array_sum(array_column($products, 'costed_revenue'));
        $cogs    = array_sum(array_column($products, 'cogs'));

        $out = [];
        foreach ($products as $p) {
            foreach ($p['daily'] as $day => $v) {
                $days[$day]['qty']     += $v['qty'];
                $days[$day]['revenue'] += $v['revenue'];
                $days[$day]['products']++;
            }
            ksort($p['daily']);
            $out[] = [
                'key'           => $p['key'],
                'product_id'    => $p['product_id'],
                'name'          => $p['name'],
                'sku'           => $p['sku'],
                'category'      => $p['category'],
                'qty'           => round($p['qty'], 3),
                'pos_qty'       => round($p['pos_qty'], 3),
                'invoice_qty'   => round($p['invoice_qty'], 3),
                'revenue'       => round($p['revenue'], 2),
                'avg_price'     => $p['qty'] > 0 ? round($p['revenue'] / $p['qty'], 2) : 0.0,
                'share'         => $revenue > 0 ? round($p['revenue'] / $revenue * 100, 1) : 0.0,
                'sales'         => $p['sales'],
                'days_sold'     => count($p['daily']),
                'returns_qty'   => round($p['returns_qty'], 3),
                'returns_total' => round($p['returns_total'], 2),
                ...$this->costFields($p['costed_revenue'], $p['costed_qty'], $p['cogs']),
                'daily'         => array_map(fn ($v) => [
                    'qty'     => round($v['qty'], 3),
                    'revenue' => round($v['revenue'], 2),
                    ...$this->costFields($v['costed_revenue'], $v['costed_qty'], $v['cogs']),
                ], $p['daily']),
            ];
        }
        usort($out, fn ($a, $b) => [$b['revenue'], $b['qty']] <=> [$a['revenue'], $a['qty']]);

        $dayList = array_map(fn ($d) => ['qty' => round($d['qty'], 3), 'revenue' => round($d['revenue'], 2)] + $d, array_values($days));
        $active  = array_filter($dayList, fn ($d) => $d['revenue'] > 0 || $d['qty'] > 0);
        $best    = $active === [] ? null : array_reduce($active, fn ($c, $d) => $c === null || $d['revenue'] > $c['revenue'] ? $d : $c);
        $sold    = array_filter($out, fn ($p) => $p['qty'] > 0);
        $byUnits = $sold;
        usort($byUnits, fn ($a, $b) => $b['qty'] <=> $a['qty']);

        return [
            'range' => [
                'from'     => $from->toDateString(),
                'to'       => $to->toDateString(),
                'days'     => count($days),
            ],
            'filters' => $this->filterOptions($business, $this->branchNames($business), $posOnly),
            'summary' => [
                'products'          => count($sold),
                'qty'               => round(array_sum(array_column($out, 'qty')), 3),
                'revenue'           => round($revenue, 2),
                'sales'             => array_sum(array_column($dayList, 'sales')),
                'cogs'              => round($cogs, 2),
                ...$this->rowProfit($costed, $cogs),
                'uncosted_revenue'  => round(max(0.0, array_sum(array_column($products, 'pos_revenue')) - $costed), 2),
                'returns_qty'       => round($returnsQty, 3),
                'returns_total'     => round($returnsTotal, 2),
                'active_days'       => count($active),
                'avg_daily_revenue' => round($revenue / max(1, count($days)), 2),
                'best_day'          => $best ? ['date' => $best['date'], 'label' => $best['label'], 'revenue' => $best['revenue'], 'qty' => $best['qty']] : null,
                'top_product'       => $sold === [] ? null : ['name' => $out[0]['name'], 'revenue' => $out[0]['revenue'], 'qty' => $out[0]['qty']],
                'most_units'        => $byUnits === [] ? null : ['name' => $byUnits[0]['name'], 'qty' => $byUnits[0]['qty']],
            ],
            'days'     => $dayList,
            'products' => $out,
        ];
    }

    private function blankProduct(string $key, ?int $id, string $name, ?string $sku, ?string $category): array
    {
        return [
            'key' => $key, 'product_id' => $id, 'name' => $name, 'sku' => $sku ?: null, 'category' => $category,
            'qty' => 0.0, 'pos_qty' => 0.0, 'invoice_qty' => 0.0, 'revenue' => 0.0, 'pos_revenue' => 0.0,
            'costed_revenue' => 0.0, 'costed_qty' => 0.0, 'cogs' => 0.0, 'sales' => 0, 'returns_qty' => 0.0, 'returns_total' => 0.0, 'daily' => [],
        ];
    }

    /** Cost of goods, average unit cost and profit for one product (or one product-day); nulls when no line had a cost. */
    private function costFields(float $costedRevenue, float $costedQty, float $cogs): array
    {
        $known = $costedRevenue > 0 || $cogs > 0;

        return [
            'cogs'      => $known ? round($cogs, 2) : null,
            'unit_cost' => $known && $costedQty > 0 ? round($cogs / $costedQty, 2) : null,
            ...$this->rowProfit($costedRevenue, $cogs),
        ];
    }

    /**
     * Invoice lines typed without picking a product are keyed by name; fold each into the
     * real product of the same name (case-insensitive) so it isn't listed twice.
     */
    private function foldNamedIntoProducts(array &$products): void
    {
        $byName = [];
        foreach ($products as $key => $p) {
            if ($p['product_id'] !== null) {
                $byName[mb_strtolower(trim($p['name']))] ??= $key;
            }
        }
        foreach ($products as $key => $n) {
            $into = $n['product_id'] === null ? ($byName[mb_strtolower(trim($n['name']))] ?? null) : null;
            if ($into === null) {
                continue;
            }
            $p = &$products[$into];
            foreach (['qty', 'pos_qty', 'invoice_qty', 'revenue', 'pos_revenue', 'costed_revenue', 'costed_qty', 'cogs', 'sales'] as $f) {
                $p[$f] += $n[$f];
            }
            $p['category'] ??= $n['category'];
            foreach ($n['daily'] as $day => $v) {
                $p['daily'][$day] ??= array_fill_keys(array_keys($v), 0.0);
                foreach ($v as $f => $x) {
                    $p['daily'][$day][$f] += $x;
                }
            }
            unset($p, $products[$key]);
        }
    }

    /** Adds one (day, product) aggregate row from POS or invoice lines; products are merged by id, else by name. */
    private function addProductDay(array &$products, array $days, object $r, string $src): void
    {
        $day = substr((string) $r->day, 0, 10);
        if (! isset($days[$day])) {
            return;
        }
        $key = $r->pid !== null ? 'p'.$r->pid : 'n'.$r->name;
        $products[$key] ??= $this->blankProduct($key, $r->pid !== null ? (int) $r->pid : null, (string) $r->name, $r->sku, $r->category);
        $products[$key]['category'] ??= $r->category;
        $products[$key]['sku']      ??= $r->sku ?: null;

        $qty = (float) $r->qty;
        $rev = (float) $r->revenue;
        $p   = &$products[$key];
        $p['qty']            += $qty;
        $p[$src.'_qty']      += $qty;
        $p['revenue']        += $rev;
        $p['costed_revenue'] += (float) $r->costed_revenue;
        $p['costed_qty']     += (float) $r->costed_qty;
        $p['cogs']           += (float) $r->cogs;
        $p['sales']          += (int) $r->sales;
        if ($src === 'pos') {
            $p['pos_revenue'] += $rev;
        }
        $p['daily'][$day] ??= ['qty' => 0.0, 'revenue' => 0.0, 'costed_revenue' => 0.0, 'costed_qty' => 0.0, 'cogs' => 0.0];
        $d = &$p['daily'][$day];
        $d['qty']            += $qty;
        $d['revenue']        += $rev;
        $d['costed_revenue'] += (float) $r->costed_revenue;
        $d['costed_qty']     += (float) $r->costed_qty;
        $d['cogs']           += (float) $r->cogs;
        unset($d, $p);
    }

    // ── Customer-wise report ──────────────────────────────────────────────────

    /**
     * One row per customer who bought in the period (POS sales + paid invoices) or has
     * an unpaid invoice in it, plus a single walk-in row for POS sales without a customer.
     *
     * @param  array{from: Carbon, to: Carbon, branch: int|string|null, counter: int|null}  $f
     */
    public function customers(Business $business, array $f): array
    {
        $from    = $f['from']->copy()->startOfDay();
        $to      = $f['to']->copy()->endOfDay();
        $posOnly = $f['counter'] !== null;

        $rows  = [];
        $blank = fn () => [
            'pos_count' => 0, 'pos_total' => 0.0, 'invoice_count' => 0, 'invoice_total' => 0.0,
            'discounts' => 0.0, 'credit_due' => 0.0, 'receivable' => 0.0, 'returns_count' => 0, 'returns_total' => 0.0,
            'qty' => 0.0, 'costed_revenue' => 0.0, 'cogs' => 0.0, 'first' => null, 'last' => null,
            'payments' => [], 'products' => [],
        ];
        $seen = function (string $k, string $when) use (&$rows) {
            if ($rows[$k]['first'] === null || $when < $rows[$k]['first']) {
                $rows[$k]['first'] = $when;
            }
            if ($rows[$k]['last'] === null || $when > $rows[$k]['last']) {
                $rows[$k]['last'] = $when;
            }
        };

        // ── POS sales
        foreach ($this->posSales($business, $from, $to, $f['branch'], $f['counter'])->where('status', Sale::STATUS_COMPLETED) as $s) {
            $k = $s->pos_customer_id === null ? 'walkin' : (string) $s->pos_customer_id;
            $rows[$k] ??= $blank();
            $amt = (float) $s->total;
            $rows[$k]['pos_count']++;
            $rows[$k]['pos_total'] += $amt;
            $rows[$k]['discounts'] += $this->saleDiscount($s);
            if ($s->payment_method === Sale::PAYMENT_CREDIT && ! $s->is_settled) {
                $rows[$k]['credit_due'] += max(0, $amt - (float) $s->amount_paid);
            }
            $pay = (string) $s->payment_method;
            $rows[$k]['payments'][$pay] = ($rows[$k]['payments'][$pay] ?? 0.0) + $amt;
            $seen($k, Carbon::parse($s->sold_at)->format('Y-m-d H:i'));
        }

        // ── POS items: units, profit and favourite products
        if ($this->hasTable('pos_sale_items') && $this->hasTable('pos_sales')) {
            $lines = $this->posLines($business, $from, $to, $f['branch'], $f['counter'])
                ->selectRaw('s.pos_customer_id as cid, i.product_id as pid, MAX(i.product_name) as name, '.$this->lineAggregatesSql())
                ->groupBy('s.pos_customer_id', 'i.product_id')
                ->get();
            foreach ($lines as $l) {
                $k = $l->cid === null ? 'walkin' : (string) $l->cid;
                $rows[$k] ??= $blank();
                $rows[$k]['qty']            += (float) $l->qty;
                $rows[$k]['costed_revenue'] += (float) $l->costed_revenue;
                $rows[$k]['cogs']           += (float) $l->cogs;
                $rows[$k]['products'][]      = ['name' => (string) $l->name, 'qty' => (float) $l->qty, 'revenue' => (float) $l->revenue];
            }
        }

        // ── Invoices: paid = revenue, sent / overdue = still to collect
        if (! $posOnly) {
            foreach ($this->documents('invoices', 'issue_date', $business, $from, $to, $f['branch'], ['payment_method']) as $inv) {
                $paid = $inv->status === 'paid';
                if (! $paid && ! in_array($inv->status, ['sent', 'overdue'], true)) {
                    continue;
                }
                $k = $inv->customer_id === null ? 'walkin' : (string) $inv->customer_id;
                $rows[$k] ??= $blank();
                if ($paid) {
                    $amt = (float) $inv->total;
                    $rows[$k]['invoice_count']++;
                    $rows[$k]['invoice_total'] += $amt;
                    $pay = (string) ($inv->payment_method ?: 'invoice');
                    $rows[$k]['payments'][$pay] = ($rows[$k]['payments'][$pay] ?? 0.0) + $amt;
                    $seen($k, substr((string) $inv->issue_date, 0, 10));
                } else {
                    $rows[$k]['receivable'] += (float) $inv->total;
                }
            }
        }

        // ── Returns, by the customer of the original sale
        if ($this->hasTable('pos_sale_returns')) {
            $q = DB::table('pos_sale_returns as r')
                ->join('pos_sales as s', 's.id', '=', 'r.pos_sale_id')
                ->where('r.business_id', $business->id)
                ->whereBetween('r.returned_at', [$from, $to]);
            $this->applyBranch($q, 's.branch_id', $f['branch']);
            if ($f['counter'] !== null) {
                $q->where('s.pos_counter_id', $f['counter']);
            }
            foreach ($q->selectRaw('s.pos_customer_id as cid, COUNT(*) as c, SUM(r.total) as t')->groupBy('s.pos_customer_id')->get() as $r) {
                $k = $r->cid === null ? 'walkin' : (string) $r->cid;
                $rows[$k] ??= $blank();
                $rows[$k]['returns_count'] += (int) $r->c;
                $rows[$k]['returns_total'] += (float) $r->t;
            }
        }

        // ── Customer records
        $ids    = array_values(array_filter(array_keys($rows), fn ($k) => ctype_digit((string) $k)));
        $people = [];
        if ($ids !== [] && $this->hasTable('pos_customers')) {
            foreach (array_chunk($ids, 1000) as $chunk) {
                DB::table('pos_customers')->where('business_id', $business->id)->whereIn('id', $chunk)
                    ->get(['id', 'name', 'phone', 'email', 'created_at'])
                    ->each(function ($c) use (&$people) { $people[(int) $c->id] = $c; });
            }
        }

        $revenue = array_sum(array_map(fn ($r) => $r['pos_total'] + $r['invoice_total'], $rows));

        $out = [];
        foreach ($rows as $k => $r) {
            $k       = (string) $k;
            $walkin  = $k === 'walkin';
            $person  = $walkin ? null : ($people[(int) $k] ?? null);
            $rev     = $r['pos_total'] + $r['invoice_total'];
            $count   = $r['pos_count'] + $r['invoice_count'];
            arsort($r['payments']);
            usort($r['products'], fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

            $out[] = [
                'key'           => $k,
                'id'            => $walkin ? null : (int) $k,
                'walkin'        => $walkin,
                'name'          => $walkin ? 'Walk-in customers' : ($person->name ?? "Customer #{$k}"),
                'phone'         => $person->phone ?? null,
                'email'         => $person->email ?? null,
                'is_new'        => $person !== null && $person->created_at !== null && Carbon::parse($person->created_at)->gte($from),
                'since'         => $person?->created_at ? Carbon::parse($person->created_at)->toDateString() : null,
                'purchases'     => $count,
                'pos_count'     => $r['pos_count'],
                'pos_total'     => round($r['pos_total'], 2),
                'invoice_count' => $r['invoice_count'],
                'invoice_total' => round($r['invoice_total'], 2),
                'revenue'       => round($rev, 2),
                'share'         => $revenue > 0 ? round($rev / $revenue * 100, 1) : 0.0,
                'avg_sale'      => $count > 0 ? round($rev / $count, 2) : 0.0,
                'items'         => round($r['qty'], 3),
                'product_count' => count($r['products']),
                'discounts'     => round($r['discounts'], 2),
                'returns_count' => $r['returns_count'],
                'returns_total' => round($r['returns_total'], 2),
                'net'           => round($rev - $r['returns_total'], 2),
                ...$this->rowProfit($r['costed_revenue'], $r['cogs']),
                'credit_due'    => round($r['credit_due'], 2),
                'receivable'    => round($r['receivable'], 2),
                'balance'       => round($r['credit_due'] + $r['receivable'], 2),
                'first_purchase'=> $r['first'],
                'last_purchase' => $r['last'],
                'top_payment'   => $r['payments'] === [] ? null : (string) array_key_first($r['payments']),
                'top_products'  => array_map(fn ($p) => ['name' => $p['name'], 'qty' => round($p['qty'], 3), 'revenue' => round($p['revenue'], 2)], array_slice($r['products'], 0, 5)),
            ];
        }
        usort($out, fn ($a, $b) => [$b['revenue'], $b['balance']] <=> [$a['revenue'], $a['balance']]);

        $named  = array_values(array_filter($out, fn ($c) => ! $c['walkin'] && $c['purchases'] > 0));
        $walkin = collect($out)->firstWhere('walkin', true);
        $namedRevenue = array_sum(array_column($named, 'revenue'));

        return [
            'range' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
                'days' => (int) $from->diffInDays($to->copy()->startOfDay()) + 1,
            ],
            'filters' => $this->filterOptions($business, $this->branchNames($business), $posOnly),
            'summary' => [
                'customers'           => count($named),
                'new_customers'       => count(array_filter($named, fn ($c) => $c['is_new'])),
                'repeat_customers'    => count(array_filter($named, fn ($c) => $c['purchases'] >= 2)),
                'revenue'             => round($revenue, 2),
                'named_revenue'       => round($namedRevenue, 2),
                'named_share'         => $revenue > 0 ? round($namedRevenue / $revenue * 100, 1) : 0.0,
                'walkin_revenue'      => $walkin['revenue'] ?? 0.0,
                'walkin_count'        => $walkin['purchases'] ?? 0,
                'avg_per_customer'    => count($named) > 0 ? round($namedRevenue / count($named), 2) : 0.0,
                'balance_due'         => round(array_sum(array_column($out, 'balance')), 2),
                'with_balance'        => count(array_filter($out, fn ($c) => $c['balance'] > 0)),
                'top_customer'        => $named === [] ? null : ['name' => $named[0]['name'], 'revenue' => $named[0]['revenue']],
            ],
            'customers' => $out,
        ];
    }

    // ── Streams ───────────────────────────────────────────────────────────────

    private function posBase(Business $business, Carbon $from, Carbon $to, int|string|null $branch, ?int $counter, string $t = 'pos_sales'): Builder
    {
        $q = DB::table($t === 'pos_sales' ? 'pos_sales' : "pos_sales as {$t}")
            ->where("{$t}.business_id", $business->id)
            ->whereBetween("{$t}.sold_at", [$from, $to]);

        $this->applyBranch($q, "{$t}.branch_id", $branch);
        if ($counter !== null) {
            $q->where("{$t}.pos_counter_id", $counter);
        }

        return $q;
    }

    private function posSales(Business $business, Carbon $from, Carbon $to, int|string|null $branch, ?int $counter)
    {
        if (! $this->hasTable('pos_sales')) {
            return collect();
        }

        return $this->posBase($business, $from, $to, $branch, $counter)
            ->select([
                'pos_sales.id', 'pos_sales.branch_id', 'pos_sales.pos_counter_id', 'pos_sales.user_id',
                'pos_sales.pos_customer_id', 'pos_sales.status', 'pos_sales.payment_method', 'pos_sales.channel',
                'pos_sales.discount_amount', 'pos_sales.coupon_discount', 'pos_sales.gift_card_amount',
                'pos_sales.total', 'pos_sales.amount_paid', 'pos_sales.is_settled', 'pos_sales.sold_at',
            ])
            ->when($this->hasTable('pos_sale_items'), fn ($q) => $q->selectRaw(
                '(SELECT COALESCE(SUM(d.discount_amount * d.quantity), 0) FROM pos_sale_items d WHERE d.pos_sale_id = pos_sales.id) as item_discount'
            ))
            ->get();
    }

    /** Everything taken off a sale: per-item discounts, the bill discount and the coupon. */
    private function saleDiscount(object $s): float
    {
        return (float) ($s->item_discount ?? 0) + (float) $s->discount_amount + (float) ($s->coupon_discount ?? 0);
    }

    /**
     * Per-line aggregates shared by the product and category breakdowns (rows of
     * pos_sale_items `i` joined to their sale `s` and product `p`):
     *   revenue        line total after its share of the bill discount + coupon
     *   costed_revenue revenue of lines whose unit cost is known
     *   cogs           unit cost × quantity of those lines
     *   uncosted       lines sold without any cost price
     *   below_cost / loss  lines sold for less than they cost, and by how much
     */
    private function lineAggregatesSql(): string
    {
        // What the stock batch cost; else the product's cost price (stock sold without
        // a batch, or a batch received before the cost was entered). Services carry no
        // goods cost. NULL = unknown, kept out of profit rather than counted as 100% margin.
        $cost = $this->hasTable('products')
            ? 'CASE WHEN i.unit_cost > 0 THEN i.unit_cost WHEN p.cost_price > 0 THEN p.cost_price WHEN i.service_item_id IS NOT NULL THEN 0 ELSE NULL END'
            : 'CASE WHEN i.unit_cost > 0 THEN i.unit_cost WHEN i.service_item_id IS NOT NULL THEN 0 ELSE NULL END';
        // Bill discount and coupon are spread over the lines in proportion to line value
        // (sale total ÷ subtotal), so the lines add up to what was actually charged.
        $net = 'i.line_total * (CASE WHEN s.subtotal > 0 THEN s.total * 1.0 / s.subtotal ELSE 1 END)';

        return "SUM(i.quantity) as qty, SUM({$net}) as revenue,"
            ." SUM(CASE WHEN ({$cost}) IS NULL THEN 0 ELSE {$net} END) as costed_revenue,"
            ." SUM(CASE WHEN ({$cost}) IS NULL THEN 0 ELSE i.quantity END) as costed_qty,"
            ." SUM(COALESCE({$cost}, 0) * i.quantity) as cogs,"
            ." SUM(CASE WHEN ({$cost}) IS NULL THEN 1 ELSE 0 END) as uncosted,"
            ." SUM(CASE WHEN ({$cost}) * i.quantity - {$net} > 0.005 THEN 1 ELSE 0 END) as below_cost,"
            ." SUM(CASE WHEN ({$cost}) * i.quantity - {$net} > 0.005 THEN ({$cost}) * i.quantity - {$net} ELSE 0 END) as loss";
    }

    /** POS sale lines in range, joined to their product for the cost-price fallback. */
    private function posLines(Business $business, Carbon $from, Carbon $to, int|string|null $branch, ?int $counter): Builder
    {
        $q = $this->posBase($business, $from, $to, $branch, $counter, 's')
            ->join('pos_sale_items as i', 'i.pos_sale_id', '=', 's.id')
            ->where('s.status', Sale::STATUS_COMPLETED);
        if ($this->hasTable('products')) {
            $q->leftJoin('products as p', 'p.id', '=', 'i.product_id');
        }

        return $q;
    }

    /** Profit and margin of a breakdown row — null where none of its lines has a known cost. */
    private function rowProfit(float $costedRevenue, float $cogs): array
    {
        if ($costedRevenue <= 0 && $cogs <= 0) {
            return ['profit' => null, 'margin' => null];
        }
        $profit = $costedRevenue - $cogs;

        return [
            'profit' => round($profit, 2),
            'margin' => $costedRevenue > 0 ? round($profit / $costedRevenue * 100, 1) : null,
        ];
    }

    /** @return array{qty: float, revenue: float, costed_revenue: float, cogs: float, uncosted_lines: int, below_cost_lines: int, below_cost_loss: float, products: array<int, array>} */
    private function posItemTotals(Business $business, Carbon $from, Carbon $to, int|string|null $branch, ?int $counter): array
    {
        $out = ['qty' => 0.0, 'revenue' => 0.0, 'costed_revenue' => 0.0, 'cogs' => 0.0,
            'uncosted_lines' => 0, 'below_cost_lines' => 0, 'below_cost_loss' => 0.0, 'products' => []];
        if (! $this->hasTable('pos_sale_items') || ! $this->hasTable('pos_sales')) {
            return $out;
        }

        $rows = $this->posLines($business, $from, $to, $branch, $counter)
            ->selectRaw('i.product_name as name, '.$this->lineAggregatesSql())
            ->groupBy('i.product_name')
            ->get();

        foreach ($rows as $r) {
            $out['qty']              += (float) $r->qty;
            $out['revenue']          += (float) $r->revenue;
            $out['costed_revenue']   += (float) $r->costed_revenue;
            $out['cogs']             += (float) $r->cogs;
            $out['uncosted_lines']   += (int) $r->uncosted;
            $out['below_cost_lines'] += (int) $r->below_cost;
            $out['below_cost_loss']  += (float) $r->loss;
            $out['products'][] = [
                'name' => (string) $r->name, 'qty' => (float) $r->qty, 'revenue' => (float) $r->revenue,
                'costed_revenue' => (float) $r->costed_revenue, 'cogs' => (float) $r->cogs,
            ];
        }

        return $out;
    }

    /**
     * POS line totals per product category. A product in several categories counts
     * under its first one so the shares add up to 100%.
     */
    private function posCategoryTotals(Business $business, Carbon $from, Carbon $to, int|string|null $branch, ?int $counter): array
    {
        if (! $this->hasTable('pos_sale_items') || ! $this->hasTable('pos_sales')) {
            return [];
        }

        $q = $this->posLines($business, $from, $to, $branch, $counter);

        $nameSql = $this->joinFirstCategory($q, 'i.product_id');

        $rows = $q->selectRaw("{$nameSql} as name, COUNT(DISTINCT i.product_name) as products, ".$this->lineAggregatesSql())
            ->groupByRaw($nameSql)
            ->get();

        $whole = (float) $rows->sum('revenue');

        return $rows->sortByDesc('revenue')->values()->map(fn ($r) => [
            'label'    => (string) $r->name,
            'products' => (int) $r->products,
            'qty'      => round((float) $r->qty, 3),
            'total'    => round((float) $r->revenue, 2),
            ...$this->rowProfit((float) $r->costed_revenue, (float) $r->cogs),
            'share'    => $whole > 0 ? round((float) $r->revenue / $whole * 100, 1) : 0.0,
        ])->all();
    }

    /** Joins each product's first category (as `c`); returns the SQL for its name. */
    private function joinFirstCategory(Builder $q, string $productCol): string
    {
        if (! $this->hasTable('product_product_category') || ! $this->hasTable('product_categories')) {
            return "'Uncategorised'";
        }
        $first = DB::table('product_product_category')
            ->selectRaw('product_id, MIN(product_category_id) as category_id')
            ->groupBy('product_id');
        $q->leftJoinSub($first, 'pc', 'pc.product_id', '=', $productCol)
            ->leftJoin('product_categories as c', 'c.id', '=', 'pc.category_id');

        return "COALESCE(c.name, 'Uncategorised')";
    }

    /** @return array<int, array{name: string, qty: float, revenue: float}> */
    private function invoiceItemTotals(Business $business, Carbon $from, Carbon $to, int|string|null $branch): array
    {
        if (! $this->hasTable('invoices') || ! $this->hasTable('invoice_items')) {
            return [];
        }

        $q = DB::table('invoices as v')
            ->join('invoice_items as i', 'i.invoice_id', '=', 'v.id')
            ->leftJoin('products as p', 'p.id', '=', 'i.product_id')
            ->where('v.business_id', $business->id)
            ->where('v.status', 'paid')
            ->whereBetween('v.issue_date', [$from->toDateString(), $to->toDateString()]);
        $this->applyBranch($q, 'v.branch_id', $branch);

        return $q->selectRaw("COALESCE(p.name, i.description, 'Item') as name, SUM(i.quantity) as qty, SUM(i.line_total) as revenue")
            ->groupByRaw("COALESCE(p.name, i.description, 'Item')")
            ->get()
            ->map(fn ($r) => ['name' => (string) $r->name, 'qty' => (float) $r->qty, 'revenue' => (float) $r->revenue])
            ->all();
    }

    /** @return array{0: float, 1: int, 2: array{by_reason: array, by_method: array, products: array}} */
    private function returns(Business $business, Carbon $from, Carbon $to, int|string|null $branch, ?int $counter, array &$buckets, callable $bucketKey): array
    {
        $detail = ['by_reason' => [], 'by_method' => [], 'products' => []];
        if (! $this->hasTable('pos_sale_returns')) {
            return [0.0, 0, $detail];
        }

        $q = DB::table('pos_sale_returns as r')
            ->where('r.business_id', $business->id)
            ->whereBetween('r.returned_at', [$from, $to]);

        if ($branch !== null || $counter !== null) {
            $q->join('pos_sales as s', 's.id', '=', 'r.pos_sale_id');
            $this->applyBranch($q, 's.branch_id', $branch);
            if ($counter !== null) {
                $q->where('s.pos_counter_id', $counter);
            }
        }

        $hasReason = Schema::hasColumn('pos_sale_returns', 'refund_reason');
        $rows = $q->get(array_merge(['r.id', 'r.total', 'r.returned_at', 'r.refund_method'], $hasReason ? ['r.refund_reason'] : []));

        $byReason = [];
        $byMethod = [];
        foreach ($rows as $r) {
            $amt = (float) $r->total;
            $k   = $bucketKey(Carbon::parse($r->returned_at));
            if (isset($buckets[$k])) {
                $buckets[$k]['returns'] += $amt;
            }
            $reason = trim((string) ($hasReason ? $r->refund_reason : ''));
            $this->bump($byReason, $reason === '' ? 'No reason given' : $reason, $amt);
            $this->bump($byMethod, (string) ($r->refund_method ?: 'cash'), $amt);
        }

        $total = (float) $rows->sum('total');
        $detail['by_reason'] = $this->labelled($byReason, $total, fn ($k) => ucfirst(str_replace(['_', '-'], ' ', $k)));
        $detail['by_method'] = $this->labelPayments($byMethod, $total);

        if ($rows->isNotEmpty() && $this->hasTable('pos_sale_return_items')) {
            $detail['products'] = $rows->pluck('id')->chunk(1000)
                ->flatMap(fn ($ids) => DB::table('pos_sale_return_items')
                    ->whereIn('pos_sale_return_id', $ids->all())
                    ->selectRaw('product_name as name, SUM(quantity) as qty, SUM(line_total) as total, COUNT(DISTINCT pos_sale_return_id) as returns')
                    ->groupBy('product_name')
                    ->get())
                ->groupBy('name')
                ->map(fn ($g, $name) => [
                    'name'    => (string) $name,
                    'qty'     => round((float) $g->sum('qty'), 3),
                    'total'   => round((float) $g->sum('total'), 2),
                    'returns' => (int) $g->sum('returns'),
                ])
                ->sortByDesc('total')
                ->take(10)
                ->values()
                ->all();
        }

        return [$total, $rows->count(), $detail];
    }

    /**
     * Distinct named customers who bought in the period; "new" = customer record
     * created on or after the start of the period.
     *
     * @param  array<int, string>  $keys  customer ids plus the 'walkin' bucket
     * @return array{unique: int, new: int, returning: int}
     */
    private function customerInsights(Business $business, Carbon $from, array $keys): array
    {
        $ids = array_values(array_filter($keys, fn ($id) => ctype_digit((string) $id)));
        if ($ids === [] || ! $this->hasTable('pos_customers')) {
            return ['unique' => count($ids), 'new' => 0, 'returning' => count($ids)];
        }

        $new = collect($ids)->chunk(1000)->sum(fn ($chunk) => DB::table('pos_customers')
            ->where('business_id', $business->id)
            ->whereIn('id', $chunk->all())
            ->where('created_at', '>=', $from)
            ->count());

        return ['unique' => count($ids), 'new' => $new, 'returning' => count($ids) - $new];
    }

    /** Invoices / quotations / sales orders share the same columns we need. */
    private function documents(string $table, string $dateCol, Business $business, Carbon $from, Carbon $to, int|string|null $branch, array $extra = [])
    {
        if (! $this->hasTable($table)) {
            return collect();
        }

        $q = DB::table($table)
            ->where('business_id', $business->id)
            ->whereBetween($dateCol, [$from->toDateString(), $to->toDateString()]);
        $this->applyBranch($q, 'branch_id', $branch);

        return $q->get(array_merge(['id', 'branch_id', 'customer_id', 'status', 'total', $dateCol], $extra));
    }

    /** @return array{revenue: float, count: int} */
    private function previousPeriod(Business $business, Carbon $from, int $days, int|string|null $branch, ?int $counter, bool $posOnly): array
    {
        $pFrom = $from->copy()->subDays($days)->startOfDay();
        $pTo   = $from->copy()->subDay()->endOfDay();

        $revenue = 0.0;
        $count   = 0;

        if ($this->hasTable('pos_sales')) {
            $row = $this->posBase($business, $pFrom, $pTo, $branch, $counter)
                ->where('pos_sales.status', Sale::STATUS_COMPLETED)
                ->selectRaw('COUNT(*) as c, COALESCE(SUM(pos_sales.total), 0) as t')
                ->first();
            $revenue += (float) $row->t;
            $count   += (int) $row->c;
        }

        if (! $posOnly && $this->hasTable('invoices')) {
            $q = DB::table('invoices')
                ->where('business_id', $business->id)
                ->where('status', 'paid')
                ->whereBetween('issue_date', [$pFrom->toDateString(), $pTo->toDateString()]);
            $this->applyBranch($q, 'branch_id', $branch);
            $row = $q->selectRaw('COUNT(*) as c, COALESCE(SUM(total), 0) as t')->first();
            $revenue += (float) $row->t;
            $count   += (int) $row->c;
        }

        return ['revenue' => $revenue, 'count' => $count];
    }

    // ── Unified transaction list ─────────────────────────────────────────────

    private function transactions(Business $business, Carbon $from, Carbon $to, array $f, bool $posOnly, array $branchNames): array
    {
        $source = in_array($f['txn_source'] ?? null, self::SOURCES, true) ? $f['txn_source'] : null;
        $status = $f['txn_status'] ?: null;
        $search = trim((string) ($f['q'] ?? ''));

        $parts = [];
        $want  = fn (string $s) => ($source === null || $source === $s) && ($s === 'pos' || ! $posOnly);

        if ($want('pos') && $this->hasTable('pos_sales')) {
            $q = $this->posBase($business, $from, $to, $f['branch'], $f['counter'], 's')
                ->leftJoin('pos_customers as c', 'c.id', '=', 's.pos_customer_id')
                ->selectRaw("'pos' as type, s.id as id, s.sale_number as number, s.sold_at as occurred_at, c.name as customer, s.branch_id as branch_id, s.status as status, s.payment_method as payment, s.total as total");
            $parts[] = $this->filterTxn($q, 's.sale_number', 's.status', $status, $search);
        }

        foreach ([
            'invoices'     => ['invoices',     'invoice_number', 'issue_date', 'payment_method'],
            'quotations'   => ['quotations',   'quote_number',   'quote_date', null],
            'sales_orders' => ['sales_orders', 'order_number',   'order_date', null],
        ] as $key => [$table, $numCol, $dateCol, $payCol]) {
            if (! $want($key) || ! $this->hasTable($table)) {
                continue;
            }
            $q = DB::table("{$table} as d")
                ->leftJoin('pos_customers as c', 'c.id', '=', 'd.customer_id')
                ->where('d.business_id', $business->id)
                ->whereBetween("d.{$dateCol}", [$from->toDateString(), $to->toDateString()])
                ->selectRaw("'{$key}' as type, d.id as id, d.{$numCol} as number, d.{$dateCol} as occurred_at, c.name as customer, d.branch_id as branch_id, d.status as status, "
                    .($payCol ? "d.{$payCol}" : 'NULL').' as payment, d.total as total');
            $this->applyBranch($q, 'd.branch_id', $f['branch']);
            $parts[] = $this->filterTxn($q, "d.{$numCol}", 'd.status', $status, $search);
        }

        $perPage = $f['per_page'];
        $page    = max(1, $f['page']);

        if ($parts === []) {
            return ['data' => [], 'page' => 1, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1];
        }

        $union = array_shift($parts);
        foreach ($parts as $p) {
            $union->unionAll($p);
        }

        $total = DB::query()->fromSub($union, 't')->count();
        $rows  = DB::query()->fromSub($union, 't')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        return [
            'data' => $rows->map(fn ($r) => [
                'type'        => $r->type,
                'id'          => (int) $r->id,
                'number'      => $r->number ?: '#'.$r->id,
                'occurred_at' => $r->occurred_at ? Carbon::parse($r->occurred_at)->format($r->type === 'pos' ? 'Y-m-d H:i' : 'Y-m-d') : null,
                'customer'    => $r->customer,
                'branch'      => $r->branch_id === null ? null : ($branchNames[(int) $r->branch_id] ?? "Branch #{$r->branch_id}"),
                'status'      => $r->status,
                'payment'     => $r->payment,
                'total'       => round((float) $r->total, 2),
            ])->all(),
            'page'      => $page,
            'per_page'  => $perPage,
            'total'     => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    private function filterTxn(Builder $q, string $numCol, string $statusCol, ?string $status, string $search): Builder
    {
        if ($status === 'open') {
            // Meta-status: quotations awaiting a decision + sales orders still in progress
            $q->whereIn($statusCol, ['draft', 'sent', ...self::OPEN_ORDER_STATUSES]);
        } elseif ($status !== null) {
            $q->where($statusCol, $status);
        }
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $q->where(fn ($w) => $w->where($numCol, 'like', $like)->orWhere('c.name', 'like', $like));
        }

        return $q;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function applyBranch(Builder $q, string $col, int|string|null $branch): void
    {
        if ($branch === 'none') {
            $q->whereNull($col);
        } elseif ($branch !== null) {
            $q->where($col, (int) $branch);
        }
    }

    private function hasTable(string $table): bool
    {
        return $this->tables[$table] ??= Schema::hasTable($table);
    }

    private function bump(array &$map, string $key, float $amount, int $count = 1): void
    {
        $map[$key] ??= ['total' => 0.0, 'count' => 0];
        $map[$key]['total'] += $amount;
        $map[$key]['count'] += $count;
    }

    /** Sort a bucket map by total desc and attach a label + share of the given whole. */
    private function labelled(array $map, float $whole, callable $label): array
    {
        uasort($map, fn ($a, $b) => $b['total'] <=> $a['total']);

        $out = [];
        foreach ($map as $key => $v) {
            $out[] = [
                'key'   => (string) $key,
                'label' => $label((string) $key),
                'count' => $v['count'],
                'total' => round($v['total'], 2),
                'share' => $whole > 0 ? round($v['total'] / $whole * 100, 1) : 0.0,
            ];
        }

        return $out;
    }

    private function labelPayments(array $map, float $whole): array
    {
        $labels = Sale::paymentMethodLabels() + [
            'gift_card'     => 'Gift card',
            'bank_transfer' => 'Bank transfer',
            'cheque'        => 'Cheque',
            'invoice'       => 'Invoice (unspecified)',
        ];

        return $this->labelled($map, $whole, fn ($k) => $labels[$k] ?? ucwords(str_replace(['_', '-'], ' ', $k)));
    }

    /** Builds a label resolver that looks up `name` for numeric ids in one query. */
    private function nameLookup(string $table, array $ids, string $fallback, ?string $fallbackKey = null): callable
    {
        $numeric = array_values(array_filter($ids, fn ($id) => ctype_digit((string) $id)));
        $names   = $numeric !== [] && $this->hasTable($table)
            ? DB::table($table)->whereIn('id', $numeric)->pluck('name', 'id')->all()
            : [];

        return fn ($id) => ($fallbackKey !== null && $id === $fallbackKey) ? $fallback : ($names[(int) $id] ?? $fallback);
    }

    /** Branch / counter choices for the desktop filter bar — same shape for every report. */
    private function filterOptions(Business $business, array $branchNames, bool $posOnly): array
    {
        return [
            'branches' => collect($branchNames)->map(fn ($name, $id) => ['id' => (int) $id, 'name' => $name])->values()->all(),
            'counters' => $this->counters($business),
            'pos_only' => $posOnly,
        ];
    }

    /** @return array<int, string> */
    private function branchNames(Business $business): array
    {
        if (! $this->hasTable('branches')) {
            return [];
        }

        return DB::table('branches')
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private ?array $counterCache = null;

    /** @return array<int, string> */
    private function counterNames(Business $business): array
    {
        return $this->counterCache ??= $this->hasTable('pos_counters')
            ? DB::table('pos_counters')->where('business_id', $business->id)->pluck('name', 'id')->all()
            : [];
    }

    private function counters(Business $business): array
    {
        if (! $this->hasTable('pos_counters')) {
            return [];
        }

        return DB::table('pos_counters')
            ->where('business_id', $business->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id'])
            ->map(fn ($c) => ['id' => (int) $c->id, 'name' => $c->name, 'branch_id' => $c->branch_id === null ? null : (int) $c->branch_id])
            ->all();
    }

    /** POS + paid-invoice lines merged by product name; profit only where POS cost is known. */
    private function mergeTopProducts(array $pos, array $invoice): array
    {
        $merged = [];
        foreach ($pos as $p) {
            $merged[$p['name']] = ['name' => $p['name'], 'qty' => $p['qty'], 'revenue' => $p['revenue'], 'costed_revenue' => $p['costed_revenue'], 'cogs' => $p['cogs']];
        }
        foreach ($invoice as $p) {
            $merged[$p['name']] ??= ['name' => $p['name'], 'qty' => 0.0, 'revenue' => 0.0, 'costed_revenue' => 0.0, 'cogs' => 0.0];
            $merged[$p['name']]['qty']     += $p['qty'];
            $merged[$p['name']]['revenue'] += $p['revenue'];
        }

        usort($merged, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $whole = array_sum(array_column($merged, 'revenue'));

        return array_map(fn ($p) => [
            'name'    => $p['name'],
            'qty'     => round($p['qty'], 3),
            'revenue' => round($p['revenue'], 2),
            'share'   => $whole > 0 ? round($p['revenue'] / $whole * 100, 1) : 0.0,
            'avg_price' => $p['qty'] > 0 ? round($p['revenue'] / $p['qty'], 2) : 0.0,
            // Profit only where POS cost is known — invoice lines carry no unit cost
            ...$this->rowProfit($p['costed_revenue'], $p['cogs']),
        ], array_slice($merged, 0, 20));
    }

    private function changePct(float|int $now, float|int $prev): ?float
    {
        if ((float) $prev == 0.0) {
            return null;
        }

        return round(((float) $now - (float) $prev) / abs((float) $prev) * 100, 1);
    }

    /** @return array{0: array<string, array>, 1: callable} */
    private function makeBuckets(Carbon $from, Carbon $to, string $groupBy): array
    {
        $blank   = ['pos' => 0.0, 'pos_count' => 0, 'invoices' => 0.0, 'returns' => 0.0, 'quotes_accepted' => 0.0];
        $buckets = [];

        if ($groupBy === 'day') {
            for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
                $buckets[$d->format('Y-m-d')] = ['label' => $d->format('M j')] + $blank;
            }

            return [$buckets, fn (Carbon $c) => $c->format('Y-m-d')];
        }

        if ($groupBy === 'week') {
            for ($d = $from->copy()->startOfWeek(); $d->lte($to); $d->addWeek()) {
                $buckets[$d->format('oW')] = ['label' => $d->format('M j')] + $blank;
            }

            return [$buckets, fn (Carbon $c) => $c->copy()->startOfWeek()->format('oW')];
        }

        for ($d = $from->copy()->startOfMonth(); $d->lte($to); $d->addMonthNoOverflow()) {
            $buckets[$d->format('Y-m')] = ['label' => $d->format("M 'y")] + $blank;
        }

        return [$buckets, fn (Carbon $c) => $c->format('Y-m')];
    }
}
