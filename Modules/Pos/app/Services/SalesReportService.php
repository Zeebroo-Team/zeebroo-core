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
        $posDiscounts = (float) $posCompleted->sum(fn ($s) => (float) $s->discount_amount + (float) ($s->coupon_discount ?? 0));
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

            if ((float) $s->discount_amount + (float) ($s->coupon_discount ?? 0) > 0) {
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
        // POS only — invoice lines carry no unit cost. Uses the sale total (after order-level discounts).
        $grossProfit  = $posTotal - $items['cogs'];

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
            'gross_margin'       => $posTotal > 0 ? round($grossProfit / $posTotal * 100, 1) : 0.0,
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
            'filters' => [
                'branches' => collect($branchNames)->map(fn ($name, $id) => ['id' => (int) $id, 'name' => $name])->values()->all(),
                'counters' => $this->counters($business),
                'pos_only' => $posOnly,
            ],
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
            ->get();
    }

    /** @return array{qty: float, revenue: float, cogs: float, products: array<int, array>} */
    private function posItemTotals(Business $business, Carbon $from, Carbon $to, int|string|null $branch, ?int $counter): array
    {
        $empty = ['qty' => 0.0, 'revenue' => 0.0, 'cogs' => 0.0, 'products' => []];
        if (! $this->hasTable('pos_sale_items') || ! $this->hasTable('pos_sales')) {
            return $empty;
        }

        $rows = $this->posBase($business, $from, $to, $branch, $counter, 's')
            ->join('pos_sale_items as i', 'i.pos_sale_id', '=', 's.id')
            ->where('s.status', Sale::STATUS_COMPLETED)
            ->selectRaw('i.product_name as name, SUM(i.quantity) as qty, SUM(i.line_total) as revenue, SUM(COALESCE(i.unit_cost, 0) * i.quantity) as cogs')
            ->groupBy('i.product_name')
            ->get();

        $out = $empty;
        foreach ($rows as $r) {
            $out['qty']     += (float) $r->qty;
            $out['revenue'] += (float) $r->revenue;
            $out['cogs']    += (float) $r->cogs;
            $out['products'][] = ['name' => (string) $r->name, 'qty' => (float) $r->qty, 'revenue' => (float) $r->revenue, 'cogs' => (float) $r->cogs];
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

        $q = $this->posBase($business, $from, $to, $branch, $counter, 's')
            ->join('pos_sale_items as i', 'i.pos_sale_id', '=', 's.id')
            ->where('s.status', Sale::STATUS_COMPLETED);

        $nameSql = "'Uncategorised'";
        if ($this->hasTable('product_product_category') && $this->hasTable('product_categories')) {
            $first = DB::table('product_product_category')
                ->selectRaw('product_id, MIN(product_category_id) as category_id')
                ->groupBy('product_id');
            $q->leftJoinSub($first, 'pc', 'pc.product_id', '=', 'i.product_id')
                ->leftJoin('product_categories as c', 'c.id', '=', 'pc.category_id');
            $nameSql = "COALESCE(c.name, 'Uncategorised')";
        }

        $rows = $q->selectRaw("{$nameSql} as name, COUNT(DISTINCT i.product_name) as products, SUM(i.quantity) as qty, SUM(i.line_total) as revenue, SUM(COALESCE(i.unit_cost, 0) * i.quantity) as cogs")
            ->groupByRaw($nameSql)
            ->get();

        $whole = (float) $rows->sum('revenue');

        return $rows->sortByDesc('revenue')->values()->map(fn ($r) => [
            'label'    => (string) $r->name,
            'products' => (int) $r->products,
            'qty'      => round((float) $r->qty, 3),
            'total'    => round((float) $r->revenue, 2),
            'profit'   => round((float) $r->revenue - (float) $r->cogs, 2),
            'margin'   => (float) $r->revenue > 0 && (float) $r->cogs > 0
                ? round(((float) $r->revenue - (float) $r->cogs) / (float) $r->revenue * 100, 1)
                : null,
            'share'    => $whole > 0 ? round((float) $r->revenue / $whole * 100, 1) : 0.0,
        ])->all();
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

    /** POS + paid-invoice lines merged by product name; margin only where POS cost is known. */
    private function mergeTopProducts(array $pos, array $invoice): array
    {
        $merged = [];
        foreach ($pos as $p) {
            $merged[$p['name']] = ['name' => $p['name'], 'qty' => $p['qty'], 'revenue' => $p['revenue'], 'pos_revenue' => $p['revenue'], 'cogs' => $p['cogs']];
        }
        foreach ($invoice as $p) {
            $merged[$p['name']] ??= ['name' => $p['name'], 'qty' => 0.0, 'revenue' => 0.0, 'pos_revenue' => 0.0, 'cogs' => 0.0];
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
            'profit'  => $p['cogs'] > 0 ? round($p['pos_revenue'] - $p['cogs'], 2) : null,
            'margin'  => $p['pos_revenue'] > 0 && $p['cogs'] > 0
                ? round(($p['pos_revenue'] - $p['cogs']) / $p['pos_revenue'] * 100, 1)
                : null,
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
