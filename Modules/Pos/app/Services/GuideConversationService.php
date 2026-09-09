<?php

namespace Modules\Pos\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Business\Models\Business;
use Modules\Pos\Models\GuideConversation;
use Modules\Pos\Models\GuideMessage;
use Modules\Pos\Models\PosCashier;

class GuideConversationService
{
    /**
     * Identifies the authenticated principal — a login User or a PosCashier —
     * since either can hold the Sanctum token that calls the guide endpoints.
     */
    public function actorKey(Request $request): string
    {
        $user = $request->user();
        if ($user instanceof PosCashier) {
            return 'cashier:'.$user->id;
        }

        return 'user:'.($user?->id ?? 0);
    }

    /**
     * @return Collection<int, GuideConversation>
     */
    public function listForActor(?Business $business, string $actorKey): Collection
    {
        return GuideConversation::query()
            ->where('actor_key', $actorKey)
            ->when(
                $business,
                fn ($q) => $q->where('business_id', $business->id),
                fn ($q) => $q->whereNull('business_id')
            )
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'last_message_at']);
    }

    public function findOwned(string $actorKey, int $conversationId): ?GuideConversation
    {
        return GuideConversation::query()
            ->where('actor_key', $actorKey)
            ->find($conversationId);
    }

    public function withMessages(GuideConversation $conversation): GuideConversation
    {
        return $conversation->load(['messages' => fn ($q) => $q->orderBy('id')]);
    }

    public function destroy(GuideConversation $conversation): void
    {
        $conversation->delete();
    }

    public function recordTurn(
        ?Business $business,
        string $actorKey,
        ?GuideConversation $conversation,
        string $userContent,
        bool $userIsVoice,
        string $assistantReply,
    ): GuideConversation {
        if ($conversation === null) {
            $conversation = GuideConversation::create([
                'business_id' => $business?->id,
                'actor_key' => $actorKey,
                'title' => $this->makeTitle($userContent, $userIsVoice),
            ]);
        }

        $conversation->messages()->create([
            'role' => GuideMessage::ROLE_USER,
            'content' => $userContent,
            'is_voice' => $userIsVoice,
        ]);

        $conversation->messages()->create([
            'role' => GuideMessage::ROLE_ASSISTANT,
            'content' => $assistantReply,
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return $conversation;
    }

    private function makeTitle(string $content, bool $isVoice): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $content) ?? '');
        if ($title === '') {
            return $isVoice ? 'Voice message' : 'New chat';
        }

        return Str::limit($title, 60, '…');
    }
}
