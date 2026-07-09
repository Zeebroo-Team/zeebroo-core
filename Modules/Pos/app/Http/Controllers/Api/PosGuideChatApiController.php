<?php

namespace Modules\Pos\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

class PosGuideChatApiController extends Controller
{
    private const SYSTEM_PROMPT = 'You are a helpful assistant embedded in Zeebroo POS, a business management desktop application. '
        . 'You help users understand how to use the system and answer business-related questions. '
        . 'Keep answers concise — 2 to 4 sentences. '
        . 'Topics: Point of Sale, Inventory, Sales history, Finance, HR, Services, Design Studio, Restaurant management, and general business operations. '
        . 'If asked something completely unrelated to business or the POS system, politely redirect the user back to POS-related topics.';

    public function chat(Request $request): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:500']);

        $apiKey = config('services.gemini.key');

        if (!$apiKey) {
            return response()->json(['reply' => 'The AI assistant is not configured. Please contact your administrator.']);
        }

        $response = Http::timeout(20)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}",
            [
                'systemInstruction' => [
                    'parts' => [['text' => self::SYSTEM_PROMPT]],
                ],
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $request->input('message')]]],
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 220,
                    'temperature'     => 0.65,
                ],
            ]
        );

        if (! $response->successful()) {
            return response()->json(['reply' => 'Sorry, I could not get a response right now. Please try again.']);
        }

        $reply = $response->json('candidates.0.content.parts.0.text')
            ?? 'Sorry, I did not understand the response. Please try again.';

        return response()->json(['reply' => trim($reply)]);
    }
}
