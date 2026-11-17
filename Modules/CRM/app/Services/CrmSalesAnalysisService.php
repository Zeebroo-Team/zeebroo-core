<?php

namespace Modules\CRM\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Division;
use Modules\CRM\Models\Lead;
use Modules\HRManagement\Models\Department;
use Modules\Sales\Models\Quotation;

/**
 * The CRM sales analysis report (ribbon → CRM → Report): one table of the filtered leads,
 * grouped the way the user picks. Same access and filters as the dashboard (CrmReportService),
 * plus status (open / won / lost) and a customer search.
 *
 *   department / division / customer / staff / source
 *       leads created in the range, per group: open, won, lost, conversion, estimated pipeline,
 *       won value (accepted quotation total, else estimated value), quotations sent and their value
 *   product
 *       what those leads were quoted (latest sent versions): customers, quantity and value,
 *       and how much of it was accepted
 *   customer_product
 *       the same per customer and item: what each company prefers, and its share of their quotes
 */
class CrmSalesAnalysisService
{
    public const GROUPS = [
        'department'       => 'Department',
        'division'         => 'Division',
        'customer'         => 'Customer',
        'staff'            => 'Staff',
        'source'           => 'Source',
        'product'          => 'Product / service',
        'customer_product' => 'Customer preferences',
    ];

    public const STATUSES = ['open' => 'Open', 'won' => 'Won', 'lost' => 'Lost'];

    private const CUSTOMER = "coalesce(nullif(trim(crm_leads.company), ''), crm_leads.name)";
    private const ITEM     = "coalesce(p.name, si.name, qi.description, 'Unnamed item')";

    public function __construct(private readonly CrmReportService $reports) {}

    /**
     * @param  array{group_by?: ?string, status?: ?string, search?: ?string}  $input  plus the dashboard filters
     * @return array{group_by: string, group_label: string, filters: array, columns: list<array{key: string, label: string, type: string}>, rows: list<array>, totals: array, options: array}
     */
    public function report(Business $business, CrmAccess $access, array $input): array
    {
        $filters = $this->reports->filters($business, $access, $input);
        $group   = ($input['group_by'] ?? null) ?: 'department';
        $status  = isset(self::STATUSES[$input['status'] ?? '']) ? $input['status'] : null;
        $search  = trim((string) ($input['search'] ?? ''));
        if (! isset(self::GROUPS[$group])) {
            throw ValidationException::withMessages(['group_by' => 'Pick what to group the report by.']);
        }

        $leads = $this->reports->leads($business, $access, $filters)
            ->whereBetween('crm_leads.created_at', [$filters['from'], $filters['to']])
            ->leftJoin('crm_lead_stages as s', 's.id', '=', 'crm_leads.stage_id')
            ->when($status === 'won', fn (Builder $q) => $q->where('s.is_won', true))
            ->when($status === 'lost', fn (Builder $q) => $q->where('s.is_lost', true))
            ->when($status === 'open', fn (Builder $q) => $q->whereRaw('coalesce(s.is_won, 0) = 0 and coalesce(s.is_lost, 0) = 0'))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('crm_leads.company', 'like', "%{$search}%")
                ->orWhere('crm_leads.name', 'like', "%{$search}%")
                ->orWhere('crm_leads.email', 'like', "%{$search}%")
                ->orWhere('crm_leads.phone', 'like', "%{$search}%")));

        [$columns, $rows, $totals] = match ($group) {
            'product', 'customer_product' => $this->itemRows($business, $leads, $group === 'customer_product'),
            default                       => $this->leadRows($business, $leads, $group),
        };

        return [
            'group_by'    => $group,
            'group_label' => self::GROUPS[$group],
            'filters'     => [
                'from'          => $filters['from']->toDateString(),
                'to'            => $filters['to']->toDateString(),
                'department_id' => $filters['department_id'],
                'division_id'   => $filters['division_id'],
                'user_id'       => $filters['user_id'],
                'source'        => $filters['source'],
                'status'        => $status,
                'search'        => $search !== '' ? $search : null,
            ],
            'columns' => $columns,
            'rows'    => $rows,
            'totals'  => $totals,
            'options' => [
                ...$this->reports->options($business, $access),
                'groups'   => collect(self::GROUPS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
                'statuses' => collect(self::STATUSES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            ],
        ];
    }

    /** One row per department / division / customer / staff member / source. */
    private function leadRows(Business $business, Builder $leads, string $group): array
    {
        $key = match ($group) {
            'department' => 'crm_leads.department_id',
            'division'   => 'crm_leads.division_id',
            'staff'      => 'crm_leads.assigned_to',
            'source'     => 'crm_leads.source',
            'customer'   => self::CUSTOMER,
        };
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
            ->get();

        $names = match ($group) {
            'department' => Department::query()->where('business_id', $business->id)->pluck('name', 'id'),
            'division'   => Division::query()->where('business_id', $business->id)->pluck('name', 'id'),
            'staff'      => DB::table('users')->whereIn('id', $result->pluck('k')->filter())->pluck('name', 'id'),
            default      => collect(),
        };
        $label = fn ($k) => match ($group) {
            'department' => $k !== null ? ($names[$k] ?? 'Department #' . $k) : 'No department',
            'division'   => $k !== null ? ($names[$k] ?? 'Division #' . $k) : 'No division',
            'staff'      => $k !== null ? ($names[$k] ?? 'User #' . $k) : 'Unassigned',
            'source'     => Lead::sourceLabel($k) ?? 'Unknown',
            'customer'   => $k ?: 'Unnamed',
        };

        $rows = $result->map(function ($r) use ($group, $label) {
            [$leads, $won, $lost, $wonValue] = [(int) $r->leads, (int) $r->won, (int) $r->lost, round((float) $r->won_value, 2)];

            return [
                'label'           => $label($r->k),
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
        })->all();

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

    /** What the leads were quoted, per item — and per customer and item for preferences. */
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
            ->whereIn('qt.lead_id', $leads->select('crm_leads.id'))
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

        // Share of each customer's quoted value, so the table reads as "what they prefer"
        $customerTotals = $perCustomer ? $result->groupBy('customer')->map(fn ($g) => $g->sum(fn ($r) => (float) $r->quoted_value)) : collect();

        $rows = $result->map(function ($r) use ($perCustomer, $customerTotals) {
            [$qty, $value, $wonValue] = [(float) $r->qty, round((float) $r->quoted_value, 2), round((float) $r->won_value, 2)];
            $customerTotal = $perCustomer ? (float) $customerTotals[$r->customer] : 0.0;

            return [
                ...($perCustomer ? ['customer' => $r->customer ?: 'Unnamed'] : []),
                'label'        => $r->item,
                ...($perCustomer ? [] : ['customers' => (int) $r->customers]),
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
        $rows = ($perCustomer
            ? $rows->sortBy([['customer', 'asc'], ['quoted_value', 'desc']])
            : $rows->sortBy([['quoted_value', 'desc'], ['qty', 'desc']]))->values()->all();

        $columns = [
            ...($perCustomer ? [['key' => 'customer', 'label' => 'Customer', 'type' => 'text']] : []),
            ['key' => 'label', 'label' => 'Product / service', 'type' => 'text'],
            ...($perCustomer ? [] : [['key' => 'customers', 'label' => 'Customers', 'type' => 'int']]),
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
        unset($totals['customers'], $totals['quotations']); // the same customer / quotation spans several rows
        $totals['avg_price'] = $totals['qty'] > 0 ? round($totals['quoted_value'] / $totals['qty'], 2) : null;
        $totals['win_share'] = $totals['quoted_value'] > 0 ? round($totals['won_value'] / $totals['quoted_value'] * 100, 1) : 0.0;

        return [$columns, $rows, $totals];
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
