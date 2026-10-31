<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadAttachment;
use Modules\CRM\Models\LeadMessage;

/**
 * Internal messages on a lead: staff discussion the customer never sees. Replies hang off a
 * top-level message (a reply to a reply goes under the same top-level one). Who may post,
 * edit and delete is LeadPolicy (canContribute / canEditMessage / canDeleteContribution).
 */
class LeadMessageService
{
    public function __construct(private readonly CrmOrgService $org) {}

    /** @return Collection<int, LeadMessage> oldest first; replies carry parent_id */
    public function forLead(Lead $lead): Collection
    {
        return $lead->messages()->with(['user:id,name', 'attachments.file'])->get();
    }

    public function messageForLead(Lead $lead, int $messageId): LeadMessage
    {
        return LeadMessage::query()
            ->where('business_id', $lead->business_id)
            ->where('lead_id', $lead->id)
            ->findOrFail($messageId);
    }

    /**
     * @param  int[]  $attachmentIds  files the poster already uploaded to this lead (and has not
     *                                shared in another message) to show inside the message
     */
    public function post(Lead $lead, int $userId, ?string $body, ?int $parentId = null, array $mentions = [], array $attachmentIds = []): LeadMessage
    {
        $parent = null;
        if ($parentId !== null) {
            $parent = LeadMessage::query()->where('lead_id', $lead->id)->find($parentId);
            if ($parent === null) {
                throw ValidationException::withMessages(['parent_id' => 'The message you replied to is gone.']);
            }
        }

        $attachments = $this->shareableAttachments($lead, $userId, $attachmentIds);
        if (count($attachments) !== count(array_unique(array_filter(array_map('intval', $attachmentIds))))) {
            throw ValidationException::withMessages(['attachment_ids' => 'One of the files cannot be shared in this message.']);
        }

        return DB::transaction(function () use ($lead, $userId, $body, $parent, $mentions, $attachments) {
            $message = LeadMessage::create([
                'business_id' => $lead->business_id,
                'lead_id'     => $lead->id,
                'user_id'     => $userId,
                'parent_id'   => $parent?->parent_id ?? $parent?->id,
                // A message may be just files
                'body'        => $attachments->isNotEmpty() && trim((string) $body) === '' ? '' : $this->cleanBody((string) $body),
                'mentions'    => $this->cleanMentions($lead, $mentions),
            ]);

            LeadAttachment::query()->whereIn('id', $attachments->pluck('id'))->update(['message_id' => $message->id]);
            $lead->recordFirstResponse();

            return $message->load(['user:id,name', 'attachments.file']);
        });
    }

    public function update(LeadMessage $message, string $body, array $mentions = []): LeadMessage
    {
        $message->loadMissing('lead');
        $message->update([
            'body'      => $this->cleanBody($body),
            'mentions'  => $this->cleanMentions($message->lead, $mentions),
            'edited_at' => now(),
        ]);

        return $message->load(['user:id,name', 'attachments.file']);
    }

    /** The message and its replies go; files shared in them stay on the lead (Files tab). */
    public function delete(LeadMessage $message): void
    {
        DB::transaction(function () use ($message) {
            $ids = LeadMessage::query()->where('parent_id', $message->id)->pluck('id')->push($message->id);
            LeadAttachment::query()->whereIn('message_id', $ids)->update(['message_id' => null]);
            $message->delete();
        });
    }

    /** @return Collection<int, LeadAttachment> of the given ids: on this lead, by this user, not in a message yet */
    private function shareableAttachments(Lead $lead, int $userId, array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        return $ids === [] ? collect() : LeadAttachment::query()
            ->where('lead_id', $lead->id)
            ->where('uploaded_by', $userId)
            ->whereNull('message_id')
            ->whereIn('id', $ids)
            ->get();
    }

    /** People who can be @mentioned on the lead: users of its business. */
    public function mentionableUsers(Lead $lead): Collection
    {
        $lead->loadMissing('business');

        return $this->org->businessUsers($lead->business);
    }

    /** @return array<string, mixed> */
    public function present(LeadMessage $message, bool $canEdit, bool $canDelete): array
    {
        return [
            'id'         => $message->id,
            'parent_id'  => $message->parent_id,
            'body'       => $message->body,
            'mentions'   => $message->mentions ?? [],
            // Shown inside the message; served by the same authorised attachment routes
            'attachments' => $message->relationLoaded('attachments') ? $message->attachments->map(fn (LeadAttachment $a) => [
                'id'        => $a->id,
                'name'      => $a->file?->original_filename ?? 'Missing file',
                'mime_type' => $a->file?->mime_type,
                'size'      => $a->file?->humanSize(),
                'is_image'  => (bool) $a->is_image,
            ])->values()->all() : [],
            'user'       => $message->user_id ? ['id' => $message->user_id, 'name' => $message->user?->name ?? 'Deleted user'] : null,
            'created_at' => $message->created_at?->toIso8601String(),
            'edited_at'  => $message->edited_at?->toIso8601String(),
            'can_edit'   => $canEdit,
            'can_delete' => $canDelete,
        ];
    }

    private function cleanBody(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Write a message first.']);
        }

        return mb_substr($body, 0, 5000);
    }

    /** Only users of the lead's business, each once. @return int[]|null */
    private function cleanMentions(Lead $lead, array $mentions): ?array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $mentions))));
        if ($ids === []) {
            return null;
        }

        $known = $this->mentionableUsers($lead)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ids   = array_values(array_intersect($ids, $known));

        return $ids ?: null;
    }
}
