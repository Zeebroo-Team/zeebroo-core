<?php

namespace Modules\AIBot\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\AIBot\Http\Requests\AIBotChatRequest;
use Modules\AIBot\Services\ConversationService;
use Modules\AIBot\Services\GeminiAgentChatService;
use Modules\Business\Models\Business;

class AIBotChatController extends Controller
{
    public function __invoke(
        AIBotChatRequest $request,
        GeminiAgentChatService $chatService,
        ConversationService $conversations,
    ): JsonResponse {
        if (trim((string) config('aibot.gemini.api_key', '')) === '') {
            return response()->json([
                'message' => 'Gemini API is not configured on this server. Add GEMINI_API_KEY to the environment.',
                'needs_gemini_api_key' => true,
                'reply' => null,
            ], 503);
        }

        $conversation = null;
        $conversationId = $request->conversationId();
        if ($conversationId !== null) {
            $conversation = $conversations->findOwned($request->user(), $conversationId);
            if ($conversation === null) {
                return response()->json([
                    'reply' => null,
                    'message' => 'Conversation not found.',
                ], 404);
            }
        }

        $business = Business::currentForNavbar($request->user());
        $speakReply = $request->wantsSpokenReply();
        $messages = $request->conversationMessages();
        $result = $chatService->reply($request->user(), $business, $messages, $speakReply);

        if (($result['error'] ?? null) !== null && trim((string) $result['error']) !== '') {
            return response()->json([
                'reply' => null,
                'message' => $result['error'],
            ], 422);
        }

        $lastUserMessage = end($messages) ?: [];
        $conversation = $conversations->recordTurn(
            $request->user(),
            $business,
            $conversation,
            (string) ($lastUserMessage['content'] ?? ''),
            isset($lastUserMessage['audio']),
            (string) ($result['reply'] ?? ''),
        );

        $payload = [
            'reply' => $result['reply'] ?? '',
            'conversation_id' => $conversation->id,
            'conversation_title' => $conversation->title,
        ];

        if (isset($result['reply_audio']) && is_array($result['reply_audio'])) {
            $payload['reply_audio'] = [
                'mime' => $result['reply_audio']['mime'] ?? 'audio/wav',
                'data' => $result['reply_audio']['data'] ?? '',
            ];
        }

        return response()->json($payload);
    }
}
