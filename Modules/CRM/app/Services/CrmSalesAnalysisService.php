<?php

namespace Modules\CRM\Services;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Division;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadStage;
use Modules\HRManagement\Models\Department;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationApproval;

/**
 * The CRM reports (ribbon → CRM → Report): one table of the filtered leads, grouped the way
 * the user picks. Same access and filters as the dashboard (CrmReportService), plus status
 * (open / won / lost), a customer search, industry and product.
 *
 *   department / division / customer / staff / source / industry / period
 *       "sales" view: leads created in the range, per group: open, won, lost, conversion,
 *       estimated pipeline, won value (accepted quotation total, else estimated value),
 *       quotations sent and their value
 *       "kpi" view: the inquiry-handling KPIs per group (CrmKpiService) — response and first
 *       contact times, follow-ups, quotations, conversion rates, pending / overdue, sales
 *   period
 *       one row per day / week / month / year of the range (every one, empty ones too)
 *   product
 *       how many inquiries asked for each item, what those leads were quoted (latest sent
 *       versions): customers, quantity and value, and how much of it was accepted
 *   customer_product
 *       the same per customer and item: what each company prefers, and its share of their quotes
 *   price
 *       quotations made in the range by discount band: values, discount given, the approval
 *       decision on the discount (approved / rejected / within the limits) and sales
 *
 * customerHistory() lists one customer's inquiries, quotations and sales (the drill-down
 * from a customer row).
 */
class CrmSalesAnalysisService
{
    public const GROUPS = [
        'department'       => 'Department',
        'division'         => 'Division',
        'customer'         => 'Customer',
        'staff'            => 'Staff',
        'source'           => 'Source',
        'industry'         => 'Industry',
        'period'           => 'Period',
        'product'          => 'Product / service',
        'customer_product' => 'Customer preferences',
        'price'            => 'Price & discount',
    ];

    /** The groups whose rows are leads, and so have both views. */
    public const LEAD_GROUPS = ['department', 'division', 'customer', 'staff', 'source', 'industry', 'period'];

    public const VIEWS = ['sales' => 'Sales', 'kpi' => 'KPIs'];

    public const PERIODS = ['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'];

    public const STATUSES = ['open' => 'Open', 'won' => 'Won', 'lost' => 'Lost'];

    /** Discount bands of the price report: band => label. */
    public const DISCOUNT_BANDS = [0 => 'No discount', 1 => 'Up to 5%', 2 => '5% – 10%', 3 => '10% – 15%', 4 => '15% – 20%', 5 => 'Over 20%'];

    private const CUSTOMER = "coalesce(nullif(trim(crm_leads.company), ''), crm_leads.name)";
    private const ITEM     = "coalesce(p.name, si.name, qi.description, 'Unnamed item')";
    // The lead's customer's industry, else that of the customer on one of its quotations
    private const INDUSTRY = "coalesce(nullif(trim(ic.industry), ''), iq.industry)";

    /** Daily rows are capped at a year so the table stays readable. */
    private const MAX_DAILY_DAYS = 366;

    public function __construct(
        private readonly CrmReportService $reports,
        private readonly CrmKpiService $kpis,
    ) {}

    /**
     * @param  array{group_by?: ?string, view?: ?string, period?: ?string, status?: ?string, search?: ?string, industry?: ?string, product_id?: int|string|null}  $input  plus the dashboard filters
     * @return array{group_by: string, group_label: string, view: string, period: ?string, filters: array, columns: list<array{key: string, label: string, type: string}>, rows: list<array>, totals: array, options: array}
     */
    public function report(Business $business, CrmAccess $access, array $input): array
    {
        $filters = $this->reports->filters($business, $access, $input);
        $group   = ($input['group_by'] ?? null) ?: 'department';
        if (! isset(self::GROUPS[$group])) {
            throw ValidationException::withMessages(['group_by' => 'Pick what to group the report by.']);
        }
        $view   = isset(self::VIEWS[$input['view'] ?? '']) ? $input['view'] : 'sales';
        $period = isset(self::PERIODS[$input['period'] ?? '']) ? $input['period'] : $this->defaultPeriod($filters);
        if ($group === 'period' && $period === 'day' && $this->days($filters) > self::MAX_DAILY_DAYS) {
            throw ValidationException::withMessages(['period' => 'A daily report covers up to a year — pick a shorter range or a weekly / monthly report.']);
        }
        $extra = $this->extraFilters($input);

        $scoped = fn () => $this->scoped($business, $access, $filters, $extra, $group === 'industry');
        $key    = fn (string $dateColumn) => $this->groupKey($group, $dateColumn, $period);
        $cohort = fn () => $scoped()->whereBetween('crm_leads.created_at', [$filters['from'], $filters['to']]);

        [$columns, $rows, $totals] = match (true) {
            $group === 'price'                                => $this->priceRows($business, $scoped(), $filters),
            in_array($group, ['product', 'customer_product']) => $this->itemRows($business, $cohort(), $group === 'customer_product'),
            $view === 'kpi'                                   => $this->kpiRows($business, $group, $scoped, $filters, $key, $period),
            default                                           => $this->leadRows($business, $group, $cohort(), $key('crm_leads.created_at'), $filters, $period),
        };

        return [
            'group_by'    => $group,
            'group_label' => self::GROUPS[$group],
            'view'        => in_array($group, self::LEAD_GROUPS, true) ? $view : null,
            'period'      => $group === 'period' ? $period : null,
            'filters'     => [
                'from'          => $filters['from']->toDateString(),
                'to'            => $filters['to']->toDateString(),
                'department_id' => $filters['department_id'],
                'division_id'   => $filters['division_id'],
                'user_id'       => $filters['user_id'],
                'source'        => $filters['source'],
                'status'        => $extra['status'],
                'search'        => $extra['search'],
                'industry'      => $extra['industry'],
                'product_id'    => $extra['product_id'],
            ],
            'columns' => $columns,
            'rows'    => $rows,
            'totals'  => $totals,
            'options' => [
                ...$this->reports->options($business, $access),
                'groups'      => collect(self::GROUPS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
                'lead_groups' => self::LEAD_GROUPS,
                'views'       => collect(self::VIEWS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
                'periods'     => collect(self::PERIODS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
                'statuses'    => collect(self::STATUSES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
                'industries'  => $this->industryOptions($business),
                'products'    => $this->productOptions($business),
            ],
        ];
    }

    /**
     * One customer's history (the drill-down from a customer row): their inquiries, quotations
     * and sales, all dates, within what this user may report on.
     */
    public function customerHistory(Business $business, CrmAccess $access, array $input): array
    {
        $customer = trim((string) ($input['customer'] ?? ''));
        if ($customer === '') {
            throw ValidationException::withMessages(['customer' => 'Pick a customer.']);
        }
        $filters = $this->reports->filters($business, $access, $input);

        $leads = $this->reports->leads($business, $access, $filters)
            ->whereRaw(self::CUSTOMER . ' = ?', [$customer])
            ->leftJoin('crm_lead_stages as s', 's.id', '=', 'crm_leads.stage_id')
            ->leftJoin('users as u', 'u.id', '=', 'crm_leads.assigned_to')
            ->orderByDesc('crm_leads.created_at')
            ->limit(500)
            ->get(['crm_leads.id', 'crm_leads.project_id', 'crm_leads.name', 'crm_leads.company', 'crm_leads.email', 'crm_leads.phone',
                'crm_leads.source', 'crm_leads.department_id', 'crm_leads.customer_id', 'crm_leads.estimated_value', 'crm_leads.created_at',
                'crm_leads.closed_at', 's.name as stage', 's.system_key', 's.is_won', 's.is_lost', 'u.name as owner']);
        $leadIds = $leads->pluck('id')->all();

        $quotations = Quotation::query()
            ->where('business_id', $business->id)
            ->whereIn('lead_id', $leadIds ?: [0])
            ->where('status', '!=', Quotation::STATUS_SUPERSEDED)
            ->orderByDesc('created_at')
            ->get(['id', 'lead_id', 'quote_number', 'version', 'status', 'subtotal', 'discount_amount', 'total', 'created_at', 'sent_at']);
        $accepted = $quotations->where('status', Quotation::STATUS_ACCEPTED)->groupBy('lead_id');

        $departments = Department::query()->where('business_id', $business->id)->pluck('name', 'id');
        $industry    = DB::table('pos_customers')
            ->whereIn('id', $leads->pluck('customer_id')->filter()->all() ?: [0])
            ->whereNotNull('industry')->where('industry', '!=', '')
            ->value('industry');

        $sales = $leads->filter(fn ($l) => (bool) $l->is_won)->map(function ($l) use ($accepted) {
            $q = $accepted->get($l->id)?->sortByDesc('total')->first();

            return [
                'lead_id'    => (int) $l->id,
                'project_id' => (int) $l->project_id,
                'closed_at'  => $l->closed_at?->toDateString(),
                'quotation'  => $q?->quote_number,
                'value'      => round((float) ($q?->total ?? $l->estimated_value ?? 0), 2),
                'owner'      => $l->owner,
            ];
        })->values();

        return [
            'customer' => $customer,
            'summary'  => [
                'email'           => $leads->pluck('email')->filter()->first(),
                'phone'           => $leads->pluck('phone')->filter()->first(),
                'industry'        => $industry,
                'inquiries'       => $leads->count(),
                'open'            => $leads->filter(fn ($l) => ! $l->is_won && ! $l->is_lost)->count(),
                'quotations'      => $quotations->count(),
                'quoted_value'    => round((float) $quotations->sum('total'), 2),
                'sales'           => $sales->count(),
                'sales_value'     => round((float) $sales->sum('value'), 2),
                'first_inquiry'   => $leads->last()?->created_at?->toDateString(),
                'last_inquiry'    => $leads->first()?->created_at?->toDateString(),
            ],
            'inquiries' => $leads->map(fn ($l) => [
                'lead_id'     => (int) $l->id,
                'project_id'  => (int) $l->project_id,
                'date'        => $l->created_at?->toDateString(),
                'contact'     => $l->name,
                'source'      => Lead::sourceLabel($l->source),
                'department'  => $l->department_id ? ($departments[$l->department_id] ?? null) : null,
                'stage'       => LeadStage::STATUSES[$l->system_key][0] ?? $l->stage ?? 'Open',
                'stage_color' => LeadStage::STATUSES[$l->system_key][1] ?? '#94a3b8',
                'owner'       => $l->owner,
                'value'       => round((float) $l->estimated_value, 2),
            ])->values()->all(),
            'quotations' => $quotations->map(fn (Quotation $q) => [
                'id'               => (int) $q->id,
                'lead_id'          => (int) $q->lead_id,
                'number'           => $q->quote_number,
                'version'          => (int) $q->version,
                'date'             => $q->created_at?->toDateString(),
                'sent_at'          => $q->sent_at?->toDateString(),
                'status'           => $q->statusLabel(),
                'status_color'     => $q->statusColor(),
                'discount_percent' => $q->discountPercent(),
                'total'            => round((float) $q->total, 2),
            ])->values()->all(),
            'sales' => $sales->all(),
        ];
    }

    // ── Scope & grouping ─────────────────────────────────────────────────

    /** @return array{status: ?string, search: ?string, industry: ?string, product_id: ?int} */
    private function extraFilters(array $input): array
    {
        $search   = trim((string) ($input['search'] ?? ''));
        $industry = trim((string) ($input['industry'] ?? ''));

        return [
            'status'     => isset(self::STATUSES[$input['status'] ?? '']) ? $input['status'] : null,
            'search'     => $search !== '' ? $search : null,
            'industry'   => $industry !== '' ? $industry : null,
            'product_id' => filled($input['product_id'] ?? null) ? (int) $input['product_id'] : null,
        ];
    }

    /** The leads in scope with every filter but the date, the stage joined as `s`. */
    private function scoped(Business $business, CrmAccess $access, array $filters, array $extra, bool $groupByIndustry): Builder
    {
        ['status' => $status, 'search' => $search, 'industry' => $industry, 'product_id' => $productId] = $extra;

        $query = $this->reports->leads($business, $access, $filters)
            ->leftJoin('crm_lead_stages as s', 's.id', '=', 'crm_leads.stage_id')
            ->when($status === 'won', fn (Builder $q) => $q->where('s.is_won', true))
            ->when($status === 'lost', fn (Builder $q) => $q->where('s.is_lost', true))
            ->when($status === 'open', fn (Builder $q) => $q->whereRaw('coalesce(s.is_won, 0) = 0 and coalesce(s.is_lost, 0) = 0'))
            ->when($search !== null, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('crm_leads.company', 'like', "%{$search}%")
                ->orWhere('crm_leads.name', 'like', "%{$search}%")
                ->orWhere('crm_leads.email', 'like', "%{$search}%")
                ->orWhere('crm_leads.phone', 'like', "%{$search}%")))
            // Asked for the product, or quoted it
            ->when($productId !== null, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereExists(fn ($e) => $e->from('crm_lead_products as fp')->whereColumn('fp.lead_id', 'crm_leads.id')->where('fp.product_id', $productId))
                ->orWhereExists(fn ($e) => $e->from('quotations as fq')
                    ->join('quotation_items as fqi', 'fqi.quotation_id', '=', 'fq.id')
                    ->whereColumn('fq.lead_id', 'crm_leads.id')
                    ->where('fq.status', '!=', Quotation::STATUS_SUPERSEDED)
                    ->where('fqi.product_id', $productId))));

        if ($groupByIndustry || $industry !== null) {
            $fromQuotations = DB::table('quotations as iqq')
                ->join('pos_customers as iqc', 'iqc.id', '=', 'iqq.customer_id')
                ->where('iqq.business_id', $business->id)
                ->whereNotNull('iqq.lead_id')
                ->whereNotNull('iqc.industry')
                ->where('iqc.industry', '!=', '')
                ->groupBy('iqq.lead_id')
                ->selectRaw('iqq.lead_id, max(trim(iqc.industry)) as industry');
            $query->leftJoin('pos_customers as ic', 'ic.id', '=', 'crm_leads.customer_id')
                ->leftJoinSub($fromQuotations, 'iq', 'iq.lead_id', '=', 'crm_leads.id')
                ->when($industry !== null, fn (Builder $q) => $q->whereRaw(self::INDUSTRY . ' = ?', [$industry]));
        }

        return $query;
    }

    /** The group key SQL; $dateColumn is the date the counted event happened (period rows). */
    private function groupKey(string $group, string $dateColumn, string $period): string
    {
        return match ($group) {
            'department' => 'crm_leads.department_id',
            'division'   => 'crm_leads.division_id',
            'staff'      => 'crm_leads.assigned_to',
            'source'     => 'crm_leads.source',
            'customer'   => self::CUSTOMER,
            'industry'   => self::INDUSTRY,
            'period'     => $this->bucketSql($dateColumn, $period),
            default      => 'crm_leads.department_id',
        };
    }

    /** Row label per group key ('' / null = none). @param  list<int|string|null>  $keys */
    private function labeler(Business $business, string $group, array $keys, array $buckets): Closure
    {
        $ids   = array_values(array_filter($keys, fn ($k) => $k !== null && $k !== ''));
        $names = match ($group) {
            'department' => Department::query()->where('business_id', $business->id)->pluck('name', 'id'),
            'division'   => Division::query()->where('business_id', $business->id)->pluck('name', 'id'),
            'staff'      => DB::table('users')->whereIn('id', $ids ?: [0])->pluck('name', 'id'),
            default      => collect(),
        };

        return function ($k) use ($group, $names, $buckets) {
            $k = $k === '' ? null : $k;

            return match ($group) {
                'department' => $k !== null ? ($names[$k] ?? 'Department #' . $k) : 'No department',
                'division'   => $k !== null ? ($names[$k] ?? 'Division #' . $k) : 'No division',
                'staff'      => $k !== null ? ($names[$k] ?? 'User #' . $k) : 'Unassigned',
                'source'     => Lead::sourceLabel($k) ?? 'Unknown',
                'industry'   => $k ?? 'Industry not set',
                'period'     => $buckets[$k]['label'] ?? (string) $k,
                default      => $k !== null ? (string) $k : 'Unnamed',
            };
        };
    }

    // ── Lead rows: the sales view ────────────────────────────────────────

    /** One row per department / division / customer / staff member / source / industry / period. */
    private function leadRows(Business $business, string $group, Builder $leads, string $key, array $filters, string $period): array
    {
        $sent = DB::table('quotations')
            ->where('business_id', $business->id)
            ->whereNotNull('lead_id')
            ->whereNotNull('sent_at')
            ->where('status', '!=', Quotation::STATUS_SUPERSEDED)
            ->groupBy('lead_id')
            ->selectRaw('lead_id, count(*) as n, sum(total) as value');
        $accepted = DB::table('quotations')
            ->whereNotNull('lead_id')
            ->where('status', Quotation::STATUS_ACCEPTED)
            ->groupBy('lead_id')
            ->selectRaw('lead_id, max(total) as won_total');

        $result = $leads
            ->leftJoinSub($sent, 'q', 'q.lead_id', '=', 'crm_leads.id')
            ->leftJoinSub($accepted, 'wq', 'wq.lead_id', '=', 'crm_leads.id')
            ->selectRaw("{$key} as k, count(*) as leads,
                sum(case when s.is_won = 1 then 1 else 0 end) as won,
                sum(case when s.is_lost = 1 then 1 else 0 end) as lost,
                sum(coalesce(crm_leads.estimated_value, 0)) as pipeline_value,
                sum(case when s.is_won = 1 then coalesce(wq.won_total, crm_leads.estimated_value, 0) else 0 end) as won_value,
                sum(coalesce(q.n, 0)) as quotations, sum(coalesce(q.value, 0)) as quoted_value,
                max(crm_leads.email) as email, max(crm_leads.phone) as phone, max(crm_leads.created_at) as last_lead")
            ->groupByRaw($key)
            ->orderByDesc('won_value')
            ->orderByDesc('leads')
            ->get()
            ->keyBy(fn ($r) => (string) $r->k);

        // Period rows: every day / week … of the range in order, empty ones too
        $buckets = $group === 'period' ? $this->buckets($filters, $period) : [];
        $keys    = array_map('strval', $group === 'period' ? array_keys($buckets) : $result->keys()->all());
        $label   = $this->labeler($business, $group, $keys, $buckets);
        $zero    = (object) ['leads' => 0, 'won' => 0, 'lost' => 0, 'pipeline_value' => 0, 'won_value' => 0, 'quotations' => 0,
            'quoted_value' => 0, 'email' => null, 'phone' => null, 'last_lead' => null];

        $rows = array_map(function (string $k) use ($result, $zero, $group, $label) {
            $r = $result->get($k, $zero);
            [$leads, $won, $lost, $wonValue] = [(int) $r->leads, (int) $r->won, (int) $r->lost, round((float) $r->won_value, 2)];

            return [
                'label'           => $label($k),
                ...($group === 'customer' ? ['email' => $r->email, 'phone' => $r->phone] : []),
                'leads'           => $leads,
                'open'            => $leads - $won - $lost,
                'won'             => $won,
                'lost'            => $lost,
                'conversion_rate' => $leads > 0 ? round($won / $leads * 100, 1) : 0.0,
                'pipeline_value'  => round((float) $r->pipeline_value, 2),
                'won_value'       => $wonValue,
                'avg_deal_value'  => $won > 0 ? round($wonValue / $won, 2) : null,
                'quotations'      => (int) $r->quotations,
                'quoted_value'    => round((float) $r->quoted_value, 2),
                ...($group === 'customer' ? ['last_lead' => substr((string) $r->last_lead, 0, 10)] : []),
            ];
        }, $keys);

        $columns = [
            ['key' => 'label', 'label' => self::GROUPS[$group], 'type' => 'text'],
            ...($group === 'customer' ? [['key' => 'email', 'label' => 'Email', 'type' => 'text'], ['key' => 'phone', 'label' => 'Phone', 'type' => 'text']] : []),
            ['key' => 'leads',           'label' => 'Leads',           'type' => 'int'],
            ['key' => 'open',            'label' => 'Open',            'type' => 'int'],
            ['key' => 'won',             'label' => 'Won',             'type' => 'int'],
            ['key' => 'lost',            'label' => 'Lost',            'type' => 'int'],
            ['key' => 'conversion_rate', 'label' => 'Conversion',      'type' => 'pct'],
            ['key' => 'pipeline_value',  'label' => 'Pipeline value',  'type' => 'money'],
            ['key' => 'won_value',       'label' => 'Won value',       'type' => 'money'],
            ['key' => 'avg_deal_value',  'label' => 'Avg deal',        'type' => 'money'],
            ['key' => 'quotations',      'label' => 'Quotations sent', 'type' => 'int'],
            ['key' => 'quoted_value',    'label' => 'Quoted value',    'type' => 'money'],
            ...($group === 'customer' ? [['key' => 'last_lead', 'label' => 'Last lead', 'type' => 'text']] : []),
        ];

        $totals = $this->sums($columns, $rows);
        $totals['conversion_rate'] = $totals['leads'] > 0 ? round($totals['won'] / $totals['leads'] * 100, 1) : 0.0;
        $totals['avg_deal_value']  = $totals['won'] > 0 ? round($totals['won_value'] / $totals['won'], 2) : null;

        return [$columns, $rows, $totals];
    }

    // ── Lead rows: the KPI view ──────────────────────────────────────────

    /** The inquiry-handling KPIs per group (see CrmKpiService for each measure). */
    private function kpiRows(Business $business, string $group, Closure $scoped, array $filters, Closure $key, string $period): array
    {
        $raw     = $this->kpis->raw($business->id, $scoped, $filters['from'], $filters['to'], $key);
        $days    = $this->days($filters);
        $buckets = $group === 'period' ? $this->buckets($filters, $period) : [];

        if ($group === 'period') {
            $keys = array_map('strval', array_keys($buckets));
        } else {
            uasort($raw, fn ($a, $b) => [$b['inquiries'], $b['quotations'], $b['won_value']] <=> [$a['inquiries'], $a['quotations'], $a['won_value']]);
            $keys = array_map('strval', array_keys($raw));
        }
        $label = $this->labeler($business, $group, $keys, $buckets);

        $rows = array_map(fn (string $k) => [
            'label' => $label($k),
            ...$this->kpis->present($raw[$k] ?? CrmKpiService::ZERO, $buckets[$k]['days'] ?? $days),
        ], $keys);

        $allocation = in_array($group, ['department', 'division'], true);
        $columns    = [
            ['key' => 'label',              'label' => self::GROUPS[$group], 'type' => 'text'],
            ['key' => 'inquiries',          'label' => 'Inquiries',       'type' => 'int',   'hint' => 'Inquiries received in the period'],
            ['key' => 'per_day',            'label' => 'Per day',         'type' => 'num',   'hint' => 'Inquiries received per day'],
            ...($allocation ? [
                ['key' => 'assigned',       'label' => 'Allocated',       'type' => 'int',   'hint' => 'Received inquiries given to a salesperson'],
                ['key' => 'unassigned',     'label' => 'Not allocated',   'type' => 'int',   'hint' => 'Received inquiries nobody owns'],
                ['key' => 'staff',          'label' => 'Staff',           'type' => 'int',   'hint' => 'People the inquiries were allocated to'],
            ] : []),
            ['key' => 'avg_response_hours', 'label' => 'Avg response',    'type' => 'hours', 'hint' => 'Received → first worked on (a message, follow-up, quotation or activity)'],
            ['key' => 'avg_contact_hours',  'label' => 'Avg first contact', 'type' => 'hours', 'hint' => 'Received → first call, email or meeting with the customer, follow-up done or quotation sent'],
            ['key' => 'follow_ups_done',    'label' => 'Follow-ups done', 'type' => 'int',   'hint' => 'Follow-ups logged as done in the period'],
            ['key' => 'quotations',         'label' => 'Quotations',      'type' => 'int',   'hint' => 'Quotations made in the period (a revised quotation counts once)'],
            ['key' => 'quotation_value',    'label' => 'Quoted value',    'type' => 'money', 'hint' => 'Total of those quotations'],
            ['key' => 'inquiry_quote_rate', 'label' => 'Inquiry → quote', 'type' => 'pct',   'hint' => 'Received inquiries that got a quotation'],
            ['key' => 'converted',          'label' => 'Converted',       'type' => 'int',   'hint' => 'Of the quotations made, those the customer accepted'],
            ['key' => 'quote_sale_rate',    'label' => 'Quote → sale',    'type' => 'pct',   'hint' => 'Converted ÷ quotations made'],
            ['key' => 'pending',            'label' => 'Pending',         'type' => 'int',   'hint' => 'Received inquiries still open'],
            ['key' => 'overdue',            'label' => 'Overdue',         'type' => 'int',   'hint' => 'Pending ones with an overdue follow-up, past the expected close date, or not worked on since before today'],
            ['key' => 'won',                'label' => 'Won',             'type' => 'int',   'hint' => 'Received inquiries now won'],
            ['key' => 'won_value',          'label' => 'Sales value',     'type' => 'money', 'hint' => 'Their accepted quotation, else the estimated value'],
        ];

        $totals = $this->kpis->present($this->kpis->sum($raw), $days);
        unset($totals['staff']); // the same person works for several rows

        return [$columns, $rows, $totals];
    }

    // ── Product rows ─────────────────────────────────────────────────────

    /** What the leads asked for and were quoted, per item — and per customer and item for preferences. */
    private function itemRows(Business $business, Builder $leads, bool $perCustomer): array
    {
        $item     = self::ITEM;
        $customer = str_replace('crm_leads.', 'l.', self::CUSTOMER);
        $groupBy  = $perCustomer ? "{$customer}, {$item}" : $item;

        $result = DB::table('quotation_items as qi')
            ->join('quotations as qt', 'qt.id', '=', 'qi.quotation_id')
            ->join('crm_leads as l', 'l.id', '=', 'qt.lead_id')
            ->leftJoin('products as p', 'p.id', '=', 'qi.product_id')
            ->leftJoin('service_items as si', 'si.id', '=', 'qi.service_item_id')
            ->where('qt.business_id', $business->id)
            ->whereIn('qt.lead_id', (clone $leads)->select('crm_leads.id'))
            ->whereNotNull('qt.sent_at')
            ->where('qt.status', '!=', Quotation::STATUS_SUPERSEDED)
            ->selectRaw(($perCustomer ? "{$customer} as customer, " : '') . "{$item} as item,
                count(distinct {$customer}) as customers, count(distinct qt.id) as quotations,
                sum(qi.quantity) as qty, sum(qi.line_total) as quoted_value,
                sum(case when qt.status = ? then qi.quantity else 0 end) as won_qty,
                sum(case when qt.status = ? then qi.line_total else 0 end) as won_value",
                [Quotation::STATUS_ACCEPTED, Quotation::STATUS_ACCEPTED])
            ->groupByRaw($groupBy)
            ->get();

        // Inquiry volume: how many of the leads asked for each product
        $interest = $perCustomer ? collect() : DB::table('crm_lead_products as lp')
            ->join('products as p', 'p.id', '=', 'lp.product_id')
            ->whereIn('lp.lead_id', (clone $leads)->select('crm_leads.id'))
            ->groupBy('p.name')
            ->selectRaw('p.name as item, count(distinct lp.lead_id) as inquiries')
            ->pluck('inquiries', 'item');

        // Share of each customer's quoted value, so the table reads as "what they prefer"
        $customerTotals = $perCustomer ? $result->groupBy('customer')->map(fn ($g) => $g->sum(fn ($r) => (float) $r->quoted_value)) : collect();

        $rows = $result->map(function ($r) use ($perCustomer, $customerTotals, $interest) {
            [$qty, $value, $wonValue] = [(float) $r->qty, round((float) $r->quoted_value, 2), round((float) $r->won_value, 2)];
            $customerTotal = $perCustomer ? (float) $customerTotals[$r->customer] : 0.0;

            return [
                ...($perCustomer ? ['customer' => $r->customer ?: 'Unnamed'] : []),
                'label'        => $r->item,
                ...($perCustomer ? [] : ['inquiries' => (int) ($interest[$r->item] ?? 0), 'customers' => (int) $r->customers]),
                'quotations'   => (int) $r->quotations,
                'qty'          => round($qty, 3),
                'quoted_value' => $value,
                'avg_price'    => $qty > 0 ? round($value / $qty, 2) : null,
                'won_qty'      => round((float) $r->won_qty, 3),
                'won_value'    => $wonValue,
                'win_share'    => $value > 0 ? round($wonValue / $value * 100, 1) : 0.0,
                ...($perCustomer ? ['share' => $customerTotal > 0 ? round($value / $customerTotal * 100, 1) : 0.0] : []),
            ];
        });
        // Products asked for but never quoted
        if (! $perCustomer) {
            $quoted = $rows->pluck('label')->flip();
            foreach ($interest as $name => $n) {
                if (! isset($quoted[$name])) {
                    $rows->push(['label' => $name, 'inquiries' => (int) $n, 'customers' => 0, 'quotations' => 0, 'qty' => 0.0, 'quoted_value' => 0.0,
                        'avg_price' => null, 'won_qty' => 0.0, 'won_value' => 0.0, 'win_share' => 0.0]);
                }
            }
        }
        $rows = ($perCustomer
            ? $rows->sortBy([['customer', 'asc'], ['quoted_value', 'desc']])
            : $rows->sortBy([['quoted_value', 'desc'], ['inquiries', 'desc'], ['qty', 'desc']]))->values()->all();

        $columns = [
            ...($perCustomer ? [['key' => 'customer', 'label' => 'Customer', 'type' => 'text']] : []),
            ['key' => 'label', 'label' => 'Product / service', 'type' => 'text'],
            ...($perCustomer ? [] : [
                ['key' => 'inquiries', 'label' => 'Inquiries', 'type' => 'int', 'hint' => 'Leads that asked for this product'],
                ['key' => 'customers', 'label' => 'Customers', 'type' => 'int'],
            ]),
            ['key' => 'quotations',   'label' => 'Quotations',      'type' => 'int'],
            ['key' => 'qty',          'label' => 'Qty quoted',      'type' => 'qty'],
            ['key' => 'quoted_value', 'label' => 'Quoted value',    'type' => 'money'],
            ['key' => 'avg_price',    'label' => 'Avg price',       'type' => 'money'],
            ['key' => 'won_qty',      'label' => 'Qty won',         'type' => 'qty'],
            ['key' => 'won_value',    'label' => 'Won value',       'type' => 'money'],
            ['key' => 'win_share',    'label' => 'Won % of quoted', 'type' => 'pct'],
            ...($perCustomer ? [['key' => 'share', 'label' => 'Share of customer', 'type' => 'pct']] : []),
        ];

        $totals = $this->sums($columns, $rows);
        unset($totals['inquiries'], $totals['customers'], $totals['quotations']); // the same lead / customer / quotation spans several rows
        $totals['avg_price'] = $totals['qty'] > 0 ? round($totals['quoted_value'] / $totals['qty'], 2) : null;
        $totals['win_share'] = $totals['quoted_value'] > 0 ? round($totals['won_value'] / $totals['quoted_value'] * 100, 1) : 0.0;

        return [$columns, $rows, $totals];
    }

    // ── Price rows ───────────────────────────────────────────────────────

    /**
     * Quotations made in the range (latest versions) by discount band. "Approved" / "rejected"
     * is the last approval decision on the quotation; "within limit" ones went out without
     * approval because the discount and value were inside the department's limits.
     */
    private function priceRows(Business $business, Builder $leads, array $filters): array
    {
        $pct  = '(coalesce(q.discount_amount, 0) * 100.0 / q.subtotal)';
        $band = "case when q.subtotal <= 0 or coalesce(q.discount_amount, 0) <= 0 then 0
            when {$pct} <= 5 then 1 when {$pct} <= 10 then 2 when {$pct} <= 15 then 3 when {$pct} <= 20 then 4 else 5 end";
        $decisions = DB::table('quotation_approvals')
            ->whereIn('action', [QuotationApproval::ACTION_APPROVED, QuotationApproval::ACTION_REJECTED, QuotationApproval::ACTION_WITHIN_LIMIT])
            ->groupBy('quotation_id')
            ->selectRaw('quotation_id, max(id) as last_id');

        $result = DB::table('quotations as q')
            ->leftJoinSub($decisions, 'd', 'd.quotation_id', '=', 'q.id')
            ->leftJoin('quotation_approvals as qa', 'qa.id', '=', 'd.last_id')
            ->where('q.business_id', $business->id)
            ->whereIn('q.lead_id', $leads->select('crm_leads.id'))
            ->where('q.status', '!=', Quotation::STATUS_SUPERSEDED)
            ->whereBetween('q.created_at', [$filters['from'], $filters['to']])
            ->selectRaw("{$band} as band, count(*) as n, sum(q.subtotal) as subtotal, sum(coalesce(q.discount_amount, 0)) as discount,
                sum(q.total) as total,
                sum(case when qa.action = ? then 1 else 0 end) as approved,
                sum(case when qa.action = ? then 1 else 0 end) as rejected,
                sum(case when qa.action = ? then 1 else 0 end) as within_limit,
                sum(case when q.status = ? then 1 else 0 end) as pending,
                sum(case when q.status = ? then 1 else 0 end) as sold,
                sum(case when q.status = ? then q.total else 0 end) as sales_value",
                [QuotationApproval::ACTION_APPROVED, QuotationApproval::ACTION_REJECTED, QuotationApproval::ACTION_WITHIN_LIMIT,
                    Quotation::STATUS_PENDING_APPROVAL, Quotation::STATUS_ACCEPTED, Quotation::STATUS_ACCEPTED])
            ->groupByRaw($band)
            ->get()
            ->keyBy(fn ($r) => (int) $r->band);

        $rows = [];
        foreach (self::DISCOUNT_BANDS as $band => $label) {
            $r = $result->get($band);
            [$n, $subtotal, $discount, $total, $sold] = [(int) ($r->n ?? 0), (float) ($r->subtotal ?? 0), (float) ($r->discount ?? 0), (float) ($r->total ?? 0), (int) ($r->sold ?? 0)];
            $rows[] = [
                'label'            => $label,
                'quotations'       => $n,
                'subtotal'         => round($subtotal, 2),
                'discount'         => round($discount, 2),
                'discount_percent' => $subtotal > 0 ? round($discount / $subtotal * 100, 1) : 0.0,
                'quoted_value'     => round($total, 2),
                'avg_value'        => $n > 0 ? round($total / $n, 2) : null,
                'approved'         => (int) ($r->approved ?? 0),
                'rejected'         => (int) ($r->rejected ?? 0),
                'within_limit'     => (int) ($r->within_limit ?? 0),
                'pending'          => (int) ($r->pending ?? 0),
                'sold'             => $sold,
                'sales_value'      => round((float) ($r->sales_value ?? 0), 2),
                'sale_rate'        => $n > 0 ? round($sold / $n * 100, 1) : 0.0,
            ];
        }

        $columns = [
            ['key' => 'label',            'label' => 'Discount',          'type' => 'text'],
            ['key' => 'quotations',       'label' => 'Quotations',        'type' => 'int',   'hint' => 'Quotations made in the period (latest versions)'],
            ['key' => 'subtotal',         'label' => 'Before discount',   'type' => 'money'],
            ['key' => 'discount',         'label' => 'Discount given',    'type' => 'money'],
            ['key' => 'discount_percent', 'label' => 'Avg discount',      'type' => 'pct',   'hint' => 'Discount given ÷ value before discount'],
            ['key' => 'quoted_value',     'label' => 'Quoted value',      'type' => 'money'],
            ['key' => 'avg_value',        'label' => 'Avg quotation',     'type' => 'money'],
            ['key' => 'approved',         'label' => 'Approved',          'type' => 'int',   'hint' => 'The head approved the discount'],
            ['key' => 'rejected',         'label' => 'Rejected',          'type' => 'int',   'hint' => 'The head rejected the discount'],
            ['key' => 'within_limit',     'label' => 'Within limit',      'type' => 'int',   'hint' => 'Inside the department’s limits — no approval needed'],
            ['key' => 'pending',          'label' => 'Awaiting approval', 'type' => 'int'],
            ['key' => 'sold',             'label' => 'Sold',              'type' => 'int',   'hint' => 'Accepted by the customer'],
            ['key' => 'sales_value',      'label' => 'Sales value',       'type' => 'money'],
            ['key' => 'sale_rate',        'label' => 'Quote → sale',      'type' => 'pct'],
        ];

        $totals = $this->sums($columns, $rows);
        $totals['discount_percent'] = $totals['subtotal'] > 0 ? round($totals['discount'] / $totals['subtotal'] * 100, 1) : 0.0;
        $totals['avg_value']        = $totals['quotations'] > 0 ? round($totals['quoted_value'] / $totals['quotations'], 2) : null;
        $totals['sale_rate']        = $totals['quotations'] > 0 ? round($totals['sold'] / $totals['quotations'] * 100, 1) : 0.0;

        return [$columns, $rows, $totals];
    }

    // ── Periods ──────────────────────────────────────────────────────────

    private function defaultPeriod(array $filters): string
    {
        $days = $this->days($filters);

        return match (true) {
            $days <= 31  => 'day',
            $days <= 120 => 'week',
            $days <= 731 => 'month',
            default      => 'year',
        };
    }

    private function days(array $filters): int
    {
        return (int) round($filters['from']->copy()->startOfDay()->diffInDays($filters['to']->copy()->startOfDay())) + 1;
    }

    /**
     * The days / weeks (from Monday) / months / years the range covers, in order, each with its
     * label and the number of its days inside the range.
     *
     * @return array<string, array{label: string, days: int}>  key as bucketSql() makes it
     */
    private function buckets(array $filters, string $period): array
    {
        [$from, $to] = [$filters['from'], $filters['to']];
        $cursor = match ($period) {
            'week'  => $from->copy()->startOfWeek(Carbon::MONDAY),
            'month' => $from->copy()->startOfMonth(),
            'year'  => $from->copy()->startOfYear(),
            default => $from->copy()->startOfDay(),
        };

        $out = [];
        while ($cursor->lte($to)) {
            $next  = match ($period) {
                'week'  => $cursor->copy()->addWeek(),
                'month' => $cursor->copy()->addMonthNoOverflow(),
                'year'  => $cursor->copy()->addYear(),
                default => $cursor->copy()->addDay(),
            };
            $start = $cursor->lt($from) ? $from->copy()->startOfDay() : $cursor->copy();
            $end   = $next->copy()->subDay()->startOfDay();
            $end   = $end->gt($to) ? $to->copy()->startOfDay() : $end;
            $key   = match ($period) {
                'month' => $cursor->format('Y-m'),
                'year'  => $cursor->format('Y'),
                default => $cursor->format('Y-m-d'),
            };
            $out[$key] = [
                'label' => match ($period) {
                    'week'  => 'Week of ' . $cursor->format('j M Y'),
                    'month' => $cursor->format('F Y'),
                    'year'  => $cursor->format('Y'),
                    default => $cursor->format('D, j M Y'),
                },
                'days'  => (int) round($start->diffInDays($end)) + 1,
            ];
            $cursor = $next;
        }

        return $out;
    }

    /** SQL putting a timestamp into its day / week (Monday) / month / year, keyed like buckets(). */
    private function bucketSql(string $column, string $period): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => match ($period) {
                'week'  => "strftime('%Y-%m-%d', {$column}, 'weekday 0', '-6 days')",
                'month' => "strftime('%Y-%m', {$column})",
                'year'  => "strftime('%Y', {$column})",
                default => "strftime('%Y-%m-%d', {$column})",
            },
            'pgsql' => match ($period) {
                'week'  => "to_char(date_trunc('week', {$column}), 'YYYY-MM-DD')",
                'month' => "to_char({$column}, 'YYYY-MM')",
                'year'  => "to_char({$column}, 'YYYY')",
                default => "to_char({$column}, 'YYYY-MM-DD')",
            },
            default => match ($period) {
                'week'  => "date_format(date_sub({$column}, interval weekday({$column}) day), '%Y-%m-%d')",
                'month' => "date_format({$column}, '%Y-%m')",
                'year'  => "date_format({$column}, '%Y')",
                default => "date_format({$column}, '%Y-%m-%d')",
            },
        };
    }

    // ── Filter options ───────────────────────────────────────────────────

    /** @return list<string> the industries saved on this business's customers */
    private function industryOptions(Business $business): array
    {
        return DB::table('pos_customers')
            ->where('business_id', $business->id)
            ->whereNotNull('industry')
            ->where('industry', '!=', '')
            ->selectRaw('distinct trim(industry) as industry')
            ->orderBy('industry')
            ->limit(300)
            ->pluck('industry')
            ->all();
    }

    /** @return list<array{id: int, name: string}> the products leads asked for or were quoted */
    private function productOptions(Business $business): array
    {
        $ids = DB::table('crm_lead_products as lp')
            ->join('crm_leads as l', 'l.id', '=', 'lp.lead_id')
            ->where('l.business_id', $business->id)
            ->distinct()
            ->pluck('lp.product_id')
            ->merge(DB::table('quotation_items as qi')
                ->join('quotations as q', 'q.id', '=', 'qi.quotation_id')
                ->where('q.business_id', $business->id)
                ->whereNotNull('q.lead_id')
                ->whereNotNull('qi.product_id')
                ->distinct()
                ->pluck('qi.product_id'))
            ->unique()
            ->values()
            ->all();

        return DB::table('products')
            ->whereIn('id', $ids ?: [0])
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name'])
            ->map(fn ($p) => ['id' => (int) $p->id, 'name' => $p->name])
            ->all();
    }

    /** Column sums for the int / qty / money columns. */
    private function sums(array $columns, array $rows): array
    {
        $totals = [];
        foreach ($columns as $c) {
            if (in_array($c['type'], ['int', 'qty', 'money'], true)) {
                $sum = array_sum(array_map(fn ($r) => (float) ($r[$c['key']] ?? 0), $rows));
                $totals[$c['key']] = $c['type'] === 'int' ? (int) $sum : round($sum, $c['type'] === 'qty' ? 3 : 2);
            }
        }

        return $totals;
    }
}
