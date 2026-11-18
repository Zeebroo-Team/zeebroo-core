<?php

namespace Modules\CRM\Services;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\FollowUp;
use Modules\CRM\Models\Lead;
use Modules\Sales\Models\Quotation;

/**
 * The inquiry-handling KPIs (admin reporting 3.1), per group or for the whole scope. Used by
 * the sales analysis report's "KPIs" view (per department / staff / customer / period …) and
 * by the dashboard (one total row).
 *
 * Inquiries received in the period (by created date), and what has happened to them since:
 *   response time        received → first response (anyone working the lead: a message,
 *                        a follow-up, a quotation, an activity — Lead::recordFirstResponse)
 *   first contact time   received → the first real contact with the customer: a call / email /
 *                        meeting logged on the lead, a follow-up logged done, or a quotation sent
 *   inquiry → quotation  received inquiries that got at least one quotation ÷ received
 *   pending              received inquiries still open (neither won nor lost)
 *   overdue              pending ones that are late: an overdue follow-up, past their expected
 *                        close date, or received before today and still not worked by anyone
 *   won / sales value    received inquiries now won; value = accepted quotation, else estimate
 *
 * Work done in the period (by the date it was done, on any in-scope inquiry):
 *   follow-ups done      follow-ups logged done in the period
 *   quotations           quotations made in the period — the latest version of each, so a
 *                        revised quotation counts once
 *   converted            of those, the ones the customer accepted (a sale)
 *   quotation → sale     converted ÷ quotations
 *
 * Raw sums / counts come back per group so totals can be added up and averages weighted.
 */
class CrmKpiService
{
    /** Activities that mean the customer was contacted (notes and the inbound enquiry are not). */
    public const CONTACT_ACTIVITIES = [Activity::TYPE_CALL, Activity::TYPE_EMAIL, Activity::TYPE_MEETING];

    /** Raw figures per group; the zero row is the shape of each. */
    public const ZERO = [
        'inquiries' => 0, 'resp_s' => 0.0, 'resp_n' => 0, 'contact_s' => 0.0, 'contact_n' => 0, 'quoted' => 0,
        'pending' => 0, 'overdue' => 0, 'won' => 0, 'won_value' => 0.0, 'assigned' => 0, 'staff' => 0,
        'follow_ups_done' => 0, 'quotations' => 0, 'quotation_value' => 0.0, 'converted' => 0, 'converted_value' => 0.0,
    ];

    public function __construct(private readonly CrmSettingsService $settings) {}

    /**
     * @param  Closure(): Builder  $scoped  a fresh crm_leads query with the report's scope and filters
     *                                      applied and the stage joined as `s`; no date limit
     * @param  Closure(string): ?string  $key  the group key SQL for an event dated by the given column;
     *                                         null for one row of everything (key 'all')
     * @return array<string, array<string, int|float>>  group key ('' for none) => raw figures (see ZERO)
     */
    public function raw(int $businessId, Closure $scoped, Carbon $from, Carbon $to, Closure $key): array
    {
        $out = [];
        $put = function (string $k, array $values) use (&$out) {
            $out[$k] = [...($out[$k] ?? self::ZERO), ...$values];
        };

        foreach ($this->received($businessId, $scoped(), $from, $to, $key('crm_leads.created_at')) as $r) {
            $put((string) $r->k, [
                'inquiries' => (int) $r->inquiries,
                'resp_s'    => (float) $r->resp_s,    'resp_n'    => (int) $r->resp_n,
                'contact_s' => (float) $r->contact_s, 'contact_n' => (int) $r->contact_n,
                'quoted'    => (int) $r->quoted,
                'pending'   => (int) $r->pending,     'overdue'   => (int) $r->overdue,
                'won'       => (int) $r->won,         'won_value' => round((float) $r->won_value, 2),
                'assigned'  => (int) $r->assigned,    'staff'     => (int) $r->staff,
            ]);
        }

        $k = $key('f.done_at');
        $scoped()
            ->join('crm_follow_ups as f', 'f.lead_id', '=', 'crm_leads.id')
            ->where('f.status', FollowUp::STATUS_DONE)
            ->whereBetween('f.done_at', [$from, $to])
            ->selectRaw(($k ?? "'all'") . ' as k, count(*) as n')
            ->when($k !== null, fn ($q) => $q->groupByRaw($k))
            ->get()
            ->each(fn ($r) => $put((string) $r->k, ['follow_ups_done' => (int) $r->n]));

        $k = $key('q.created_at');
        $scoped()
            ->join('quotations as q', 'q.lead_id', '=', 'crm_leads.id')
            ->where('q.business_id', $businessId)
            ->where('q.status', '!=', Quotation::STATUS_SUPERSEDED)
            ->whereBetween('q.created_at', [$from, $to])
            ->selectRaw(($k ?? "'all'") . " as k, count(*) as n, sum(q.total) as value,
                sum(case when q.status = ? then 1 else 0 end) as converted,
                sum(case when q.status = ? then q.total else 0 end) as converted_value",
                [Quotation::STATUS_ACCEPTED, Quotation::STATUS_ACCEPTED])
            ->when($k !== null, fn ($q) => $q->groupByRaw($k))
            ->get()
            ->each(fn ($r) => $put((string) $r->k, [
                'quotations'      => (int) $r->n,
                'quotation_value' => round((float) $r->value, 2),
                'converted'       => (int) $r->converted,
                'converted_value' => round((float) $r->converted_value, 2),
            ]));

        return $out;
    }

    /**
     * The KPI figures from raw sums.
     *
     * @param  array<string, int|float>  $raw
     * @param  int  $days  days the figures cover, for "per day"
     */
    public function present(array $raw, int $days): array
    {
        $raw = [...self::ZERO, ...$raw];

        return [
            'inquiries'          => $raw['inquiries'],
            'per_day'            => round($raw['inquiries'] / max(1, $days), 1),
            'avg_response_hours' => $raw['resp_n'] > 0 ? round($raw['resp_s'] / $raw['resp_n'] / 3600, 1) : null,
            'avg_contact_hours'  => $raw['contact_n'] > 0 ? round($raw['contact_s'] / $raw['contact_n'] / 3600, 1) : null,
            'assigned'           => $raw['assigned'],
            'unassigned'         => $raw['inquiries'] - $raw['assigned'],
            'staff'              => $raw['staff'],
            'follow_ups_done'    => $raw['follow_ups_done'],
            'quotations'         => $raw['quotations'],
            'quotation_value'    => round($raw['quotation_value'], 2),
            'quoted_inquiries'   => $raw['quoted'],
            'inquiry_quote_rate' => $raw['inquiries'] > 0 ? round($raw['quoted'] / $raw['inquiries'] * 100, 1) : 0.0,
            'converted'          => $raw['converted'],
            'converted_value'    => round($raw['converted_value'], 2),
            'quote_sale_rate'    => $raw['quotations'] > 0 ? round($raw['converted'] / $raw['quotations'] * 100, 1) : 0.0,
            'pending'            => $raw['pending'],
            'overdue'            => $raw['overdue'],
            'won'                => $raw['won'],
            'won_value'          => round($raw['won_value'], 2),
        ];
    }

    /** Adds raw rows together (distinct staff cannot be added, so it is left out). */
    public function sum(array $rows): array
    {
        $total = self::ZERO;
        foreach ($rows as $row) {
            foreach ($total as $field => $v) {
                $total[$field] = $v + ($row[$field] ?? 0);
            }
        }
        $total['staff'] = 0;

        return $total;
    }

    /** Inquiries received in the period, per group. */
    private function received(int $businessId, Builder $scoped, Carbon $from, Carbon $to, ?string $k): \Illuminate\Support\Collection
    {
        $startOfToday = $this->settings->startOfToday($businessId);
        $quoted = DB::table('quotations')
            ->where('business_id', $businessId)
            ->whereNotNull('lead_id')
            ->select('lead_id')
            ->distinct();
        $overdueFollowUps = DB::table('crm_follow_ups')
            ->where('business_id', $businessId)
            ->where('status', FollowUp::STATUS_PENDING)
            ->where('due_at', '<', $startOfToday)
            ->select('lead_id')
            ->distinct();
        $accepted = DB::table('quotations')
            ->where('business_id', $businessId)
            ->whereNotNull('lead_id')
            ->where('status', Quotation::STATUS_ACCEPTED)
            ->groupBy('lead_id')
            ->selectRaw('lead_id, max(total) as won_total');

        $open     = 'coalesce(s.is_won, 0) = 0 and coalesce(s.is_lost, 0) = 0';
        $response = self::secondsBetween('crm_leads.created_at', 'crm_leads.first_response_at');
        $contact  = self::secondsBetween('crm_leads.created_at', 'fc.contacted_at');

        return $scoped
            ->whereBetween('crm_leads.created_at', [$from, $to])
            ->leftJoinSub($this->firstContacts($businessId), 'fc', 'fc.lead_id', '=', 'crm_leads.id')
            ->leftJoinSub($quoted, 'ql', 'ql.lead_id', '=', 'crm_leads.id')
            ->leftJoinSub($overdueFollowUps, 'ofu', 'ofu.lead_id', '=', 'crm_leads.id')
            ->leftJoinSub($accepted, 'wq', 'wq.lead_id', '=', 'crm_leads.id')
            ->selectRaw(($k ?? "'all'") . " as k, count(*) as inquiries,
                sum(case when crm_leads.first_response_at >= crm_leads.created_at then {$response} else 0 end) as resp_s,
                sum(case when crm_leads.first_response_at >= crm_leads.created_at then 1 else 0 end) as resp_n,
                sum(case when fc.contacted_at >= crm_leads.created_at then {$contact} else 0 end) as contact_s,
                sum(case when fc.contacted_at >= crm_leads.created_at then 1 else 0 end) as contact_n,
                sum(case when ql.lead_id is not null then 1 else 0 end) as quoted,
                sum(case when {$open} then 1 else 0 end) as pending,
                sum(case when {$open} and (ofu.lead_id is not null or crm_leads.expected_close_date < ?
                    or (crm_leads.first_response_at is null and crm_leads.created_at < ?)) then 1 else 0 end) as overdue,
                sum(case when s.is_won = 1 then 1 else 0 end) as won,
                sum(case when s.is_won = 1 then coalesce(wq.won_total, crm_leads.estimated_value, 0) else 0 end) as won_value,
                sum(case when crm_leads.assigned_to is not null then 1 else 0 end) as assigned,
                count(distinct crm_leads.assigned_to) as staff",
                [$startOfToday->toDateString(), $startOfToday])
            ->when($k !== null, fn ($q) => $q->groupByRaw($k))
            ->get();
    }

    /** lead_id → when the customer was first contacted (see the class comment). */
    private function firstContacts(int $businessId): QueryBuilder
    {
        $activities = DB::table('crm_activities')
            ->where('business_id', $businessId)
            ->where('subject_type', Lead::class)
            ->whereIn('type', self::CONTACT_ACTIVITIES)
            ->selectRaw('subject_id as lead_id, occurred_at as at');
        $followUps = DB::table('crm_follow_ups')
            ->where('business_id', $businessId)
            ->where('status', FollowUp::STATUS_DONE)
            ->whereNotNull('done_at')
            ->selectRaw('lead_id, done_at as at');
        $quotations = DB::table('quotations')
            ->where('business_id', $businessId)
            ->whereNotNull('lead_id')
            ->whereNotNull('sent_at')
            ->selectRaw('lead_id, sent_at as at');

        return DB::query()
            ->fromSub($activities->unionAll($followUps)->unionAll($quotations), 'c')
            ->groupBy('lead_id')
            ->selectRaw('lead_id, min(at) as contacted_at');
    }

    public static function secondsBetween(string $from, string $to): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "((julianday({$to}) - julianday({$from})) * 86400)",
            'pgsql'  => "extract(epoch from ({$to} - {$from}))",
            default  => "timestampdiff(second, {$from}, {$to})",
        };
    }
}
