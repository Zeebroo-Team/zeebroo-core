<?php

namespace Modules\AIBot\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Modules\AIBot\Models\Conversation;
use Modules\AIBot\Models\Message;
use Modules\Business\Models\Business;

class ConversationService
{
    /**
     * @return Collection<int, Conversation>
     */
    public function listForUser(User $user, ?Business $business): Collection
    {
        return Conversation::query()
            ->where('user_id', $user->id)
            ->when($business, fn ($q) => $q->where('business_id', $business->id))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'last_message_at']);
    }

    public function findOwned(User $user, int $conversationId): ?Conversation
    {
        return Conversation::query()
            ->where('user_id', $user->id)
            ->find($conversationId);
    }

    public function withMessages(Conversation $conversation): Conversation
    {
        return $conversation->load(['messages' => fn ($q) => $q->orderBy('id')]);
    }

    public function recordTurn(
        User $user,
        ?Business $business,
        ?Conversation $conversation,
        string $userContent,
        bool $userIsVoice,
        string $assistantReply,
    ): Conversation {
        if ($conversation === null) {
            $conversation = Conversation::create([
                'user_id' => $user->id,
                'business_id' => $business?->id,
                'title' => $this->makeTitle($userContent, $userIsVoice),
            ]);
        }

        $conversation->messages()->create([
            'role' => Message::ROLE_USER,
            'content' => $userContent,
            'is_voice' => $userIsVoice,
        ]);

        $conversation->messages()->create([
            'role' => Message::ROLE_ASSISTANT,
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
