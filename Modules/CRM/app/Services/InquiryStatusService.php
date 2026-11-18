<?php

namespace Modules\CRM\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\FollowUp;
use Modules\CRM\Models\InquiryAllocation;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\Project;
use Modules\HRManagement\Models\Department;
use Modules\Sales\Models\Quotation;

/**
 * The daily inquiry status: for the inquiries handed to each user on a day, how far each one
 * has got. Every inquiry is counted once, at its furthest step:
 *
 *   sale       the lead is won (invoice / sales order / project made)
 *   lost       the lead is lost
 *   quotation  a quotation exists for it, or its stage is a quotation stage
 *   follow_up  a follow-up is planned or logged on it
 *   contacted  someone worked it (an activity, a first response, or moved past Assigned)
 *   pending    nobody has acted on it yet
 *
 * so the six add up to the total. Shown in the desktop's daily pop-up (the user's own, and the
 * team for heads) and in the daily report (InquiryReportService).
 */
class InquiryStatusService
{
    public const CATEGORIES = [
        'pending'   => ['Pending',          '#94a3b8', 'fa-hourglass-half'],
        'contacted' => ['Contacted',        '#3b82f6', 'fa-phone'],
        'follow_up' => ['Under follow-up',  '#f59e0b', 'fa-phone-volume'],
        'quotation' => ['Quotation',        '#8b5cf6', 'fa-file-invoice-dollar'],
        'sale'      => ['Sale',             '#10b981', 'fa-trophy'],
        'lost'      => ['Lost',             '#ef4444', 'fa-circle-xmark'],
    ];

    private const QUOTATION_STATUSES = [
        LeadStage::STATUS_QUOTATION_PREPARING,
        LeadStage::STATUS_PENDING_APPROVAL,
        LeadStage::STATUS_QUOTATION_SENT,
        LeadStage::STATUS_NEGOTIATION,
    ];

    public function __construct(
        private readonly InquiryAllocationService $allocations,
        private readonly CrmOrgService $org,
    ) {}

    /** @return list<array{key:string, label:string, color:string, icon:string}> */
    public static function categories(): array
    {
        return array_map(
            fn (string $key, array $c) => ['key' => $key, 'label' => $c[0], 'color' => $c[1], 'icon' => $c[2]],
            array_keys(self::CATEGORIES),
            self::CATEGORIES,
        );
    }

    /**
     * The step each lead has reached.
     *
     * @param  int[]  $leadIds
     * @return array<int, string> lead id => category key
     */
    public function classify(array $leadIds): array
    {
        $leadIds = array_values(array_unique(array_map('intval', $leadIds)));
        if ($leadIds === []) {
            return [];
        }

        $leads = Lead::query()->whereIn('id', $leadIds)
            ->with('stage:id,system_key,is_won,is_lost')
            ->get(['id', 'stage_id', 'first_response_at']);

        $quoted = Quotation::query()->whereIn('lead_id', $leadIds)
            ->where('status', '!=', Quotation::STATUS_SUPERSEDED)
            ->distinct()->pluck('lead_id')->map(fn ($id) => (int) $id)->flip();
        $followed = FollowUp::query()->whereIn('lead_id', $leadIds)
            ->where('status', '!=', FollowUp::STATUS_CANCELLED)
            ->distinct()->pluck('lead_id')->map(fn ($id) => (int) $id)->flip();
        $worked = Activity::query()
            ->where('subject_type', (new Lead())->getMorphClass())
            ->whereIn('subject_id', $leadIds)
            ->distinct()->pluck('subject_id')->map(fn ($id) => (int) $id)->flip();

        $out = [];
        foreach ($leads as $lead) {
            $id     = (int) $lead->id;
            $status = $lead->stage?->system_key;
            $out[$id] = match (true) {
                (bool) $lead->stage?->is_won || $status === LeadStage::STATUS_WON   => 'sale',
                (bool) $lead->stage?->is_lost || $status === LeadStage::STATUS_LOST => 'lost',
                isset($quoted[$id]) || in_array($status, self::QUOTATION_STATUSES, true) => 'quotation',
                isset($followed[$id])                                                 => 'follow_up',
                isset($worked[$id]) || $lead->first_response_at !== null
                    || ($status !== null && ! in_array($status, [LeadStage::STATUS_NEW, LeadStage::STATUS_ASSIGNED], true)) => 'contacted',
                default => 'pending',
            };
        }

        return $out;
    }

    /** @param  string[]  $categories  @return array{total:int, pending:int, contacted:int, follow_up:int, quotation:int, sale:int, lost:int, conversion_rate:float} */
    public function count(array $categories): array
    {
        $counts = ['total' => count($categories)] + array_fill_keys(array_keys(self::CATEGORIES), 0);
        foreach ($categories as $c) {
            $counts[$c]++;
        }
        $counts['conversion_rate'] = $counts['total'] > 0 ? round(100 * $counts['sale'] / $counts['total'], 1) : 0.0;

        return $counts;
    }

    /**
     * One user's inquiries of the day with the step each has reached, and the counts.
     *
     * @return array{date:string, counts:array, capacity:array, leads:list<array>}
     */
    public function forUser(Business $business, int $userId, string $date): array
    {
        $rows = InquiryAllocation::query()
            ->where('business_id', $business->id)
            ->where('user_id', $userId)
            ->whereDate('allocation_date', $date)
            ->active()
            ->with(['lead:id,project_id,name,company,phone,stage_id', 'lead.stage:id,name,color', 'assignedBy:id,name'])
            ->orderBy('id')
            ->get();

        $categories = $this->classify($rows->pluck('lead_id')->all());

        return [
            'date'     => $date,
            'counts'   => $this->count(array_values($categories)),
            'capacity' => $this->allocations->capacity($business, [$userId], $date)[$userId],
            'leads'    => $rows->filter(fn (InquiryAllocation $a) => $a->lead !== null)->map(fn (InquiryAllocation $a) => [
                'id'          => (int) $a->lead->id,
                'project_id'  => (int) $a->lead->project_id,
                'name'        => $a->lead->name,
                'company'     => $a->lead->company,
                'phone'       => $a->lead->phone,
                'stage'       => $a->lead->stage?->name,
                'stage_color' => $a->lead->stage?->color,
                'category'    => $categories[(int) $a->lead_id] ?? 'pending',
                'assigned_by' => $a->assignedBy?->name,
                'assigned_at' => $a->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * A department's day: every member (with zero rows for those given nothing) and the
     * department totals. People given the department's leads from outside it are listed too.
     *
     * @return array{department:array{id:int, name:string}, date:string, totals:array, users:list<array>}
     */
    public function forDepartment(Business $business, Department $department, string $date): array
    {
        $rows = InquiryAllocation::query()
            ->where('business_id', $business->id)
            ->where('department_id', $department->id)
            ->whereDate('allocation_date', $date)
            ->active()
            ->get(['id', 'lead_id', 'user_id', 'over_limit']);

        $categories = $this->classify($rows->pluck('lead_id')->all());
        $userIds    = collect($this->org->memberUserIds($department))
            ->concat($rows->pluck('user_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
        $names      = User::whereIn('id', $userIds)->pluck('name', 'id');
        $capacity   = $this->allocations->capacity($business, $userIds, $date);
        $byUser     = $rows->groupBy('user_id');

        $users = collect($userIds)->map(function (int $id) use ($byUser, $categories, $names, $capacity) {
            $mine = $byUser->get($id, collect());

            return [
                'user'       => ['id' => $id, 'name' => $names[$id] ?? "User #{$id}"],
                'counts'     => $this->count($mine->map(fn ($a) => $categories[(int) $a->lead_id] ?? 'pending')->all()),
                'over_limit' => $mine->where('over_limit', true)->count(),
                'capacity'   => $capacity[$id],
            ];
        })->sortBy([fn ($a, $b) => $b['counts']['total'] <=> $a['counts']['total'], fn ($a, $b) => strcmp($a['user']['name'], $b['user']['name'])])
            ->values()->all();

        return [
            'department' => ['id' => (int) $department->id, 'name' => $department->name],
            'date'       => $date,
            'totals'     => $this->count($rows->map(fn ($a) => $categories[(int) $a->lead_id] ?? 'pending')->all()),
            'users'      => $users,
        ];
    }

    /**
     * Departments whose team status this user may see: every department for admins, the CEO and
     * "All Departments"; otherwise the ones they head (or head a division of).
     *
     * @return Collection<int, Department>
     */
    public function teamDepartments(Business $business, CrmAccess $access): Collection
    {
        $query = Department::query()->where('business_id', $business->id)->orderBy('name');

        // Only departments that run a pipeline take inquiries
        if ($access->viewsAllLeads()) {
            return $query->whereIn('id', Project::query()->where('business_id', $business->id)->whereNotNull('department_id')->select('department_id'))->get();
        }

        $ids = array_values(array_unique([...$access->headDepartmentIds, ...array_values($access->headDivisions)]));

        return $ids === [] ? collect() : $query->whereIn('id', $ids)->get();
    }

    public function canSeeTeam(Business $business, CrmAccess $access): bool
    {
        return $access->viewsAllLeads() || $access->isHead();
    }
}
