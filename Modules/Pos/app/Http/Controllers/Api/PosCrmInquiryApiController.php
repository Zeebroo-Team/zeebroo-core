<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\CRM\Models\InquiryAllocation;
use Modules\CRM\Models\InquiryLimitException;
use Modules\CRM\Models\InquiryReport;
use Modules\CRM\Models\Lead;
use Modules\CRM\Policies\LeadPolicy;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CrmOrgService;
use Modules\CRM\Services\CrmPermissionService;
use Modules\CRM\Services\CrmSettingsService;
use Modules\CRM\Services\InquiryAllocationService;
use Modules\CRM\Services\InquiryReportService;
use Modules\CRM\Services\InquiryStatusService;
use Modules\HRManagement\Models\Department;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

/**
 * Daily inquiry management in the desktop: the daily status pop-up (the user's own inquiries
 * and, for heads, their team), the allocation record, the daily limit's exceptions (admins)
 * and the daily report heads review and send to management.
 *
 * Who sees what:
 *   - everyone with CRM: their own inquiries of the day and their own allocations
 *   - heads: their departments' team status, allocations and reports (review / send)
 *   - admins, the CEO and "All Departments": every department (the CEO read-only)
 *   - admins only: exceptions to the daily limit
 */
class PosCrmInquiryApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    private ?CrmAccess $crmAccess = null;

    public function __construct(
        private readonly CrmPermissionService $crmPermissions,
        private readonly CrmOrgService $org,
        private readonly CrmSettingsService $settings,
        private readonly InquiryAllocationService $allocations,
        private readonly InquiryStatusService $status,
        private readonly InquiryReportService $reports,
        private readonly LeadPolicy $policy,
    ) {}

    /** GET crm/inquiries/status?date=Y-m-d — the daily pop-up. */
    public function status(Request $request): JsonResponse
    {
        [$business, $access] = $this->base($request);
        $date    = $this->date($request, $business);
        $canTeam = $this->status->canSeeTeam($business, $access);

        return response()->json([
            'data' => [
                'date'       => $date,
                'today'      => $this->allocations->today($business),
                'limit'      => $this->allocations->limit($business),
                'categories' => InquiryStatusService::categories(),
                'mine'       => $this->status->forUser($business, (int) $access->userId, $date),
                'team'       => $canTeam
                    ? $this->status->teamDepartments($business, $access)->map(fn (Department $d) => $this->status->forDepartment($business, $d, $date))->values()
                    : null,
            ],
            'meta' => $this->abilities($business, $access),
        ]);
    }

    /** GET crm/inquiries/allocations?from=&to=&user_id=&department_id= — the allocation record. */
    public function allocations(Request $request): JsonResponse
    {
        [$business, $access] = $this->base($request);
        $data = $request->validate([
            'from'          => ['nullable', 'date_format:Y-m-d'],
            'to'            => ['nullable', 'date_format:Y-m-d'],
            'user_id'       => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
        ]);
        $today = $this->allocations->today($business);
        $from  = $data['from'] ?? $today;
        $to    = $data['to'] ?? $from;
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        // Staff see their own; heads their departments (and their own); admins everything
        $departments = $this->visibleDepartmentIds($business, $access);
        $userIds     = isset($data['user_id']) ? [(int) $data['user_id']] : null;
        if (isset($data['department_id'])) {
            $this->abortUnlessAllowed($departments === null || in_array((int) $data['department_id'], $departments, true));
            $departments = [(int) $data['department_id']];
        }

        if ($departments !== null && $departments === []) {
            $userIds = [(int) $access->userId];
        }

        $rows = $this->allocations->allocations($business, $from, $to, $userIds, $departments === [] ? null : $departments);

        return response()->json([
            'data' => $rows->map(fn (InquiryAllocation $a) => $this->allocations->presentAllocation($a))->values(),
            'meta' => ['from' => $from, 'to' => $to] + $this->abilities($business, $access),
        ]);
    }

    /**
     * GET crm/leads/{lead}/inquiry-capacity — today's load of each person the lead can be given
     * to, so the assign picker can show "3 / 10 today" and grey out the full ones.
     */
    public function leadCapacity(Request $request, int $leadId): JsonResponse
    {
        [$business, $access] = $this->base($request);
        $lead = Lead::where('business_id', $business->id)->findOrFail($leadId);
        if (! $this->policy->canView($access, $lead)) {
            abort(404);
        }
        $lead->loadMissing('project');

        $ids = $this->org->assignableUsers($business, $lead->project?->department_id)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return response()->json([
            'data' => $this->allocations->capacity($business, $ids),
            'meta' => ['date' => $this->allocations->today($business), 'limit' => $this->allocations->limit($business)],
        ]);
    }

    // ── Exceptions (admins) ──────────────────────────────────────────────

    /** GET crm/inquiries/exceptions?date= */
    public function exceptions(Request $request): JsonResponse
    {
        [$business, $access] = $this->base($request);
        $this->abortUnlessAllowed($access->isHead() || $access->viewsAllLeads());

        return response()->json([
            'data' => $this->allocations->exceptionsOn($business, $this->date($request, $business))
                ->map(fn (InquiryLimitException $e) => $this->allocations->presentException($e))->values(),
            'meta' => $this->abilities($business, $access),
        ]);
    }

    /** POST crm/inquiries/exceptions — { user_id, date?, extra_allowed, reason } */
    public function grantException(Request $request): JsonResponse
    {
        [$business, $access] = $this->base($request);
        $this->abortUnlessAllowed($this->canGrantExceptions($access));

        $data = $request->validate([
            'user_id'       => ['required', 'integer'],
            'date'          => ['nullable', 'date_format:Y-m-d'],
            'extra_allowed' => ['required', 'integer', 'min:1', 'max:200'],
            'reason'        => ['required', 'string', 'max:500'],
        ]);
        $today = $this->allocations->today($business);
        $date  = $data['date'] ?? $today;
        if ($date < $today) {
            return response()->json(['message' => 'An exception can only be granted for today or a later day.', 'errors' => ['date' => ['Pick today or a later day.']]], 422);
        }
        $this->abortUnlessAllowed($this->org->businessUsers($business)->pluck('id')->map(fn ($id) => (int) $id)->contains((int) $data['user_id']));

        $exception = $this->allocations->grantException($business, (int) $data['user_id'], $date, (int) $data['extra_allowed'], $data['reason'], $access->userId);

        return response()->json([
            'message' => 'Exception granted: ' . $exception->extra_allowed . ' extra inquir' . ($exception->extra_allowed === 1 ? 'y' : 'ies') . ' on ' . $date . '.',
            'data'    => $this->allocations->presentException($exception->load(['user:id,name', 'grantedBy:id,name'])),
        ], 201);
    }

    /** DELETE crm/inquiries/exceptions/{exception} */
    public function revokeException(Request $request, int $exceptionId): JsonResponse
    {
        [$business, $access] = $this->base($request);
        $this->abortUnlessAllowed($this->canGrantExceptions($access));

        $this->allocations->exceptionForBusiness($business, $exceptionId)->delete();

        return response()->json(['message' => 'Exception removed.']);
    }

    // ── Daily reports ────────────────────────────────────────────────────

    /** GET crm/inquiries/reports?from=&to= */
    public function reports(Request $request): JsonResponse
    {
        [$business, $access] = $this->base($request);
        $departments = $this->visibleDepartmentIds($business, $access);
        $this->abortUnlessAllowed($departments === null || $departments !== []);

        $data  = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);
        $today = $this->allocations->today($business);
        $to    = $data['to'] ?? $today;
        $from  = $data['from'] ?? \Illuminate\Support\Carbon::parse($to)->subDays(13)->toDateString();

        $rows = $this->reports->list($business, min($from, $to), max($from, $to), $departments);

        return response()->json([
            'data' => $rows->map(fn (InquiryReport $r) => $this->reports->present($r) + ['can_manage' => $this->canManageReport($access, $r->department_id)])->values(),
            'meta' => [
                'from'        => min($from, $to),
                'to'          => max($from, $to),
                'departments' => $this->reportDepartmentsFor($business, $access)->map(fn (Department $d) => ['id' => (int) $d->id, 'name' => $d->name])->values(),
                'settings'    => $this->settings->inquiryReport($business),
                'recipients'  => $this->reports->recipients($business),
            ] + $this->abilities($business, $access),
        ]);
    }

    /** GET crm/inquiries/reports/{report} — with the per-user figures. */
    public function report(Request $request, int $reportId): JsonResponse
    {
        [$business, $access] = $this->base($request);
        $report = $this->visibleReport($business, $access, $reportId);

        return response()->json(['data' => $this->reports->present($report->load(['department:id,name', 'reviewedBy:id,name', 'sentBy:id,name']), true)
            + ['can_manage' => $this->canManageReport($access, $report->department_id)]]);
    }

    /** POST crm/inquiries/reports/generate — { date?, department_id? }: make / refresh drafts now. */
    public function generate(Request $request): JsonResponse
    {
        [$business, $access] = $this->base($request);
        $data = $request->validate([
            'date'          => ['nullable', 'date_format:Y-m-d'],
            'department_id' => ['nullable', 'integer'],
        ]);
        $date = $data['date'] ?? $this->allocations->today($business);

        $departments = $this->reportDepartmentsFor($business, $access)
            ->filter(fn (Department $d) => $this->canManageReport($access, (int) $d->id))
            ->when(isset($data['department_id']), fn ($c) => $c->where('id', (int) $data['department_id']));
        $this->abortUnlessAllowed($departments->isNotEmpty());

        $made = $departments->map(fn (Department $d) => $this->reports->generate($business, $d, $date)->load(['department:id,name', 'reviewedBy:id,name', 'sentBy:id,name']));

        return response()->json([
            'message' => $made->count() === 1 ? 'Report updated.' : $made->count() . ' reports updated.',
            'data'    => $made->map(fn (InquiryReport $r) => $this->reports->present($r) + ['can_manage' => true])->values(),
        ]);
    }

    /** POST crm/inquiries/reports/{report}/review — { note? } */
    public function review(Request $request, int $reportId): JsonResponse
    {
        [$business, $access] = $this->base($request);
        $report = $this->manageableReport($business, $access, $reportId);
        $data   = $request->validate(['note' => ['nullable', 'string', 'max:5000']]);

        $this->reports->review($report, (int) $access->userId, $data['note'] ?? null);

        return response()->json([
            'message' => 'Review saved.',
            'data'    => $this->reports->present($report->fresh(['department:id,name', 'reviewedBy:id,name', 'sentBy:id,name']), true) + ['can_manage' => true],
        ]);
    }

    /** POST crm/inquiries/reports/{report}/send — { refresh?: bool } emails it to management (again). */
    public function send(Request $request, int $reportId): JsonResponse
    {
        [$business, $access] = $this->base($request);
        $report = $this->manageableReport($business, $access, $reportId);

        if ($request->boolean('refresh') && $report->department !== null) {
            $report = $this->reports->generate($business, $report->department, $report->report_date->toDateString(), true);
        }
        $this->reports->send($business, $report, $access->userId);

        return response()->json([
            'message' => 'Report sent to ' . implode(', ', $report->recipients ?? []) . '.',
            'data'    => $this->reports->present($report->fresh(['department:id,name', 'reviewedBy:id,name', 'sentBy:id,name']), true) + ['can_manage' => true],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function abilities(Business $business, CrmAccess $access): array
    {
        return [
            'can_team'              => $this->status->canSeeTeam($business, $access),
            'can_grant_exceptions'  => $this->canGrantExceptions($access),
            'can_manage_reports'    => ! $access->isReadOnly() && ($access->worksAllLeads() || $access->headDepartmentIds !== []),
            'can_view_reports'      => $access->viewsAllLeads() || $access->isHead(),
            'can_edit_settings'     => $access->unrestricted,
        ];
    }

    /** Exceptions to the daily limit are an administrator's call. */
    private function canGrantExceptions(CrmAccess $access): bool
    {
        return $access->unrestricted;
    }

    private function canManageReport(CrmAccess $access, ?int $departmentId): bool
    {
        return ! $access->isReadOnly() && ($access->worksAllLeads() || $access->headsDepartment($departmentId));
    }

    /** null = every department; else the departments the user heads (or heads a division of). @return int[]|null */
    private function visibleDepartmentIds(Business $business, CrmAccess $access): ?array
    {
        if ($access->viewsAllLeads()) {
            return null;
        }

        return $this->status->teamDepartments($business, $access)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function reportDepartmentsFor(Business $business, CrmAccess $access)
    {
        $visible = $this->visibleDepartmentIds($business, $access);

        return $this->reports->reportDepartments($business)
            ->filter(fn (Department $d) => $visible === null || in_array((int) $d->id, $visible, true))
            ->values();
    }

    private function visibleReport(Business $business, CrmAccess $access, int $id): InquiryReport
    {
        $report  = $this->reports->reportForBusiness($business, $id);
        $visible = $this->visibleDepartmentIds($business, $access);
        if ($visible !== null && ! in_array((int) $report->department_id, $visible, true)) {
            abort(404);
        }

        return $report;
    }

    private function manageableReport(Business $business, CrmAccess $access, int $id): InquiryReport
    {
        $report = $this->visibleReport($business, $access, $id);
        $this->abortUnlessAllowed($this->canManageReport($access, $report->department_id));

        return $report;
    }

    private function date(Request $request, Business $business): string
    {
        $date = $request->query('date');

        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : $this->allocations->today($business);
    }

    /** @return array{0: Business, 1: CrmAccess} */
    private function base(Request $request): array
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->crmAccess ??= $this->crmPermissions->accessFor($business, $request->user());
        $this->abortUnlessAllowed($access->hasCrm() && $access->userId !== null);

        return [$business, $access];
    }

    private function abortUnlessAllowed(bool $allowed): void
    {
        if (! $allowed) {
            throw new HttpResponseException(
                response()->json(['message' => 'You do not have permission to perform this action.'], 403)
            );
        }
    }
}
