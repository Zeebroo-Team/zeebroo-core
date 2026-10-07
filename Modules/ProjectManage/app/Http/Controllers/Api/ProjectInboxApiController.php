<?php

namespace Modules\ProjectManage\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\ProjectManage\Models\InboxThread;
use Modules\ProjectManage\Services\InboxService;
use Modules\ProjectManage\Services\TaskAttachmentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * My Projects → Inbox: Gmail-style messages between project team members.
 * Gated by "Assigned Project Access" like the rest of My Projects.
 */
class ProjectInboxApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly InboxService $inbox) {}

    /** Threads of a folder (inbox / unread / starred / sent / archive / trash) + folder badges. */
    public function index(Request $request): JsonResponse
    {
        $business = $this->inboxBusinessOrAbort($request);
        $userId   = (int) $request->user()->id;

        $filters = $request->validate([
            'folder'     => ['nullable', Rule::in(InboxService::FOLDERS)],
            'search'     => 'nullable|string|max:200',
            'project_id' => 'nullable|integer',
            'page'       => 'nullable|integer|min:1',
        ]);

        $page = $this->inbox->list($business, $userId, $filters);

        return response()->json([
            'data'     => $page['threads'],
            'has_more' => $page['has_more'],
            'counts'   => $this->inbox->counts($business, $userId),
        ]);
    }

    /** Folder badges only — polled by the desktop for the unread count. */
    public function counts(Request $request): JsonResponse
    {
        $business = $this->inboxBusinessOrAbort($request);

        return response()->json(['data' => $this->inbox->counts($business, (int) $request->user()->id)]);
    }

    /** People the caller can write to and the projects they can tag a thread with. */
    public function contacts(Request $request): JsonResponse
    {
        $business = $this->inboxBusinessOrAbort($request);

        return response()->json(['data' => $this->inbox->contacts($business, (int) $request->user()->id, $this->canManage($request, $business))]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $business = $this->inboxBusinessOrAbort($request);
        $userId   = (int) $request->user()->id;

        return response()->json(['data' => $this->inbox->show($this->resolveThread($business, $id, $userId), $userId)]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->inboxBusinessOrAbort($request);
        $userId   = (int) $request->user()->id;

        $data = $request->validate([
            'to'         => 'required|array|min:1|max:50',
            'to.*'       => 'integer',
            'subject'    => 'required|string|max:200',
            'body'       => 'required|string|max:20000',
            'project_id' => 'nullable|integer',
        ], ['to.required' => 'Add at least one recipient.']);

        $thread = $this->inbox->create($business, $userId, $this->canManage($request, $business), $data);

        return response()->json(['data' => $this->inbox->show($thread, $userId)], 201);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $business = $this->inboxBusinessOrAbort($request);
        $userId   = (int) $request->user()->id;
        $thread   = $this->resolveThread($business, $id, $userId);

        $body    = $request->validate(['body' => 'required|string|max:20000'])['body'];
        $message = $this->inbox->reply($thread, $userId, $body);

        return response()->json(['data' => $this->inbox->fmtMessage($message, $userId)], 201);
    }

    /** Read / unread / star / unstar / archive / unarchive / trash / restore / delete on several threads. */
    public function action(Request $request): JsonResponse
    {
        $business = $this->inboxBusinessOrAbort($request);
        $userId   = (int) $request->user()->id;

        $data = $request->validate([
            'ids'    => 'required|array|min:1|max:200',
            'ids.*'  => 'integer',
            'action' => ['required', Rule::in(InboxService::ACTIONS)],
        ]);

        $count = $this->inbox->applyAction($business, $userId, $data['ids'], $data['action']);

        return response()->json(['data' => ['affected' => $count, 'counts' => $this->inbox->counts($business, $userId)]]);
    }

    /** Files for a message — only its sender may attach (right after sending). */
    public function attachmentStore(Request $request, int $messageId): JsonResponse
    {
        $business = $this->inboxBusinessOrAbort($request);
        $userId   = (int) $request->user()->id;
        $message  = $this->inbox->findMessage($business, $messageId);
        abort_unless($message, 404);
        abort_unless((int) $message->user_id === $userId, 403, 'You can only attach files to your own messages.');

        $request->validate(TaskAttachmentService::uploadRules(), TaskAttachmentService::uploadMessages());
        $stored = $this->inbox->storeAttachments($message, $request->file('files', []), $userId);

        return response()->json(['data' => $stored->map(fn ($a) => $this->inbox->fmtAttachment($a))->values()], 201);
    }

    public function attachmentDownload(Request $request, int $id): StreamedResponse
    {
        $business   = $this->inboxBusinessOrAbort($request);
        $attachment = $this->inbox->findAttachmentForUser($business, $id, (int) $request->user()->id);
        abort_unless($attachment, 404);

        return $this->inbox->download($attachment);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function inboxBusinessOrAbort(Request $request): Business
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'projects_assigned');

        return $business;
    }

    /** Project managers may message anyone in the business, not only teammates. */
    private function canManage(Request $request, Business $business): bool
    {
        $member = $this->resolveMember($request, $business);

        return $member === null || $member->hasPermission('projects_access');
    }

    private function resolveThread(Business $business, int $id, int $userId): InboxThread
    {
        $thread = $this->inbox->findForUser($business, $id, $userId);
        abort_unless($thread, 404, 'This conversation does not exist or you are not part of it.');

        return $thread;
    }
}
