<?php

declare(strict_types=1);

namespace Modules\DesignStudio\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class LetterHeadGeneratorService
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function generate(array $data): array
    {
        $apiKey = trim((string) config('aibot.gemini.api_key', ''));
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'GEMINI_API_KEY is not configured.'];
        }

        $textModel = trim((string) config('aibot.gemini.model', 'gemini-2.0-flash'));

        try {
            $response = Http::timeout(60)->acceptJson()
                ->withOptions(['http_errors' => false])
                ->withQueryParameters(['key' => $apiKey])
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(self::BASE_URL.$textModel.':generateContent', [
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $this->buildTextPrompt($data)]]],
                    ],
                ]);

            return [
                'success' => true,
                'content' => $this->parseTextResponse($response->json()),
            ];
        } catch (\Throwable $e) {
            Log::error('LetterHeadGeneratorService: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function buildTextPrompt(array $data): string
    {
        $name    = $data['name']     ?? 'Company';
        $tagline = $data['tagline']  ?? '';
        $cat     = $data['category'] ?? '';

        return <<<PROMPT
You are a professional corporate copywriter. Generate letterhead content for:
Company: {$name}
Tagline: {$tagline}
Industry: {$cat}

Return ONLY valid JSON (no markdown fences, no explanation):
{
  "tagline": "Refine the given tagline into a professional 6-10 word phrase, or create one if empty",
  "subject_example": "A realistic example letter subject line (no 'Re:' prefix), e.g. 'Service Partnership Proposal – Q3 2026'"
}

Rules:
- tagline must be concise, inspiring, and industry-appropriate
- subject_example should read like a real corporate letter
- Return ONLY the raw JSON object, nothing else
PROMPT;
    }

    private function parseTextResponse(array $json): array
    {
        try {
            $raw = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $raw = trim((string) $raw);
            $raw = preg_replace('/^```json\s*/i', '', $raw);
            $raw = preg_replace('/\s*```$/m', '', $raw);

            $parsed = json_decode($raw, true);
            if (is_array($parsed)) {
                return $parsed;
            }
        } catch (\Throwable) {
            // fall through
        }

        return [];
    }
}
