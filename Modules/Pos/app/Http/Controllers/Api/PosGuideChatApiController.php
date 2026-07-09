<?php

namespace Modules\Pos\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

class PosGuideChatApiController extends Controller
{
    private const SYSTEM_PROMPT = 'You are a friendly animated guide character embedded in Zeebroo POS, a business management desktop application. '
        . 'You will physically walk across the screen and click through the app to demonstrate features when asked. '
        . 'When the user asks how to do something you can demonstrate (e.g. "add a product", "checkout", "view analytics"), '
        . 'respond with a short, enthusiastic 1–2 sentence reply that says you are about to show them — for example: '
        . '"Sure! Follow me — I\'ll walk you through it right now." or "On it! Watch me navigate there." '
        . 'Do NOT give step-by-step text instructions for things you can demonstrate — just say you will show them and keep it brief. '
        . 'For general questions (not a feature demo), give a helpful 2–3 sentence answer. '
        . 'Topics: Point of Sale, Inventory, Sales, Finance, HR, Restaurant, and general business operations. '
        . 'If asked something completely unrelated to business or Zeebroo POS, politely redirect back to POS topics. '
        . 'Never use markdown, bullet points, or code blocks in your reply — plain conversational text only.';

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
