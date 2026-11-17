<?php

namespace Modules\CRM\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Division;
use Modules\CRM\Models\FollowUp;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadAssignmentLog;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\LostReason;
use Modules\HRManagement\Models\Department;
use Modules\Sales\Models\Quotation;

/**
 * The view-only management dashboard. Access: admins, the CEO and "All Departments" see the
 * whole business; department / division heads only what they head. Everything is grouped SQL
 * on the filtered leads — nothing loads lead rows.
 *
 * Filters: from / to (lead created date, default the last 30 days), department_id,
 * division_id, user_id (primary assignee) and source (a Lead::SOURCES key).
 *
 *   conversion rate   leads created in the range that are now won ÷ leads created in the range
 *   quotation values  latest versions (superseded ones left out) sent in the range: all sent,
 *                     accepted (won), rejected (lost)
 *   response time     first assignment → first_response_at, for leads created in the range
 *   close time        created → closed_at, for leads closed in the range
 *   won value         per won lead: its accepted quotation's total, else its estimated value
 *
 *   win rate          won ÷ (won + lost) among leads closed in the range
 *   previous          the headline figures for the equally long period just before, for deltas
 *
 * Pending approvals, overdue follow-ups, the open pipeline and the top open deals are
 * "right now", not limited to the date range.
 */
class CrmReportService
{
    /** @param  string  $permission  reports_view for the dashboard, reports_sales for the sales analysis report */
    public function canView(CrmAccess $access, string $permission = 'reports_view'): bool
    {
        return $access->hasCrm() && ($access->viewsAllLeads() || $access->isHead()) && $access->can($permission);
    }

    /**
     * @param  array{from?: ?string, to?: ?string, department_id?: int|string|null, division_id?: int|string|null, user_id?: int|string|null, source?: ?string}  $input
     * @return array<string, mixed>
     */
    public function dashboard(Business $business, CrmAccess $access, array $input): array
    {
        $filters = $this->filters($business, $access, $input);
        [$from, $to] = [$filters['from'], $filters['to']];

        $created = fn () => $this->leads($business, $access, $filters)->whereBetween('crm_leads.created_at', [$from, $to]);
        $closed  = fn () => $this->leads($business, $access, $filters)->whereBetween('crm_leads.closed_at', [$from, $to]);

        $totals = $created()
            ->leftJoin('crm_lead_stages as s', 's.id', '=', 'crm_leads.stage_id')
            ->selectRaw('count(*) as leads, sum(case when s.is_won = 1 then 1 else 0 end) as won, sum(case when s.is_lost = 1 then 1 else 0 end) as lost, sum(coalesce(crm_leads.estimated_value, 0)) as pipeline_value')
            ->first();
        $leadsCount = (int) $totals->leads;
        $wonCount   = (int) $totals->won;

        $wonInRange = $this->withWonValue($closed()->whereIn('crm_leads.stage_id', $this->terminalStageIds($business, 'is_won')))
            ->selectRaw('count(*) as n, sum(coalesce(wq.won_total, crm_leads.estimated_value, 0)) as value')
            ->first();
        $lostInRange = $closed()->whereIn('crm_leads.stage_id', $this->terminalStageIds($business, 'is_lost'))->count();
        $wonN        = (int) $wonInRange->n;
        $wonValue    = round((float) $wonInRange->value, 2);
        $quotations  = $this->quotationValues($business, $access, $filters);

        return [
            'filters' => [
                'from'          => $from->toDateString(),
                'to'            => $to->toDateString(),
                'department_id' => $filters['department_id'],
                'division_id'   => $filters['division_id'],
                'user_id'       => $filters['user_id'],
                'source'        => $filters['source'],
            ],
            'kpis' => [
                'leads'               => $leadsCount,
                'won'                 => $wonCount,
                'lost'                => (int) $totals->lost,
                'open'                => $leadsCount - $wonCount - (int) $totals->lost,
                'conversion_rate'     => $leadsCount > 0 ? round($wonCount / $leadsCount * 100, 1) : 0.0,
                'pipeline_value'      => round((float) $totals->pipeline_value, 2),
                'won_in_range'        => $wonN,
                'won_value_in_range'  => $wonValue,
                'lost_in_range'       => $lostInRange,
                'win_rate'            => $wonN + $lostInRange > 0 ? round($wonN / ($wonN + $lostInRange) * 100, 1) : null,
                'avg_deal_value'      => $wonN > 0 ? round($wonValue / $wonN, 2) : null,
                'avg_response_hours'  => $this->averageResponseHours($created()),
                'avg_close_days'      => $this->averageCloseDays($closed()),
                ...$quotations,
                ...$this->pendingApprovals($business, $access, $filters),
                ...$this->openPipeline($business, $access, $filters),
                'overdue_follow_ups'  => FollowUp::query()->overdue($business->id)
                    ->whereIn('lead_id', $this->leads($business, $access, $filters)->select('crm_leads.id'))->count(),
                ...$this->contactGaps($business, $access, $filters),
            ],
            'previous'     => $this->previousPeriod($business, $access, $filters),
            'trend'        => $this->trend($created(), $from, $to, $this->wonValueByDay($closed(), $business)),
            'top_open'     => $this->topOpenDeals($business, $access, $filters),
            'recent_wins'  => $this->recentWins($business, $closed()),
            'pipeline_stages' => $this->pipelineByStage($business, $access, $filters),
            'forecast'     => $this->closeForecast($business, $access, $filters),
            'attention'    => $this->attention($business, $access, $filters),
            'by_source'    => $this->bySource($created()),
            'by_department'=> $this->byDepartment($business, $created()),
            'by_division'  => $this->byDivision($business, $created()),
            'funnel'       => $this->funnel($created()),
            'staff'        => $this->withStaffWorkload($business, $access, $filters, $this->staffRanking($created()), $created()),
            'lost_reasons' => $this->lostReasons($closed()),
            'options'      => $this->options($business, $access),
        ];
    }

    // ── Filters & scope ──────────────────────────────────────────────────

    /** @return array{from: Carbon, to: Carbon, department_id: ?int, division_id: ?int, user_id: ?int, source: ?string} */
    public function filters(Business $business, CrmAccess $access, array $input): array
    {
        try {
            $to   = filled($input['to'] ?? null) ? Carbon::parse($input['to'])->endOfDay() : now()->endOfDay();
            $from = filled($input['from'] ?? null) ? Carbon::parse($input['from'])->startOfDay() : $to->copy()->subDays(29)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['from' => 'Enter valid dates.']);
        }
        if ($from->gt($to)) {
            throw ValidationException::withMessages(['from' => 'The start date is after the end date.']);
        }

        $department = filled($input['department_id'] ?? null) ? (int) $input['department_id'] : null;
        if ($department !== null && ! $access->viewsAllLeads() && ! in_array($department, $this->scopeDepartmentIds($business, $access), true)) {
            throw ValidationException::withMessages(['department_id' => 'You can only see the departments you head.']);
        }

        return [
            'from'          => $from,
            'to'            => $to,
            'department_id' => $department,
            'division_id'   => filled($input['division_id'] ?? null) ? (int) $input['division_id'] : null,
            'user_id'       => filled($input['user_id'] ?? null) ? (int) $input['user_id'] : null,
            'source'        => filled($input['source'] ?? null) ? (string) $input['source'] : null,
        ];
    }

    /** The business's leads this user may report on, with the non-date filters applied. */
    public function leads(Business $business, CrmAccess $access, array $filters): Builder
    {
        $query = Lead::query()->where('crm_leads.business_id', $business->id);

        if (! $access->viewsAllLeads()) {
            $divisions = array_keys($access->headDivisions);
            $query->where(fn (Builder $q) => $q->whereIn('crm_leads.department_id', $access->headDepartmentIds ?: [0])
                ->orWhereIn('crm_leads.division_id', $divisions ?: [0]));
        }

        return $query
            ->when($filters['department_id'], fn (Builder $q, int $id) => $q->where('crm_leads.department_id', $id))
            ->when($filters['division_id'], fn (Builder $q, int $id) => $q->where('crm_leads.division_id', $id))
            ->when($filters['user_id'], fn (Builder $q, int $id) => $q->where('crm_leads.assigned_to', $id))
            ->when($filters['source'], fn (Builder $q, string $s) => $q->where('crm_leads.source', $s));
    }

    /** Departments a head reports on: the ones they head plus those of the divisions they head. @return int[] */
    private function scopeDepartmentIds(Business $business, CrmAccess $access): array
    {
        return array_values(array_unique([...$access->headDepartmentIds, ...array_values($access->headDivisions)]));
    }

    /** @return int[] */
    private function terminalStageIds(Business $business, string $flag): array
    {
        return LeadStage::query()
            ->whereIn('project_id', \Modules\CRM\Models\Project::query()->where('business_id', $business->id)->select('id'))
            ->where($flag, true)
            ->pluck('id')
            ->all() ?: [0];
    }

    // ── Metrics ──────────────────────────────────────────────────────────

    /** Adds wq.won_total: the lead's accepted quotation total. */
    private function withWonValue(Builder $query): Builder
    {
        $accepted = DB::table('quotations')
            ->whereNotNull('lead_id')
            ->where('status', Quotation::STATUS_ACCEPTED)
            ->groupBy('lead_id')
            ->selectRaw('lead_id, max(total) as won_total');

        return $query->leftJoinSub($accepted, 'wq', 'wq.lead_id', '=', 'crm_leads.id');
    }

    private function quotationValues(Business $business, CrmAccess $access, array $filters): array
    {
        $row = Quotation::query()
            ->where('business_id', $business->id)
            ->whereIn('lead_id', $this->leads($business, $access, $filters)->select('crm_leads.id'))
            ->where('status', '!=', Quotation::STATUS_SUPERSEDED)
            ->whereBetween('sent_at', [$filters['from'], $filters['to']])
            ->selectRaw('count(*) as sent_count, sum(total) as sent_value,
                sum(case when status = ? then total else 0 end) as won_value, sum(case when status = ? then 1 else 0 end) as won_count,
                sum(case when status = ? then total else 0 end) as lost_value, sum(case when status = ? then 1 else 0 end) as lost_count',
                [Quotation::STATUS_ACCEPTED, Quotation::STATUS_ACCEPTED, Quotation::STATUS_REJECTED, Quotation::STATUS_REJECTED])
            ->first();

        return [
            'quotations_sent'       => (int) $row->sent_count,
            'quotation_value_sent'  => round((float) $row->sent_value, 2),
            'quotations_won'        => (int) $row->won_count,
            'quotation_value_won'   => round((float) $row->won_value, 2),
            'quotations_lost'       => (int) $row->lost_count,
            'quotation_value_lost'  => round((float) $row->lost_value, 2),
        ];
    }

    private function pendingApprovals(Business $business, CrmAccess $access, array $filters): array
    {
        $row = Quotation::query()
            ->where('business_id', $business->id)
            ->where('status', Quotation::STATUS_PENDING_APPROVAL)
            ->whereIn('lead_id', $this->leads($business, $access, $filters)->select('crm_leads.id'))
            ->selectRaw('count(*) as n, sum(total) as value')
            ->first();

        return ['pending_approvals' => (int) $row->n, 'pending_approval_value' => round((float) $row->value, 2)];
    }

    private function averageResponseHours(Builder $leads): ?float
    {
        $firstAssigned = DB::table('crm_lead_assignment_logs')
            ->whereIn('action', [LeadAssignmentLog::ACTION_ASSIGNED, LeadAssignmentLog::ACTION_REASSIGNED])
            ->groupBy('lead_id')
            ->selectRaw('lead_id, min(created_at) as assigned_at');

        $seconds = $leads
            ->joinSub($firstAssigned, 'fa', 'fa.lead_id', '=', 'crm_leads.id')
            ->whereNotNull('crm_leads.first_response_at')
            ->whereColumn('crm_leads.first_response_at', '>=', 'fa.assigned_at')
            ->selectRaw('avg(' . $this->secondsBetween('fa.assigned_at', 'crm_leads.first_response_at') . ') as s')
            ->value('s');

        return $seconds === null ? null : round((float) $seconds / 3600, 1);
    }

    private function averageCloseDays(Builder $closedLeads): ?float
    {
        $seconds = $closedLeads
            ->selectRaw('avg(' . $this->secondsBetween('crm_leads.created_at', 'crm_leads.closed_at') . ') as s')
            ->value('s');

        return $seconds === null ? null : round((float) $seconds / 86400, 1);
    }

    /** The same headline figures for the equally long period just before this one, for the "vs previous" deltas. */
    private function previousPeriod(Business $business, CrmAccess $access, array $filters): array
    {
        $days = (int) $filters['from']->diffInDays($filters['to']) + 1;
        $prev = [...$filters, 'from' => $filters['from']->copy()->subDays($days), 'to' => $filters['from']->copy()->subSecond()];

        $created = $this->leads($business, $access, $prev)->whereBetween('crm_leads.created_at', [$prev['from'], $prev['to']])
            ->leftJoin('crm_lead_stages as s', 's.id', '=', 'crm_leads.stage_id')
            ->selectRaw('count(*) as leads, sum(case when s.is_won = 1 then 1 else 0 end) as won')
            ->first();
        $closed = fn () => $this->leads($business, $access, $prev)->whereBetween('crm_leads.closed_at', [$prev['from'], $prev['to']]);
        $won    = $this->withWonValue($closed()->whereIn('crm_leads.stage_id', $this->terminalStageIds($business, 'is_won')))
            ->selectRaw('count(*) as n, sum(coalesce(wq.won_total, crm_leads.estimated_value, 0)) as value')
            ->first();
        $lost   = $closed()->whereIn('crm_leads.stage_id', $this->terminalStageIds($business, 'is_lost'))->count();
        [$leads, $wonN] = [(int) $created->leads, (int) $won->n];

        return [
            'from'                 => $prev['from']->toDateString(),
            'to'                   => $prev['to']->toDateString(),
            'leads'                => $leads,
            'conversion_rate'      => $leads > 0 ? round((int) $created->won / $leads * 100, 1) : 0.0,
            'won_in_range'         => $wonN,
            'won_value_in_range'   => round((float) $won->value, 2),
            'win_rate'             => $wonN + $lost > 0 ? round($wonN / ($wonN + $lost) * 100, 1) : null,
            'avg_deal_value'       => $wonN > 0 ? round((float) $won->value / $wonN, 2) : null,
            'quotation_value_sent' => $this->quotationValues($business, $access, $prev)['quotation_value_sent'],
        ];
    }

    /** Open leads in scope right now (any creation date): count, estimated value, and those past their expected close date. */
    private function openPipeline(Business $business, CrmAccess $access, array $filters): array
    {
        $row = $this->openLeads($business, $access, $filters)
            ->selectRaw('count(*) as n, sum(coalesce(crm_leads.estimated_value, 0)) as value,
                sum(case when crm_leads.expected_close_date < ? then 1 else 0 end) as past_due,
                sum(case when crm_leads.expected_close_date < ? then coalesce(crm_leads.estimated_value, 0) else 0 end) as past_due_value',
                [now()->toDateString(), now()->toDateString()])
            ->first();

        return [
            'open_pipeline_count'  => (int) $row->n,
            'open_pipeline_value'  => round((float) $row->value, 2),
            'past_due_deals'       => (int) $row->past_due,
            'past_due_deals_value' => round((float) $row->past_due_value, 2),
        ];
    }

    private function openLeads(Business $business, CrmAccess $access, array $filters): Builder
    {
        return $this->leads($business, $access, $filters)
            ->leftJoin('crm_lead_stages as s', 's.id', '=', 'crm_leads.stage_id')
            ->whereRaw('coalesce(s.is_won, 0) = 0 and coalesce(s.is_lost, 0) = 0');
    }

    /** The five largest open deals by estimated value. */
    private function topOpenDeals(Business $business, CrmAccess $access, array $filters): array
    {
        return $this->openLeads($business, $access, $filters)
            ->leftJoin('users as u', 'u.id', '=', 'crm_leads.assigned_to')
            ->where('crm_leads.estimated_value', '>', 0)
            ->orderByDesc('crm_leads.estimated_value')
            ->limit(5)
            ->get(['crm_leads.id', 'crm_leads.project_id', 'crm_leads.name', 'crm_leads.company', 'crm_leads.estimated_value',
                'crm_leads.expected_close_date', 'crm_leads.created_at', 's.system_key', 'u.name as owner'])
            ->map(fn ($l) => [
                'id'                  => (int) $l->id,
                'project_id'          => (int) $l->project_id,
                'name'                => $l->company ?: $l->name,
                'contact'             => $l->company ? $l->name : null,
                'value'               => round((float) $l->estimated_value, 2),
                'stage'               => LeadStage::STATUSES[$l->system_key][0] ?? 'Open',
                'stage_color'         => LeadStage::STATUSES[$l->system_key][1] ?? '#94a3b8',
                'owner'               => $l->owner,
                'expected_close_date' => $l->expected_close_date?->toDateString(),
                'past_due'            => $l->expected_close_date !== null && $l->expected_close_date->lt(today()),
                'age_days'            => (int) Carbon::parse($l->created_at)->diffInDays(now()),
            ])
            ->all();
    }

    /** The five largest deals won in the period. */
    private function recentWins(Business $business, Builder $closedLeads): array
    {
        return $this->withWonValue($closedLeads->whereIn('crm_leads.stage_id', $this->terminalStageIds($business, 'is_won')))
            ->leftJoin('users as u', 'u.id', '=', 'crm_leads.assigned_to')
            ->orderByRaw('coalesce(wq.won_total, crm_leads.estimated_value, 0) desc')
            ->limit(5)
            ->get(['crm_leads.id', 'crm_leads.project_id', 'crm_leads.name', 'crm_leads.company', 'crm_leads.closed_at', 'u.name as owner',
                DB::raw('coalesce(wq.won_total, crm_leads.estimated_value, 0) as won_value')])
            ->map(fn ($l) => [
                'id'         => (int) $l->id,
                'project_id' => (int) $l->project_id,
                'name'       => $l->company ?: $l->name,
                'value'      => round((float) $l->won_value, 2),
                'owner'      => $l->owner,
                'closed_at'  => $l->closed_at?->toDateString(),
            ])
            ->all();
    }

    /** Won value per close day. @return array<string, float> date => value */
    private function wonValueByDay(Builder $closedLeads, Business $business): array
    {
        return $this->withWonValue($closedLeads->whereIn('crm_leads.stage_id', $this->terminalStageIds($business, 'is_won')))
            ->selectRaw('date(crm_leads.closed_at) as d, sum(coalesce(wq.won_total, crm_leads.estimated_value, 0)) as v')
            ->groupByRaw('date(crm_leads.closed_at)')
            ->get()
            ->mapWithKeys(fn ($r) => [Carbon::parse($r->d)->toDateString() => (float) $r->v])
            ->all();
    }

    /**
     * Leads created per day (per week over 92 days), with won value by close day.
     * @param  array<string, float>  $wonValues
     * @return list<array{label: string, date: string, leads: int, won: int, won_value: float}>
     */
    private function trend(Builder $leads, Carbon $from, Carbon $to, array $wonValues = []): array
    {
        $rows = $leads
            ->leftJoin('crm_lead_stages as s', 's.id', '=', 'crm_leads.stage_id')
            ->selectRaw('date(crm_leads.created_at) as d, count(*) as n, sum(case when s.is_won = 1 then 1 else 0 end) as won')
            ->groupByRaw('date(crm_leads.created_at)')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->d)->toDateString());

        $weekly = $from->diffInDays($to) > 92;
        $out    = [];
        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $key = $weekly ? $day->copy()->startOfWeek()->toDateString() : $day->toDateString();
            $out[$key] ??= ['label' => ($weekly ? 'Wk ' : '') . Carbon::parse($key)->format('M j'), 'date' => $key, 'leads' => 0, 'won' => 0, 'won_value' => 0.0];
            $row = $rows->get($day->toDateString());
            if ($row) {
                $out[$key]['leads'] += (int) $row->n;
                $out[$key]['won']   += (int) $row->won;
            }
            $out[$key]['won_value'] += $wonValues[$day->toDateString()] ?? 0.0;
        }

        return array_values(array_map(fn ($r) => [...$r, 'won_value' => round($r['won_value'], 2)], $out));
    }

    private function bySource(Builder $leads): array
    {
        return $leads
            ->leftJoin('crm_lead_stages as s', 's.id', '=', 'crm_leads.stage_id')
            ->selectRaw('crm_leads.source as k, count(*) as n, sum(case when s.is_won = 1 then 1 else 0 end) as won')
            ->groupBy('crm_leads.source')
            ->orderByDesc('n')
            ->get()
            ->map(fn ($r) => [
                'key'   => $r->k,
                'label' => Lead::sourceLabel($r->k) ?? 'Unknown',
                'color' => Lead::SOURCES[$r->k][2] ?? '#94a3b8',
                'leads' => (int) $r->n,
                'won'   => (int) $r->won,
            ])
            ->all();
    }

    private function byDepartment(Business $business, Builder $leads): array
    {
        $names = Department::query()->where('business_id', $business->id)->pluck('name', 'id');

        return $leads
            ->leftJoin('crm_lead_stages as s', 's.id', '=', 'crm_leads.stage_id')
            ->selectRaw('crm_leads.department_id as k, count(*) as n, sum(case when s.is_won = 1 then 1 else 0 end) as won')
            ->groupBy('crm_leads.department_id')
            ->orderByDesc('n')
            ->get()
            ->map(fn ($r) => ['id' => $r->k !== null ? (int) $r->k : null, 'label' => $r->k !== null ? ($names[$r->k] ?? 'Department #' . $r->k) : 'No department', 'leads' => (int) $r->n, 'won' => (int) $r->won])
            ->all();
    }

    private function byDivision(Business $business, Builder $leads): array
    {
        $names = Division::query()->where('business_id', $business->id)->pluck('name', 'id');

        return $leads
            ->whereNotNull('crm_leads.division_id')
            ->leftJoin('crm_lead_stages as s', 's.id', '=', 'crm_leads.stage_id')
            ->selectRaw('crm_leads.division_id as k, count(*) as n, sum(case when s.is_won = 1 then 1 else 0 end) as won')
            ->groupBy('crm_leads.division_id')
            ->orderByDesc('n')
            ->get()
            ->map(fn ($r) => ['id' => (int) $r->k, 'label' => $names[$r->k] ?? 'Division #' . $r->k, 'leads' => (int) $r->n, 'won' => (int) $r->won])
            ->all();
    }

    /** Leads per standard status, in pipeline order. */
    private function funnel(Builder $leads): array
    {
        $counts = $leads
            ->join('crm_lead_stages as s', 's.id', '=', 'crm_leads.stage_id')
            ->selectRaw('s.system_key as k, count(*) as n')
            ->groupBy('s.system_key')
            ->pluck('n', 'k');

        return collect(LeadStage::STATUSES)
            ->map(fn ($s, $key) => ['key' => $key, 'label' => $s[0], 'color' => $s[1], 'leads' => (int) ($counts[$key] ?? 0)])
            ->values()
            ->all();
    }

    private function staffRanking(Builder $leads): array
    {
        return $this->withWonValue($leads)
            ->leftJoin('crm_lead_stages as s', 's.id', '=', 'crm_leads.stage_id')
            ->leftJoin('users as u', 'u.id', '=', 'crm_leads.assigned_to')
            ->whereNotNull('crm_leads.assigned_to')
            ->selectRaw('crm_leads.assigned_to as uid, max(u.name) as name, count(*) as n,
                sum(case when s.is_won = 1 then 1 else 0 end) as won,
                sum(case when s.is_won = 1 then coalesce(wq.won_total, crm_leads.estimated_value, 0) else 0 end) as won_value')
            ->groupBy('crm_leads.assigned_to')
            ->get()
            ->map(fn ($r) => [
                'user_id'         => (int) $r->uid,
                'name'            => $r->name ?? 'User #' . $r->uid,
                'leads'           => (int) $r->n,
                'won'             => (int) $r->won,
                'won_value'       => round((float) $r->won_value, 2),
                'conversion_rate' => (int) $r->n > 0 ? round((int) $r->won / (int) $r->n * 100, 1) : 0.0,
            ])
            ->sortBy([['won_value', 'desc'], ['won', 'desc'], ['conversion_rate', 'desc']])
            ->values()
            ->all();
    }

    /** Open leads right now with no assignee, and assigned ones nobody has contacted yet. */
    private function contactGaps(Business $business, CrmAccess $access, array $filters): array
    {
        $row = $this->openLeads($business, $access, $filters)
            ->selectRaw('sum(case when crm_leads.assigned_to is null then 1 else 0 end) as unassigned,
                sum(case when crm_leads.assigned_to is not null and crm_leads.first_response_at is null then 1 else 0 end) as uncontacted')
            ->first();

        return ['unassigned_leads' => (int) $row->unassigned, 'uncontacted_leads' => (int) $row->uncontacted];
    }

    /** All open deals right now, per standard status in pipeline order: count, estimated value, past due. */
    private function pipelineByStage(Business $business, CrmAccess $access, array $filters): array
    {
        $rows = $this->openLeads($business, $access, $filters)
            ->selectRaw('s.system_key as k, count(*) as n, sum(coalesce(crm_leads.estimated_value, 0)) as value,
                sum(case when crm_leads.expected_close_date < ? then 1 else 0 end) as past_due', [now()->toDateString()])
            ->groupBy('s.system_key')
            ->get()
            ->keyBy('k');

        return collect(LeadStage::STATUSES)
            ->except([LeadStage::STATUS_WON, LeadStage::STATUS_LOST])
            ->map(fn ($s, $key) => [
                'key'      => $key,
                'label'    => $s[0],
                'color'    => $s[1],
                'leads'    => (int) ($rows[$key]->n ?? 0),
                'value'    => round((float) ($rows[$key]->value ?? 0), 2),
                'past_due' => (int) ($rows[$key]->past_due ?? 0),
            ])
            ->values()
            ->all();
    }

    /** Open deals by expected close date: past due, the next 30 days, later, not set. */
    private function closeForecast(Business $business, CrmAccess $access, array $filters): array
    {
        $today = now()->toDateString();
        $in30  = now()->addDays(30)->toDateString();
        $row   = $this->openLeads($business, $access, $filters)
            ->selectRaw('
                sum(case when crm_leads.expected_close_date < ? then 1 else 0 end) as past_n,
                sum(case when crm_leads.expected_close_date < ? then coalesce(crm_leads.estimated_value, 0) else 0 end) as past_v,
                sum(case when crm_leads.expected_close_date >= ? and crm_leads.expected_close_date <= ? then 1 else 0 end) as soon_n,
                sum(case when crm_leads.expected_close_date >= ? and crm_leads.expected_close_date <= ? then coalesce(crm_leads.estimated_value, 0) else 0 end) as soon_v,
                sum(case when crm_leads.expected_close_date > ? then 1 else 0 end) as later_n,
                sum(case when crm_leads.expected_close_date > ? then coalesce(crm_leads.estimated_value, 0) else 0 end) as later_v,
                sum(case when crm_leads.expected_close_date is null then 1 else 0 end) as none_n,
                sum(case when crm_leads.expected_close_date is null then coalesce(crm_leads.estimated_value, 0) else 0 end) as none_v',
                [$today, $today, $today, $in30, $today, $in30, $in30, $in30])
            ->first();

        return collect(['past' => 'Past due', 'soon' => 'Next 30 days', 'later' => 'Later', 'none' => 'No close date'])
            ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'leads' => (int) $row->{$key . '_n'}, 'value' => round((float) $row->{$key . '_v'}, 2)])
            ->values()
            ->all();
    }

    /** Live to-do list for managers: the oldest overdue follow-ups and assigned leads still waiting for first contact. */
    private function attention(Business $business, CrmAccess $access, array $filters): array
    {
        $startOfToday = now()->startOfDay();

        $followUps = FollowUp::query()->overdue($business->id)
            ->whereIn('crm_follow_ups.lead_id', $this->leads($business, $access, $filters)->select('crm_leads.id'))
            ->join('crm_leads as l', 'l.id', '=', 'crm_follow_ups.lead_id')
            ->leftJoin('users as u', 'u.id', '=', 'crm_follow_ups.user_id')
            ->orderBy('crm_follow_ups.due_at')
            ->limit(8)
            ->get(['crm_follow_ups.id', 'crm_follow_ups.due_at', 'l.id as lead_id', 'l.project_id', 'l.name', 'l.company', 'l.phone', 'u.name as owner'])
            ->map(fn ($f) => [
                'id'           => (int) $f->id,
                'lead_id'      => (int) $f->lead_id,
                'project_id'   => (int) $f->project_id,
                'name'         => $f->company ?: $f->name,
                'phone'        => $f->phone,
                'owner'        => $f->owner,
                'due_at'       => $f->due_at?->toDateString(),
                'days_overdue' => max(1, (int) $f->due_at?->copy()->startOfDay()->diffInDays($startOfToday)),
            ])
            ->all();

        $uncontacted = $this->openLeads($business, $access, $filters)
            ->leftJoin('users as u', 'u.id', '=', 'crm_leads.assigned_to')
            ->whereNotNull('crm_leads.assigned_to')
            ->whereNull('crm_leads.first_response_at')
            ->orderBy('crm_leads.created_at')
            ->limit(8)
            ->get(['crm_leads.id', 'crm_leads.project_id', 'crm_leads.name', 'crm_leads.company', 'crm_leads.phone', 'crm_leads.created_at', 'u.name as owner'])
            ->map(fn ($l) => [
                'lead_id'    => (int) $l->id,
                'project_id' => (int) $l->project_id,
                'name'       => $l->company ?: $l->name,
                'phone'      => $l->phone,
                'owner'      => $l->owner,
                'age_days'   => (int) Carbon::parse($l->created_at)->diffInDays(now()),
            ])
            ->all();

        return ['follow_ups' => $followUps, 'uncontacted' => $uncontacted];
    }

    /**
     * Adds each person's live workload (open deals and their value, overdue follow-ups) and their
     * average first response for leads created in the range. Staff with open work but no new
     * leads in the range are appended at the end.
     */
    private function withStaffWorkload(Business $business, CrmAccess $access, array $filters, array $staff, Builder $createdLeads): array
    {
        $open = $this->openLeads($business, $access, $filters)
            ->leftJoin('users as u', 'u.id', '=', 'crm_leads.assigned_to')
            ->whereNotNull('crm_leads.assigned_to')
            ->selectRaw('crm_leads.assigned_to as uid, max(u.name) as name, count(*) as n, sum(coalesce(crm_leads.estimated_value, 0)) as value')
            ->groupBy('crm_leads.assigned_to')
            ->get()
            ->keyBy('uid');
        $overdue = FollowUp::query()->overdue($business->id)
            ->whereIn('crm_follow_ups.lead_id', $this->leads($business, $access, $filters)->select('crm_leads.id'))
            ->selectRaw('crm_follow_ups.user_id as uid, count(*) as n')
            ->groupBy('crm_follow_ups.user_id')
            ->pluck('n', 'uid');

        $firstAssigned = DB::table('crm_lead_assignment_logs')
            ->whereIn('action', [LeadAssignmentLog::ACTION_ASSIGNED, LeadAssignmentLog::ACTION_REASSIGNED])
            ->groupBy('lead_id')
            ->selectRaw('lead_id, min(created_at) as assigned_at');
        $response = $createdLeads
            ->joinSub($firstAssigned, 'fa', 'fa.lead_id', '=', 'crm_leads.id')
            ->whereNotNull('crm_leads.assigned_to')
            ->whereNotNull('crm_leads.first_response_at')
            ->whereColumn('crm_leads.first_response_at', '>=', 'fa.assigned_at')
            ->selectRaw('crm_leads.assigned_to as uid, avg(' . $this->secondsBetween('fa.assigned_at', 'crm_leads.first_response_at') . ') as s')
            ->groupBy('crm_leads.assigned_to')
            ->pluck('s', 'uid');

        $extra = fn (int $uid) => [
            'open_deals'         => (int) ($open[$uid]->n ?? 0),
            'open_value'         => round((float) ($open[$uid]->value ?? 0), 2),
            'overdue_follow_ups' => (int) ($overdue[$uid] ?? 0),
            'avg_response_hours' => isset($response[$uid]) ? round((float) $response[$uid] / 3600, 1) : null,
        ];

        $seen = [];
        $rows = array_map(function ($s) use ($extra, &$seen) {
            $seen[$s['user_id']] = true;

            return [...$s, ...$extra($s['user_id'])];
        }, $staff);
        foreach ($open as $uid => $o) {
            if (! isset($seen[(int) $uid])) {
                $rows[] = ['user_id' => (int) $uid, 'name' => $o->name ?? 'User #' . $uid, 'leads' => 0, 'won' => 0, 'won_value' => 0.0, 'conversion_rate' => 0.0, ...$extra((int) $uid)];
            }
        }

        return $rows;
    }

    private function lostReasons(Builder $closedLeads): array
    {
        $rows = $closedLeads
            ->whereNotNull('crm_leads.lost_reason_id')
            ->selectRaw('crm_leads.lost_reason_id as k, count(*) as n')
            ->groupBy('crm_leads.lost_reason_id')
            ->orderByDesc('n')
            ->limit(10)
            ->get();
        $labels = LostReason::query()->whereIn('id', $rows->pluck('k'))->pluck('label', 'id');

        return $rows->map(fn ($r) => ['id' => (int) $r->k, 'label' => $labels[$r->k] ?? 'Reason #' . $r->k, 'leads' => (int) $r->n])->all();
    }

    /** What the filter bar offers this user. */
    public function options(Business $business, CrmAccess $access): array
    {
        $departmentIds = $access->viewsAllLeads() ? null : $this->scopeDepartmentIds($business, $access);

        $departments = Department::query()->where('business_id', $business->id)
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('id', $departmentIds ?: [0]))
            ->orderBy('name')->get(['id', 'name']);
        $divisions = Division::query()->where('business_id', $business->id)
            ->whereIn('department_id', $departments->pluck('id')->all() ?: [0])
            ->orderBy('name')->get(['id', 'name', 'department_id']);
        $staff = DB::table('crm_leads')->where('crm_leads.business_id', $business->id)
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('crm_leads.department_id', $departmentIds ?: [0]))
            ->join('users', 'users.id', '=', 'crm_leads.assigned_to')
            ->distinct()->orderBy('users.name')->get(['users.id', 'users.name']);

        return [
            'departments' => $departments->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->name])->values()->all(),
            'divisions'   => $divisions->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->name, 'department_id' => (int) $d->department_id])->values()->all(),
            'staff'       => $staff->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])->values()->all(),
            'sources'     => Lead::sourceOptions(),
            'scope'       => $access->viewsAllLeads() ? 'business' : 'headed',
        ];
    }

    private function secondsBetween(string $from, string $to): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "((julianday({$to}) - julianday({$from})) * 86400)",
            'pgsql'  => "extract(epoch from ({$to} - {$from}))",
            default  => "timestampdiff(second, {$from}, {$to})",
        };
    }
}
