<?php

namespace Modules\ProjectManage\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Pos\Services\PosNotificationService;
use Modules\ProjectManage\Models\InboxAttachment;
use Modules\ProjectManage\Models\InboxMessage;
use Modules\ProjectManage\Models\InboxParticipant;
use Modules\ProjectManage\Models\InboxThread;
use Modules\ProjectManage\Models\Project;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * My Projects → Inbox: Gmail-style threads between project team members.
 *
 * Who you can write to: managers (projects_access) — any active business user; everyone
 * else — teammates who share a non-archived project with them (team member or task assignee).
 * Each participant has their own read / starred / archived / trashed state.
 */
class InboxService
{
    const FOLDERS = ['inbox', 'unread', 'starred', 'sent', 'archive', 'trash'];

    const ACTIONS = ['read', 'unread', 'star', 'unstar', 'archive', 'unarchive', 'trash', 'restore', 'delete'];

    const PER_PAGE = 50;

    public function __construct(private readonly ProjectService $projects) {}

    // ── Contacts ─────────────────────────────────────────────────────────────

    /**
     * People the user may message plus the projects they can tag a thread with
     * (each with its team, so the compose window can add a whole team at once).
     *
     * @return array{contacts: Collection, projects: Collection}
     */
    public function contacts(Business $business, int $userId, bool $canManage): array
    {
        $projectQuery = Project::query()
            ->where('business_id', $business->id)
            ->where('status', '!=', Project::STATUS_ARCHIVED);
        if (! $canManage) {
            $projectQuery->where(fn ($q) => $q->whereHas('members', fn ($m) => $m->where('users.id', $userId))
                                              ->orWhereHas('tasks.assignees', fn ($a) => $a->where('users.id', $userId)));
        }
        $projects = $projectQuery->orderBy('name')->get(['id', 'name', 'color']);
        $ids      = $projects->pluck('id');

        // Team of each project: members + anyone assigned a task in it.
        $team = DB::table('pm_project_members')->whereIn('project_id', $ids)->select('project_id', 'user_id')
            ->union(
                DB::table('pm_task_assignees as a')->join('pm_tasks as t', 't.id', '=', 'a.task_id')
                    ->whereIn('t.project_id', $ids)->select('t.project_id', 'a.user_id')
            )
            ->get()
            ->groupBy('project_id')
            ->map(fn ($rows) => $rows->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values());

        $users   = $this->projects->businessUsers($business);
        $allowed = $canManage
            ? $users->pluck('id')
            : $team->flatten()->unique();
        $allowed = $allowed->map(fn ($id) => (int) $id)->reject(fn ($id) => $id === $userId)->all();

        $contacts = $users
            ->filter(fn ($u) => in_array((int) $u['id'], $allowed, true))
            ->map(fn ($u) => $u + [
                'initial'     => $this->initial($u['name']),
                'project_ids' => $team->filter(fn ($members) => $members->contains((int) $u['id']))->keys()->map(fn ($id) => (int) $id)->values(),
            ])
            ->values();

        $contactIds = $contacts->pluck('id')->all();

        return [
            'contacts' => $contacts,
            'projects' => $projects->map(fn (Project $p) => [
                'id'         => (int) $p->id,
                'name'       => $p->name,
                'color'      => $p->color,
                'member_ids' => ($team[$p->id] ?? collect())->filter(fn ($id) => in_array($id, $contactIds, true))->values(),
            ])->values(),
        ];
    }

    // ── Listing ──────────────────────────────────────────────────────────────

    /**
     * One page of the user's threads in a folder, newest activity first.
     *
     * @param array{folder?: string, search?: string, project_id?: int|string|null, page?: int} $filters
     * @return array{threads: Collection, has_more: bool}
     */
    public function list(Business $business, int $userId, array $filters = []): array
    {
        $folder = in_array($filters['folder'] ?? 'inbox', self::FOLDERS, true) ? $filters['folder'] ?? 'inbox' : 'inbox';
        $page   = max(1, (int) ($filters['page'] ?? 1));

        $query = $this->baseQuery($business, $userId);
        $this->applyFolder($query, $folder, $userId);

        if (! empty($filters['project_id'])) {
            $query->where('pm_inbox_threads.project_id', (int) $filters['project_id']);
        }

        if ($term = trim((string) ($filters['search'] ?? ''))) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
            $query->where(fn ($q) => $q
                ->where('pm_inbox_threads.subject', 'like', $like)
                ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', $like))
                ->orWhereHas('participants.user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like))
                ->orWhereHas('attachments', fn ($a) => $a->where('original_name', 'like', $like)));
        }

        $rows = $query
            ->orderByDesc('pm_inbox_threads.last_message_at')
            ->orderByDesc('pm_inbox_threads.id')
            ->offset(($page - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE + 1)
            ->get();

        return [
            'threads'  => $rows->take(self::PER_PAGE)->map(fn (InboxThread $t) => $this->fmtThread($t, $userId))->values(),
            'has_more' => $rows->count() > self::PER_PAGE,
        ];
    }

    /**
     * Folder badges: unread threads in the inbox, starred threads.
     *
     * @return array{inbox:int, starred:int}
     */
    public function counts(Business $business, int $userId): array
    {
        $inbox = $this->baseQuery($business, $userId, withExtras: false);
        $this->applyFolder($inbox, 'unread', $userId);

        $starred = $this->baseQuery($business, $userId, withExtras: false);
        $this->applyFolder($starred, 'starred', $userId);

        return ['inbox' => $inbox->count(), 'starred' => $starred->count()];
    }

    // ── Thread detail ────────────────────────────────────────────────────────

    /** A thread of the business the user takes part in, or null. */
    public function findForUser(Business $business, int $threadId, int $userId): ?InboxThread
    {
        return InboxThread::query()
            ->where('business_id', $business->id)
            ->where('id', $threadId)
            ->whereHas('participants', fn ($p) => $p->where('user_id', $userId))
            ->first();
    }

    /** Every message of the thread (oldest first) with attachments; marks the thread read. */
    public function show(InboxThread $thread, int $userId): array
    {
        $thread->load(['project:id,name,color', 'participants.user:id,name,email,avatar_path', 'messages.user:id,name,avatar_path', 'messages.attachments']);
        $me = $thread->participants->firstWhere('user_id', $userId);
        $previousRead = (int) ($me?->last_read_message_id ?? 0);

        $me?->forceFill(['last_read_message_id' => (int) $thread->messages->max('id')])->save();

        return [
            'id'              => (int) $thread->id,
            'subject'         => $thread->subject,
            'project'         => $this->fmtProject($thread),
            'participants'    => $this->fmtParticipants($thread, $userId),
            'is_starred'      => (bool) $me?->is_starred,
            'is_archived'     => $me?->archived_at !== null,
            'is_trashed'      => $me?->trashed_at !== null,
            'last_message_at' => $thread->last_message_at?->toDateTimeString(),
            'messages'        => $thread->messages->map(fn (InboxMessage $m) => $this->fmtMessage($m, $userId) + [
                // Highlights what arrived since the user last opened the thread.
                'is_new' => (int) $m->user_id !== $userId && (int) $m->id > $previousRead,
            ])->values(),
        ];
    }

    // ── Writing ──────────────────────────────────────────────────────────────

    /**
     * Starts a thread. Recipients must be among the sender's contacts; the optional
     * project must be one the sender may tag.
     *
     * @param array{to: int[], subject: string, body: string, project_id?: int|null} $data
     */
    public function create(Business $business, int $senderId, bool $canManage, array $data): InboxThread
    {
        $book       = $this->contacts($business, $senderId, $canManage);
        $allowedIds = $book['contacts']->pluck('id')->all();
        $to         = array_values(array_unique(array_map('intval', $data['to'])));

        if (array_diff($to, $allowedIds)) {
            throw ValidationException::withMessages(['to' => 'You can only message people who share a project with you.']);
        }

        $projectId = $data['project_id'] ?? null;
        if ($projectId && ! $book['projects']->contains('id', (int) $projectId)) {
            throw ValidationException::withMessages(['project_id' => 'You are not working on that project.']);
        }

        [$thread, $message] = DB::transaction(function () use ($business, $senderId, $to, $data, $projectId) {
            $thread = InboxThread::create([
                'business_id'     => $business->id,
                'project_id'      => $projectId ?: null,
                'created_by'      => $senderId,
                'subject'         => $data['subject'],
                'last_message_at' => now(),
            ]);

            $message = $thread->messages()->create(['user_id' => $senderId, 'body' => $data['body']]);

            foreach (array_merge([$senderId], $to) as $uid) {
                $thread->participants()->create([
                    'user_id'              => $uid,
                    'last_read_message_id' => $uid === $senderId ? $message->id : null,
                ]);
            }

            return [$thread, $message];
        });

        $this->notify($thread, $message, $to, $senderId);

        return $thread;
    }

    /**
     * Replies to every participant. Like Gmail, the thread comes back to the inbox
     * of anyone who had archived or trashed it.
     */
    public function reply(InboxThread $thread, int $senderId, string $body): InboxMessage
    {
        $message = DB::transaction(function () use ($thread, $senderId, $body) {
            $message = $thread->messages()->create(['user_id' => $senderId, 'body' => $body]);
            $thread->forceFill(['last_message_at' => $message->created_at])->save();

            InboxParticipant::where('thread_id', $thread->id)->where('user_id', '!=', $senderId)
                ->update(['archived_at' => null, 'trashed_at' => null]);
            InboxParticipant::where('thread_id', $thread->id)->where('user_id', $senderId)
                ->update(['last_read_message_id' => $message->id, 'trashed_at' => null]);

            return $message;
        });

        $others = $thread->participants()->where('user_id', '!=', $senderId)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $this->notify($thread, $message, $others, $senderId);

        return $message->load(['user:id,name,avatar_path', 'attachments']);
    }

    /**
     * Applies a Gmail-style action to the user's copy of several threads.
     * "delete" removes trashed threads for good (the thread itself goes once nobody has it).
     *
     * @param int[] $threadIds
     */
    public function applyAction(Business $business, int $userId, array $threadIds, string $action): int
    {
        $rows = InboxParticipant::query()
            ->where('user_id', $userId)
            ->whereIn('thread_id', $threadIds)
            ->whereHas('thread', fn ($t) => $t->where('business_id', $business->id));

        $now = now();

        if ($action === 'delete') {
            $ids = (clone $rows)->whereNotNull('trashed_at')->pluck('thread_id');
            InboxParticipant::where('user_id', $userId)->whereIn('thread_id', $ids)->delete();

            InboxThread::whereIn('id', $ids)->whereDoesntHave('participants')->get()
                ->each(function (InboxThread $t) {
                    Storage::disk(InboxAttachment::DISK)->deleteDirectory($this->threadDir($t));
                    $t->delete();
                });

            return $ids->count();
        }

        return $rows->update(match ($action) {
            'read'      => ['last_read_message_id' => DB::raw('(select max(m.id) from pm_inbox_messages m where m.thread_id = pm_inbox_participants.thread_id)')],
            'unread'    => ['last_read_message_id' => null],
            'star'      => ['is_starred' => true],
            'unstar'    => ['is_starred' => false],
            'archive'   => ['archived_at' => $now, 'trashed_at' => null],
            'unarchive' => ['archived_at' => null, 'trashed_at' => null],
            'trash'     => ['trashed_at' => $now],
            'restore'   => ['trashed_at' => null],
        });
    }

    // ── Attachments ──────────────────────────────────────────────────────────

    /** A message of the business, with its thread. */
    public function findMessage(Business $business, int $messageId): ?InboxMessage
    {
        return InboxMessage::with('thread')
            ->where('id', $messageId)
            ->whereHas('thread', fn ($t) => $t->where('business_id', $business->id))
            ->first();
    }

    /** An attachment whose thread the user takes part in. */
    public function findAttachmentForUser(Business $business, int $attachmentId, int $userId): ?InboxAttachment
    {
        return InboxAttachment::with('message.thread')
            ->where('id', $attachmentId)
            ->whereHas('message.thread', fn ($t) => $t->where('business_id', $business->id)
                ->whereHas('participants', fn ($p) => $p->where('user_id', $userId)))
            ->first();
    }

    /**
     * @param UploadedFile[] $files
     * @return Collection<int, InboxAttachment>
     */
    public function storeAttachments(InboxMessage $message, array $files, int $userId): Collection
    {
        $dir = $this->threadDir($message->thread) . '/' . $message->id;

        return collect($files)->map(function (UploadedFile $file) use ($message, $dir, $userId) {
            return InboxAttachment::create([
                'message_id'    => $message->id,
                'user_id'       => $userId,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'stored_path'   => $file->store($dir, InboxAttachment::DISK),
                'mime_type'     => $file->getMimeType() ?: $file->getClientMimeType(),
                'size_bytes'    => $file->getSize() ?: null,
            ]);
        });
    }

    public function download(InboxAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk(InboxAttachment::DISK);
        abort_unless($disk->exists($attachment->stored_path), 404, 'The file is missing on the server.');

        return $disk->download($attachment->stored_path, $attachment->original_name);
    }

    public function fmtAttachment(InboxAttachment $a): array
    {
        return [
            'id'         => (int) $a->id,
            'message_id' => (int) $a->message_id,
            'name'       => $a->original_name,
            'mime_type'  => $a->mime_type,
            'size_bytes' => $a->size_bytes,
            'created_at' => $a->created_at?->toDateTimeString(),
        ];
    }

    public function fmtMessage(InboxMessage $m, int $userId): array
    {
        $name = $m->user?->name ?? 'Former user';

        return [
            'id'          => (int) $m->id,
            'user_id'     => $m->user_id ? (int) $m->user_id : null,
            'sender_name' => $name,
            'initial'     => $this->initial($name),
            'avatar_url'  => $m->user?->avatarUrl(),
            'is_mine'     => (int) $m->user_id === $userId,
            'body'        => $m->body,
            'created_at'  => $m->created_at?->toDateTimeString(),
            'attachments' => $m->attachments->map(fn ($a) => $this->fmtAttachment($a))->values(),
        ];
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /** Threads of the business joined to the user's participant row (as "p"). */
    private function baseQuery(Business $business, int $userId, bool $withExtras = true)
    {
        $query = InboxThread::query()
            ->join('pm_inbox_participants as p', fn ($j) => $j->on('p.thread_id', '=', 'pm_inbox_threads.id')->where('p.user_id', '=', $userId))
            ->where('pm_inbox_threads.business_id', $business->id)
            ->select('pm_inbox_threads.*', 'p.is_starred as my_is_starred',
                     'p.archived_at as my_archived_at', 'p.trashed_at as my_trashed_at');

        if ($withExtras) {
            $query->addSelect(['unread_count' => $this->unreadSubquery($userId)])
                ->withCount(['messages', 'attachments'])
                ->with(['project:id,name,color', 'participants.user:id,name,avatar_path', 'latestMessage.user:id,name']);
        }

        return $query;
    }

    /** Count of messages from other people newer than the user's last read time. */
    private function unreadSubquery(int $userId)
    {
        return $this->unreadScope(
            InboxMessage::query()->selectRaw('count(*)')->whereColumn('pm_inbox_messages.thread_id', 'pm_inbox_threads.id'),
            $userId
        );
    }

    /** Narrows a pm_inbox_messages query to ones unread by the user ("p" = their participant row). */
    private function unreadScope($q, int $userId)
    {
        return $q
            ->where(fn ($w) => $w->whereNull('pm_inbox_messages.user_id')->orWhere('pm_inbox_messages.user_id', '!=', $userId))
            ->where(fn ($w) => $w->whereNull('p.last_read_message_id')->orWhereColumn('pm_inbox_messages.id', '>', 'p.last_read_message_id'));
    }

    private function applyFolder($query, string $folder, int $userId): void
    {
        $fromOthers = fn ($q) => $q->where(fn ($w) => $w->whereNull('user_id')->orWhere('user_id', '!=', $userId));

        match ($folder) {
            'inbox'   => $query->whereNull('p.trashed_at')->whereNull('p.archived_at')->whereHas('messages', $fromOthers),
            'unread'  => $query->whereNull('p.trashed_at')->whereNull('p.archived_at')->whereHas('messages', fn ($m) => $this->unreadScope($m, $userId)),
            'starred' => $query->whereNull('p.trashed_at')->where('p.is_starred', true),
            'sent'    => $query->whereNull('p.trashed_at')->whereHas('messages', fn ($m) => $m->where('user_id', $userId)),
            'archive' => $query->whereNull('p.trashed_at')->whereNotNull('p.archived_at'),
            'trash'   => $query->whereNotNull('p.trashed_at'),
        };
    }

    private function fmtThread(InboxThread $t, int $userId): array
    {
        $last = $t->latestMessage;
        $name = $last?->user?->name ?? 'Former user';

        return [
            'id'                => (int) $t->id,
            'subject'           => $t->subject,
            'project'           => $this->fmtProject($t),
            'participants'      => $this->fmtParticipants($t, $userId),
            'last_message'      => $last ? [
                'sender_id'   => $last->user_id ? (int) $last->user_id : null,
                'sender_name' => $name,
                'is_mine'     => (int) $last->user_id === $userId,
                'snippet'     => Str::limit(preg_replace('/\s+/', ' ', trim($last->body)), 160),
            ] : null,
            'message_count'     => (int) $t->messages_count,
            'attachments_count' => (int) $t->attachments_count,
            'unread_count'      => (int) $t->unread_count,
            'is_unread'         => (int) $t->unread_count > 0,
            'is_starred'        => (bool) $t->my_is_starred,
            'is_archived'       => $t->my_archived_at !== null,
            'is_trashed'        => $t->my_trashed_at !== null,
            'last_message_at'   => $t->last_message_at?->toDateTimeString(),
        ];
    }

    private function fmtProject(InboxThread $t): ?array
    {
        return $t->project ? ['id' => (int) $t->project->id, 'name' => $t->project->name, 'color' => $t->project->color] : null;
    }

    private function fmtParticipants(InboxThread $t, int $userId): array
    {
        return $t->participants
            ->map(fn (InboxParticipant $p) => [
                'id'      => (int) $p->user_id,
                'name'    => $p->user?->name ?? 'Former user',
                'email'   => $p->user?->email,
                'initial'    => $this->initial($p->user?->name ?? '?'),
                'avatar_url' => $p->user?->avatarUrl(),
                'is_me'      => (int) $p->user_id === $userId,
            ])
            ->sortBy(fn ($p) => $p['is_me'] ? 1 : 0)   // others first
            ->values()
            ->all();
    }

    private function notify(InboxThread $thread, InboxMessage $message, array $userIds, int $senderId): void
    {
        try {
            app(PosNotificationService::class)->notifyInboxMessage($thread->loadMissing('business'), $message, $userIds, $senderId);
        } catch (\Throwable $e) {
            report($e);   // a failed notification must not lose the message
        }
    }

    private function threadDir(InboxThread $t): string
    {
        return 'pm-inbox-attachments/' . $t->business_id . '/' . $t->id;
    }

    private function initial(?string $name): string
    {
        return mb_strtoupper(mb_substr(trim((string) $name), 0, 1)) ?: '?';
    }
}
