<?php

namespace Modules\AIBot\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\AIBot\Http\Requests\AIBotChatRequest;
use Modules\AIBot\Services\GeminiAgentChatService;
use Modules\Business\Models\Business;

class AIBotChatController extends Controller
{
    public function __invoke(AIBotChatRequest $request, GeminiAgentChatService $chatService): JsonResponse
    {
        if (trim((string) config('aibot.gemini.api_key', '')) === '') {
            return response()->json([
                'message' => 'Gemini API is not configured on this server. Add GEMINI_API_KEY to the environment.',
                'needs_gemini_api_key' => true,
                'reply' => null,
            ], 503);
        }

        $business = Business::currentForNavbar($request->user());
        $speakReply = $request->wantsSpokenReply();
        $result = $chatService->reply($request->user(), $business, $request->conversationMessages(), $speakReply);

        if (($result['error'] ?? null) !== null && trim((string) $result['error']) !== '') {
            return response()->json([
                'reply' => null,
                'message' => $result['error'],
            ], 422);
        }

        $payload = [
            'reply' => $result['reply'] ?? '',
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
