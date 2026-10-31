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
 * Pending approvals and overdue follow-ups are "right now", not limited to the date range.
 */
class CrmReportService
{
    public function canView(CrmAccess $access): bool
    {
        return $access->hasCrm() && ($access->viewsAllLeads() || $access->isHead());
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
                'won_in_range'        => (int) $wonInRange->n,
                'won_value_in_range'  => round((float) $wonInRange->value, 2),
                'lost_in_range'       => $lostInRange,
                'avg_response_hours'  => $this->averageResponseHours($created()),
                'avg_close_days'      => $this->averageCloseDays($closed()),
                ...$this->quotationValues($business, $access, $filters),
                ...$this->pendingApprovals($business, $access, $filters),
                'overdue_follow_ups'  => FollowUp::query()->overdue()
                    ->whereIn('lead_id', $this->leads($business, $access, $filters)->select('crm_leads.id'))->count(),
            ],
            'trend'        => $this->trend($created(), $from, $to),
            'by_source'    => $this->bySource($created()),
            'by_department'=> $this->byDepartment($business, $created()),
            'by_division'  => $this->byDivision($business, $created()),
            'funnel'       => $this->funnel($created()),
            'staff'        => $this->staffRanking($created()),
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
    private function leads(Business $business, CrmAccess $access, array $filters): Builder
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

    /** Leads created per day (per week over 92 days). @return list<array{label: string, date: string, leads: int, won: int}> */
    private function trend(Builder $leads, Carbon $from, Carbon $to): array
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
            $out[$key] ??= ['label' => ($weekly ? 'Wk ' : '') . Carbon::parse($key)->format('M j'), 'date' => $key, 'leads' => 0, 'won' => 0];
            $row = $rows->get($day->toDateString());
            if ($row) {
                $out[$key]['leads'] += (int) $row->n;
                $out[$key]['won']   += (int) $row->won;
            }
        }

        return array_values($out);
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
    private function options(Business $business, CrmAccess $access): array
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
