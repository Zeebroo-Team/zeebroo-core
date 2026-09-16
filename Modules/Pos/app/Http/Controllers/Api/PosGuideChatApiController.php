<?php

namespace Modules\Pos\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\GuideConversation;
use Modules\Pos\Services\GuideConversationService;
use Modules\Pos\Services\PosAgentChatService;
use Modules\Business\Models\Business;

class PosGuideChatApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    /**
     * Resolve the current business without aborting when none is selected —
     * the guide chat works fine (minus data/action tools) with no business context.
     */
    private function softBusiness(Request $request): ?Business
    {
        $result = $this->resolveBusinessForApi($request);

        return $result instanceof Business ? $result : null;
    }

    public function conversations(Request $request, GuideConversationService $conversations): JsonResponse
    {
        $business = $this->softBusiness($request);
        $actorKey = $conversations->actorKey($request);

        $items = $conversations->listForActor($business, $actorKey)
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title ?: 'New chat',
                'last_message_at' => optional($c->last_message_at)->toIso8601String(),
            ])
            ->values();

        return response()->json(['conversations' => $items]);
    }

    public function conversationShow(int $conversation, Request $request, GuideConversationService $conversations): JsonResponse
    {
        $actorKey = $conversations->actorKey($request);
        $model = $conversations->findOwned($actorKey, $conversation);
        if ($model === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $model = $conversations->withMessages($model);

        return response()->json([
            'id' => $model->id,
            'title' => $model->title,
            'messages' => $model->messages->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
                'is_voice' => (bool) $m->is_voice,
            ])->values(),
        ]);
    }

    public function conversationDestroy(int $conversation, Request $request, GuideConversationService $conversations): JsonResponse
    {
        $actorKey = $conversations->actorKey($request);
        $model = $conversations->findOwned($actorKey, $conversation);
        if ($model === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $conversations->destroy($model);

        return response()->json(['deleted' => true]);
    }

    private const NO_BUSINESS_REPLY = 'Please select a business first — then I can check your data and take actions for you.';

    /**
     * @return list<array{role: string, content: string}>
     */
    private function historyFor(?GuideConversation $conversation, GuideConversationService $conversations): array
    {
        if ($conversation === null) {
            return [];
        }

        $loaded = $conversations->withMessages($conversation);

        return $loaded->messages->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])->all();
    }

    /**
     * Voice endpoint — accepts a base64-encoded audio blob (WebM/Opus from the
     * Electron renderer's MediaRecorder). First transcribes the user's speech
     * via a lightweight Gemini multimodal call, then runs the same tool-calling
     * agent used by chat() so voice input gets the same advanced capabilities.
     */
    public function voice(Request $request, GuideConversationService $conversations, PosAgentChatService $agent): JsonResponse
    {
        $request->validate([
            'audio'     => 'required|string|max:6000000',   // ~4.5 MB decoded
            'mime_type' => 'nullable|string|max:100',
            'conversation_id' => 'nullable|integer|min:1',
        ]);

        $actorKey = $conversations->actorKey($request);
        $conversation = null;
        $conversationId = $request->integer('conversation_id') ?: null;
        if ($conversationId) {
            $conversation = $conversations->findOwned($actorKey, $conversationId);
            if ($conversation === null) {
                return response()->json(['message' => 'Conversation not found.'], 404);
            }
        }

        $apiKey = config('services.gemini.key');
        if (!$apiKey) {
            return response()->json(['transcript' => null, 'reply' => null], 503);
        }

        // Strip codec parameters from MIME type — Gemini only needs the base type
        $rawMime    = $request->input('mime_type', 'audio/webm');
        $geminiMime = trim(explode(';', $rawMime)[0]);   // e.g. "audio/webm"

        $transcribePrompt = <<<'TXT'
Transcribe the attached voice message exactly as spoken. Detect the language spoken.
Return ONLY valid JSON — no markdown, no code fences — with these fields:
{"transcript":"exact words spoken (in original language)","lang":"BCP-47 language code e.g. si-LK or en-US or ta-LK"}
TXT;

        $models = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash'];

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $transcribePrompt]]],
            'contents'          => [[
                'role'  => 'user',
                'parts' => [[
                    'inline_data' => [
                        'mime_type' => $geminiMime,
                        'data'      => $request->input('audio'),
                    ],
                ]],
            ]],
            'generationConfig'  => ['maxOutputTokens' => 400, 'temperature' => 0.1],
        ];

        $transcript = null;
        $lang = '';
        foreach ($models as $model) {
            $response = Http::timeout(30)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                $payload
            );
            if ($response->status() === 429) continue;
            if (!$response->successful()) break;

            $raw = $response->json('candidates.0.content.parts.0.text');
            if (!$raw) continue;

            $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($raw));
            $parsed  = json_decode($cleaned, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($parsed['transcript'])) {
                $transcript = trim((string) $parsed['transcript']);
                $lang = trim((string) ($parsed['lang'] ?? ''));
                break;
            }
        }

        if (!$transcript) {
            return response()->json(['transcript' => null, 'reply' => null], 503);
        }

        $business = $this->softBusiness($request);
        if ($business === null) {
            $conversation = $conversations->recordTurn(null, $actorKey, $conversation, $transcript, true, self::NO_BUSINESS_REPLY);

            return response()->json([
                'transcript' => $transcript,
                'reply' => self::NO_BUSINESS_REPLY,
                'lang' => $lang,
                'conversation_id' => $conversation->id,
            ]);
        }

        $history = $this->historyFor($conversation, $conversations);
        $history[] = ['role' => 'user', 'content' => $transcript];

        $result = $agent->reply($business, $actorKey, $history);
        if (isset($result['error']) || trim((string) ($result['reply'] ?? '')) === '') {
            return response()->json(['transcript' => $transcript, 'reply' => null], 503);
        }

        $reply = trim($result['reply']);
        $conversation = $conversations->recordTurn($business, $actorKey, $conversation, $transcript, true, $reply);

        return response()->json([
            'transcript'      => $transcript,
            'reply'           => $reply,
            'walkthrough'     => $result['walkthroughId'] ?? null,
            'productName'     => $result['productName'] ?? null,
            'fieldName'       => $result['fieldName'] ?? null,
            'posterCommands'  => $result['posterCommands'] ?? null,
            'lang'            => $lang,
            'conversation_id' => $conversation?->id,
        ]);
    }

    public function chat(Request $request, GuideConversationService $conversations, PosAgentChatService $agent): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'conversation_id' => 'nullable|integer|min:1',
        ]);

        $actorKey = $conversations->actorKey($request);
        $conversation = null;
        $conversationId = $request->integer('conversation_id') ?: null;
        if ($conversationId) {
            $conversation = $conversations->findOwned($actorKey, $conversationId);
            if ($conversation === null) {
                return response()->json(['message' => 'Conversation not found.'], 404);
            }
        }

        $userMessage = trim((string) $request->input('message'));
        $business = $this->softBusiness($request);

        if ($business === null) {
            $conversation = $conversations->recordTurn(null, $actorKey, $conversation, $userMessage, false, self::NO_BUSINESS_REPLY);

            return response()->json([
                'reply' => self::NO_BUSINESS_REPLY,
                'walkthrough' => null,
                'conversation_id' => $conversation->id,
            ]);
        }

        if (!config('services.gemini.key')) {
            return response()->json(['reply' => null, 'walkthrough' => null], 503);
        }

        $history = $this->historyFor($conversation, $conversations);
        $history[] = ['role' => 'user', 'content' => $userMessage];

        $result = $agent->reply($business, $actorKey, $history);

        if (isset($result['error']) || trim((string) ($result['reply'] ?? '')) === '') {
            return response()->json(['reply' => null, 'walkthrough' => null], 503);
        }

        $reply = trim($result['reply']);
        $conversation = $conversations->recordTurn($business, $actorKey, $conversation, $userMessage, false, $reply);

        return response()->json([
            'reply'           => $reply,
            'walkthrough'     => $result['walkthroughId'] ?? null,
            'isHtml'          => (bool) ($result['isHtml'] ?? false),
            'productName'     => $result['productName'] ?? null,
            'fieldName'       => $result['fieldName'] ?? null,
            'posterCommands'  => $result['posterCommands'] ?? null,
            'conversation_id' => $conversation->id,
        ]);
    }
}
