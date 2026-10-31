<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\CRM\Models\FollowUp;
use Modules\CRM\Models\Lead;
use Modules\CRM\Policies\LeadPolicy;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CrmNotifier;
use Modules\CRM\Services\CrmPermissionService;
use Modules\CRM\Services\FollowUpService;
use Modules\CRM\Services\LeadAssignmentService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

/**
 * Follow-ups in the desktop: "My follow-ups" and, for heads, "Team" (with the overdue ones in
 * red), the lead window's Follow-ups tab, logging one, and the user's CRM email preference.
 * A follow-up is worked by its assignee or by whoever may edit the lead.
 */
class PosCrmFollowUpApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    private ?CrmAccess $crmAccess = null;

    public function __construct(
        private readonly CrmPermissionService $crmPermissions,
        private readonly LeadPolicy $policy,
        private readonly FollowUpService $followUps,
        private readonly LeadAssignmentService $assignments,
        private readonly CrmNotifier $notifier,
    ) {}

    /** GET crm/follow-ups?scope=mine|team&status=pending|overdue|today|done|all */
    public function index(Request $request): JsonResponse
    {
        [$business, $access] = $this->base($request);

        $data = $request->validate([
            'scope'  => ['nullable', Rule::in(['mine', 'team'])],
            'status' => ['nullable', Rule::in(['pending', 'overdue', 'today', 'done', 'all'])],
        ]);
        $scope   = $data['scope'] ?? 'mine';
        $canTeam = $this->followUps->canSeeTeam($access);
        if ($scope === 'team') {
            $this->abortUnlessAllowed($canTeam);
        }

        $rows = $this->followUps->listFor($business, $access, $scope, $data['status'] ?? 'pending');

        return response()->json([
            'data' => $rows->map(fn (FollowUp $f) => $this->followUps->present($f, $this->canWork($access, $f)))->values(),
            'meta' => [
                'scope'    => $scope,
                'can_team' => $canTeam,
                'counts'   => [
                    'mine_pending' => $this->followUps->listFor($business, $access, 'mine', 'pending')->count(),
                    'mine_overdue' => $this->followUps->listFor($business, $access, 'mine', 'overdue')->count(),
                    'team_overdue' => $canTeam ? $this->followUps->listFor($business, $access, 'team', 'overdue')->count() : null,
                ],
                ...$this->choices(),
                'email_notifications' => $access->userId ? $this->notifier->emailEnabled($business->id, $access->userId) : false,
            ],
        ]);
    }

    /** GET crm/leads/{lead}/follow-ups — the lead window's tab. */
    public function forLead(Request $request, int $leadId): JsonResponse
    {
        [, $access, $lead] = $this->leadContext($request, $leadId);
        $canEdit = $this->policy->canEdit($access, $lead);

        return response()->json([
            'data' => $this->followUps->forLead($lead)->map(fn (FollowUp $f) => $this->followUps->present($f, $canEdit || $this->isAssignee($access, $f)))->values(),
            'meta' => [
                'can_add'    => $canEdit,
                'assignees'  => $canEdit ? $this->assignments->assignableUsers($lead)->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])->values() : [],
                'default_user_id' => $lead->assigned_to !== null ? (int) $lead->assigned_to : $access->userId,
                ...$this->choices(),
            ],
        ]);
    }

    /** POST crm/leads/{lead}/follow-ups — { due_at, user_id?, channel?, notes?, quotation_id? } */
    public function store(Request $request, int $leadId): JsonResponse
    {
        [, $access, $lead] = $this->leadContext($request, $leadId);
        $this->abortUnlessAllowed($this->policy->canEdit($access, $lead));

        $data = $request->validate([
            'due_at'       => ['required', 'date'],
            'user_id'      => ['nullable', 'integer'],
            'channel'      => ['nullable', 'string', Rule::in(array_keys(FollowUp::CHANNELS))],
            'notes'        => ['nullable', 'string', 'max:2000'],
            'quotation_id' => ['nullable', 'integer'],
        ]);

        $followUp = $this->followUps->create($lead, $data, $request->user()?->id);

        return response()->json([
            'message' => 'Follow-up planned.',
            'data'    => $this->followUps->present($followUp->load(['user:id,name', 'quotation']), true),
        ], 201);
    }

    /** POST crm/follow-ups/{followUp}/log — { outcome, channel?, notes?, next_follow_up_at? } */
    public function log(Request $request, int $followUpId): JsonResponse
    {
        [$business, $access] = $this->base($request);
        $followUp = $this->workable($business, $access, $followUpId);

        $data = $request->validate([
            'outcome'           => ['required', 'string', Rule::in(array_keys(FollowUp::OUTCOMES))],
            'channel'           => ['nullable', 'string', Rule::in(array_keys(FollowUp::CHANNELS))],
            'notes'             => ['nullable', 'string', 'max:2000'],
            'next_follow_up_at' => ['nullable', 'date'],
        ]);

        [$done, $next] = $this->followUps->log($followUp, $data, $request->user()?->id);

        return response()->json([
            'message' => 'Follow-up logged.' . ($next ? ' Next one on ' . $next->due_at->format('M j, g:i A') . '.' : ''),
            'data'    => [
                'follow_up' => $this->followUps->present($done->load(['user:id,name', 'quotation']), false),
                'next'      => $next ? $this->followUps->present($next->load(['user:id,name', 'quotation']), true) : null,
            ],
        ]);
    }

    /** POST crm/follow-ups/{followUp}/cancel */
    public function cancel(Request $request, int $followUpId): JsonResponse
    {
        [$business, $access] = $this->base($request);
        $followUp = $this->workable($business, $access, $followUpId);
        $this->followUps->cancel($followUp);

        return response()->json(['message' => 'Follow-up cancelled.']);
    }

    /** GET crm/notification-preferences */
    public function preferences(Request $request): JsonResponse
    {
        [$business, $access] = $this->base($request);

        return response()->json(['data' => ['email_notifications' => $this->notifier->emailEnabled($business->id, (int) $access->userId)]]);
    }

    /** PUT crm/notification-preferences — { email_notifications: bool } */
    public function updatePreferences(Request $request): JsonResponse
    {
        [$business, $access] = $this->base($request);
        $data = $request->validate(['email_notifications' => ['required', 'boolean']]);
        $this->notifier->setEmailEnabled($business->id, (int) $access->userId, (bool) $data['email_notifications']);

        return response()->json([
            'message' => $data['email_notifications'] ? 'CRM emails turned on.' : 'CRM emails turned off — you still get them in the app.',
            'data'    => ['email_notifications' => (bool) $data['email_notifications']],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function choices(): array
    {
        return [
            'channels' => FollowUpService::options(FollowUp::CHANNELS),
            'outcomes' => FollowUpService::options(FollowUp::OUTCOMES),
        ];
    }

    private function canWork(CrmAccess $access, FollowUp $f): bool
    {
        return $this->isAssignee($access, $f) || ($f->lead !== null && $this->policy->canEdit($access, $f->lead));
    }

    private function isAssignee(CrmAccess $access, FollowUp $f): bool
    {
        return $access->userId !== null && (int) $f->user_id === $access->userId && ! $access->isReadOnly();
    }

    /** A follow-up on a lead the user sees and may work (404 / 403 otherwise). */
    private function workable(Business $business, CrmAccess $access, int $id): FollowUp
    {
        $followUp = $this->followUps->followUpForBusiness($business, $id);
        if ($followUp->lead === null || ! $this->policy->canView($access, $followUp->lead)) {
            abort(404);
        }
        $this->abortUnlessAllowed($this->canWork($access, $followUp));

        return $followUp;
    }

    /** @return array{0: Business, 1: CrmAccess} */
    private function base(Request $request): array
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->crmAccess ??= $this->crmPermissions->accessFor($business, $request->user());
        $this->abortUnlessAllowed($access->hasCrm() && $access->userId !== null);

        return [$business, $access];
    }

    /** @return array{0: Business, 1: CrmAccess, 2: Lead} */
    private function leadContext(Request $request, int $leadId): array
    {
        [$business, $access] = $this->base($request);
        $this->abortUnlessAllowed($access->can('leads_view'));

        $lead = Lead::where('business_id', $business->id)->findOrFail($leadId);
        if (! $this->policy->canView($access, $lead)) {
            abort(404);
        }

        return [$business, $access, $lead];
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
