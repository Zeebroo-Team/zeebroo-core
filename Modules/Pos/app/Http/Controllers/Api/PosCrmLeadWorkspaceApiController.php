<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\CRM\Models\CrmAuditLog;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadAttachment;
use Modules\CRM\Models\LeadMessage;
use Modules\CRM\Models\Task;
use Modules\CRM\Policies\LeadPolicy;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CrmAuditService;
use Modules\CRM\Services\CrmPermissionService;
use Modules\CRM\Services\LeadAssignmentService;
use Modules\CRM\Services\LeadAttachmentService;
use Modules\CRM\Services\LeadMessageService;
use Modules\CRM\Services\LeadSummaryService;
use Modules\CRM\Services\TaskService;
use Modules\CRM\Services\Timeline\TimelineService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The desktop lead window's workspace: assignees (+ history), the unified timeline, internal
 * messages, files and tasks of one lead — plus the admin-only CRM audit log.
 */
class PosCrmLeadWorkspaceApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    private ?CrmAccess $crmAccess = null;

    public function __construct(
        private readonly CrmPermissionService $crmPermissions,
        private readonly LeadPolicy $policy,
        private readonly LeadAssignmentService $assignments,
        private readonly LeadMessageService $messages,
        private readonly LeadAttachmentService $attachments,
        private readonly TimelineService $timeline,
        private readonly TaskService $tasks,
        private readonly CrmAuditService $audit,
        private readonly LeadSummaryService $summaries,
    ) {}

    /** GET crm/leads/{lead}/summary — the lead with its department heads and assignees' HR details. */
    public function summary(Request $request, int $leadId): JsonResponse
    {
        [, , $lead] = $this->context($request, $leadId);

        return response()->json(['data' => $this->summaries->summary($lead)]);
    }

    /** GET crm/leads/{lead}/workspace — what the lead window needs to draw its tabs. */
    public function show(Request $request, int $leadId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $can = $this->policy->abilities($access, $lead);

        return response()->json([
            'data' => [
                'lead_id'           => $lead->id,
                'can'               => $can,
                'assignees'         => $this->assignments->present($this->assignments->assignees($lead)),
                'assignable_users'  => $can['can_assign'] ? $this->users($this->assignments->assignableUsers($lead)) : [],
                'mentionable_users' => $can['can_contribute'] ? $this->users($this->messages->mentionableUsers($lead)) : [],
                // Tasks on the lead go to someone of its department (see storeTask)
                'task_assignees'    => $can['can_edit'] ? $this->users($this->assignments->assignableUsers($lead)) : [],
                'can_add_tasks'     => $can['can_edit'] && $access->can('tasks_create'),
                'timeline_types'    => $this->timeline->types(),
                'attachment_rules'  => ['mimes' => LeadAttachmentService::ALLOWED_MIMES, 'max_kb' => LeadAttachmentService::maxKb()],
                'counts'            => [
                    'messages' => $lead->messages()->count(),
                    'files'    => $lead->attachments()->count(),
                    'tasks'    => $lead->tasks()->where('status', Task::STATUS_PENDING)->count(),
                    'follow_ups' => $lead->followUps()->pending()->count(),
                ],
                'follow_ups_overdue' => $lead->followUps()->overdue((int) $lead->business_id)->count(),
            ],
        ]);
    }

    // ── Assignees ────────────────────────────────────────────────────────

    /** GET crm/leads/{lead}/assignees — who works it, who can be picked, and the history. */
    public function assignees(Request $request, int $leadId): JsonResponse
    {
        [, $access, $lead] = $this->context($request, $leadId);

        return $this->assigneesResponse($access, $lead);
    }

    /** PUT crm/leads/{lead}/assignees — { user_ids[], primary_user_id?, note? }: replace the assignees. */
    public function syncAssignees(Request $request, int $leadId): JsonResponse
    {
        [, $access, $lead] = $this->assignContext($request, $leadId);

        $data = $request->validate([
            'user_ids'        => ['present', 'array', 'max:50'],
            'user_ids.*'      => ['integer'],
            'primary_user_id' => ['nullable', 'integer'],
            'note'            => ['nullable', 'string', 'max:2000'],
        ]);

        $lead = $this->assignments->sync($lead, $data['user_ids'], isset($data['primary_user_id']) ? (int) $data['primary_user_id'] : null,
            $request->user()?->id, $data['note'] ?? null);

        return $this->assigneesResponse($access, $lead, 'Assignees saved.');
    }

    /** POST crm/leads/{lead}/assignees — { user_ids[], note? }: add people, keeping who is on it. */
    public function addAssignees(Request $request, int $leadId): JsonResponse
    {
        [, $access, $lead] = $this->assignContext($request, $leadId);

        $data = $request->validate([
            'user_ids'   => ['required', 'array', 'min:1', 'max:50'],
            'user_ids.*' => ['integer'],
            'note'       => ['nullable', 'string', 'max:2000'],
        ]);

        $lead = $this->assignments->add($lead, $data['user_ids'], $request->user()?->id, $data['note'] ?? null);

        return $this->assigneesResponse($access, $lead, 'Assignees added.');
    }

    /** DELETE crm/leads/{lead}/assignees/{user} */
    public function removeAssignee(Request $request, int $leadId, int $userId): JsonResponse
    {
        [, $access, $lead] = $this->assignContext($request, $leadId);

        $lead = $this->assignments->remove($lead, $userId, $request->user()?->id, $request->input('note'));

        return $this->assigneesResponse($access, $lead, 'Assignee removed.');
    }

    /** POST crm/leads/{lead}/assignees/{user}/primary */
    public function primaryAssignee(Request $request, int $leadId, int $userId): JsonResponse
    {
        [, $access, $lead] = $this->assignContext($request, $leadId);

        $lead = $this->assignments->setPrimary($lead, $userId, $request->user()?->id);

        return $this->assigneesResponse($access, $lead, 'Primary assignee changed.');
    }

    // ── Timeline ─────────────────────────────────────────────────────────

    /** GET crm/leads/{lead}/timeline?types=activity,message — everything that happened, newest first. */
    public function timeline(Request $request, int $leadId): JsonResponse
    {
        [, , $lead] = $this->context($request, $leadId);

        $types = filled($request->query('types'))
            ? array_values(array_filter(explode(',', (string) $request->query('types'))))
            : null;

        return response()->json([
            'data' => $this->timeline->forLead($lead, $types),
            'meta' => ['types' => $this->timeline->types()],
        ]);
    }

    // ── Messages ─────────────────────────────────────────────────────────

    /** GET crm/leads/{lead}/messages — oldest first; replies carry parent_id. */
    public function messages(Request $request, int $leadId): JsonResponse
    {
        [, $access, $lead] = $this->context($request, $leadId);

        return response()->json([
            'data' => $this->messages->forLead($lead)->map(fn (LeadMessage $m) => $this->presentMessage($access, $lead, $m))->values(),
            'meta' => ['can_post' => $this->policy->canContribute($access, $lead)],
        ]);
    }

    /**
     * POST crm/leads/{lead}/messages — { body?, parent_id?, mentions[]?, attachment_ids[]? }.
     * Files go up first (POST …/attachments, one per call) and are shared here by id; a message
     * needs text, files or both.
     */
    public function storeMessage(Request $request, int $leadId): JsonResponse
    {
        [, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessAllowed($this->policy->canContribute($access, $lead));

        $data = $request->validate([
            'body'             => ['required_without:attachment_ids', 'nullable', 'string', 'max:5000'],
            'parent_id'        => ['nullable', 'integer'],
            'mentions'         => ['nullable', 'array', 'max:20'],
            'mentions.*'       => ['integer'],
            'attachment_ids'   => ['nullable', 'array', 'max:10'],
            'attachment_ids.*' => ['integer'],
        ]);

        $message = $this->messages->post($lead, (int) $request->user()->id, $data['body'] ?? null,
            isset($data['parent_id']) ? (int) $data['parent_id'] : null, $data['mentions'] ?? [], $data['attachment_ids'] ?? []);

        return response()->json(['message' => 'Message posted.', 'data' => $this->presentMessage($access, $lead, $message)], 201);
    }

    /** PUT crm/leads/{lead}/messages/{message} — { body, mentions[]? }: the author edits it. */
    public function updateMessage(Request $request, int $leadId, int $messageId): JsonResponse
    {
        [, $access, $lead] = $this->context($request, $leadId);
        $message = $this->messages->messageForLead($lead, $messageId);
        $this->abortUnlessAllowed($this->policy->canEditMessage($access, $lead, $message));

        $data = $request->validate([
            'body'       => ['required', 'string', 'max:5000'],
            'mentions'   => ['nullable', 'array', 'max:20'],
            'mentions.*' => ['integer'],
        ]);

        $message = $this->messages->update($message, $data['body'], $data['mentions'] ?? []);

        return response()->json(['message' => 'Message updated.', 'data' => $this->presentMessage($access, $lead, $message)]);
    }

    /** DELETE crm/leads/{lead}/messages/{message} — author, the lead's head or an admin. */
    public function destroyMessage(Request $request, int $leadId, int $messageId): JsonResponse
    {
        [, $access, $lead] = $this->context($request, $leadId);
        $message = $this->messages->messageForLead($lead, $messageId);
        $this->abortUnlessAllowed($this->policy->canDeleteContribution($access, $lead, $message->user_id ? (int) $message->user_id : null));

        $this->messages->delete($message);

        return response()->json(['message' => 'Message deleted.']);
    }

    // ── Files ────────────────────────────────────────────────────────────

    /** GET crm/leads/{lead}/attachments — newest first. */
    public function attachments(Request $request, int $leadId): JsonResponse
    {
        [, $access, $lead] = $this->context($request, $leadId);

        return response()->json([
            'data' => $this->attachments->forLead($lead)->map(fn (LeadAttachment $a) => $this->presentAttachment($access, $lead, $a))->values(),
            'meta' => [
                'can_upload' => $this->policy->canContribute($access, $lead),
                'rules'      => ['mimes' => LeadAttachmentService::ALLOWED_MIMES, 'max_kb' => LeadAttachmentService::maxKb()],
            ],
        ]);
    }

    /** POST crm/leads/{lead}/attachments — multipart files[] (the desktop sends one file per call). */
    public function storeAttachments(Request $request, int $leadId): JsonResponse
    {
        [, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessAllowed($this->policy->canContribute($access, $lead));

        $request->validate([
            'files'   => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => LeadAttachmentService::fileRules(),
        ], [
            'files.*.mimes' => 'Allowed file types: ' . implode(', ', LeadAttachmentService::ALLOWED_MIMES) . '.',
            'files.*.max'   => 'Each file can be at most ' . round(LeadAttachmentService::maxKb() / 1024) . ' MB.',
        ]);

        $saved = collect($request->file('files', []))
            ->map(fn ($file) => $this->presentAttachment($access, $lead, $this->attachments->upload($lead, $file, $request->user()?->id)))
            ->values();

        return response()->json(['message' => $saved->count() === 1 ? 'File uploaded.' : 'Files uploaded.', 'data' => $saved], 201);
    }

    /** GET crm/leads/{lead}/attachments/{attachment}/download?inline=1 — anyone who can view the lead. */
    public function downloadAttachment(Request $request, int $leadId, int $attachmentId): StreamedResponse
    {
        [, , $lead] = $this->context($request, $leadId);

        return $this->attachments->download($this->attachments->attachmentForLead($lead, $attachmentId), $request->boolean('inline'));
    }

    /** DELETE crm/leads/{lead}/attachments/{attachment} — uploader, the lead's head or an admin. */
    public function destroyAttachment(Request $request, int $leadId, int $attachmentId): JsonResponse
    {
        [, $access, $lead] = $this->context($request, $leadId);
        $attachment = $this->attachments->attachmentForLead($lead, $attachmentId);
        $this->abortUnlessAllowed($this->policy->canDeleteContribution($access, $lead, $attachment->uploaded_by ? (int) $attachment->uploaded_by : null));

        $this->attachments->delete($attachment);

        return response()->json(['message' => 'File deleted.']);
    }

    // ── Tasks ────────────────────────────────────────────────────────────

    /** GET crm/leads/{lead}/tasks — open first, then by due date. */
    public function tasks(Request $request, int $leadId): JsonResponse
    {
        [, $access, $lead] = $this->context($request, $leadId);
        abort_unless($access->can('tasks_view'), 403);

        $rows = $lead->tasks()->with('assignedTo:id,name')->get()
            ->sortBy(fn (Task $t) => [$t->isCompleted() ? 1 : 0, $t->due_at?->getTimestamp() ?? PHP_INT_MAX])
            ->values()
            ->map(fn (Task $t) => [
                'id'               => $t->id,
                'title'            => $t->title,
                'description'      => $t->description,
                'due_at'           => $t->due_at?->toIso8601String(),
                'status'           => $t->status,
                'is_overdue'       => $t->isOverdue(),
                'assigned_to'      => $t->assigned_to,
                'assigned_to_name' => $t->assignedTo?->name,
                'completed_at'     => $t->completed_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows, 'meta' => ['can_work' => $this->policy->canEdit($access, $lead) && $access->can('tasks_create')]]);
    }

    /** POST crm/leads/{lead}/tasks — { title, description?, due_at?, assigned_to? }: a task on this lead. */
    public function storeTask(Request $request, int $leadId): JsonResponse
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessPerm($request, $business, 'crm_tasks');
        $this->abortUnlessAllowed($access->can('tasks_create') && $this->policy->canEdit($access, $lead));

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_at'      => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', Rule::in($this->assignments->assignableUsers($lead)->pluck('id')->all())],
        ], ['assigned_to.in' => 'Tasks on this lead go to someone of its department.']);

        $task = $this->tasks->create($business, $data, $lead, $request->user()?->id);

        return response()->json(['message' => 'Task added.', 'data' => $task], 201);
    }

    // ── Audit log (admins) ───────────────────────────────────────────────

    /** GET crm/audit-logs?subject=&subject_id=&event=&user_id=&from=&to=&page= */
    public function auditLogs(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessAllowed($this->policy->canViewAuditLog($this->access($request, $business)));

        $filters = $request->validate([
            'subject'    => ['nullable', Rule::in(array_keys(CrmAuditLog::SUBJECTS))],
            'subject_id' => ['nullable', 'integer'],
            'event'      => ['nullable', Rule::in(array_keys(CrmAuditLog::events()))],
            'user_id'    => ['nullable', 'integer'],
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date'],
            'per_page'   => ['nullable', 'integer', 'min:10', 'max:200'],
        ]);

        $page = $this->audit->list($business, $filters, (int) ($filters['per_page'] ?? 50));

        return response()->json([
            'data' => collect($page->items())->map(fn (CrmAuditLog $log) => $this->audit->present($log))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
                'subjects'     => collect(CrmAuditLog::SUBJECTS)->map(fn ($s, $key) => ['key' => $key, 'label' => $s[1]])->values(),
                'events'       => collect(CrmAuditLog::events())->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
                'actors'       => $this->audit->actors($business)->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
            ],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** @return array{0: Business, 1: CrmAccess, 2: Lead} a lead of this business the user may see (404 otherwise) */
    private function context(Request $request, int $leadId): array
    {
        $business = $this->businessOrAbort($request);
        $access   = $this->access($request, $business);
        if (! $access->can('leads_view')) {
            $this->abortUnlessAllowed(false);
        }

        $lead = Lead::where('business_id', $business->id)->findOrFail($leadId);
        if (! $this->policy->canView($access, $lead)) {
            abort(404);
        }

        return [$business, $access, $lead];
    }

    /** As context(), for changing the assignees: department head / admin only. */
    private function assignContext(Request $request, int $leadId): array
    {
        [$business, $access, $lead] = $this->context($request, $leadId);
        $this->abortUnlessPerm($request, $business, 'crm_pipeline');
        $this->abortUnlessAllowed($this->policy->canAssign($access, $lead));

        return [$business, $access, $lead];
    }

    private function assigneesResponse(CrmAccess $access, Lead $lead, ?string $message = null): JsonResponse
    {
        $canAssign = $this->policy->canAssign($access, $lead);

        return response()->json(array_filter(['message' => $message]) + [
            'data' => [
                'assignees'        => $this->assignments->present($this->assignments->assignees($lead)),
                'assigned_to'      => $lead->assigned_to,
                'status_key'       => $lead->fresh('stage')?->statusKey(),
                'stage_id'         => $lead->stage_id,
                'can_assign'       => $canAssign,
                'assignable_users' => $canAssign ? $this->users($this->assignments->assignableUsers($lead)) : [],
                'history'          => $this->assignments->presentHistory($this->assignments->history($lead)),
            ],
        ]);
    }

    private function presentMessage(CrmAccess $access, Lead $lead, LeadMessage $message): array
    {
        return $this->messages->present(
            $message,
            $this->policy->canEditMessage($access, $lead, $message),
            $this->policy->canDeleteContribution($access, $lead, $message->user_id ? (int) $message->user_id : null),
        );
    }

    private function presentAttachment(CrmAccess $access, Lead $lead, LeadAttachment $attachment): array
    {
        return $this->attachments->present(
            $attachment,
            $this->policy->canDeleteContribution($access, $lead, $attachment->uploaded_by ? (int) $attachment->uploaded_by : null),
        );
    }

    private function users($users): array
    {
        return collect($users)->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])->values()->all();
    }

    private function access(Request $request, Business $business): CrmAccess
    {
        return $this->crmAccess ??= $this->crmPermissions->accessFor($business, $request->user());
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
