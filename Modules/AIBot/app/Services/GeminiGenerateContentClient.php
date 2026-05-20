<?php

namespace Modules\AIBot\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiGenerateContentClient
{
    public function generate(array $body): Response
    {
        $model = trim((string) config('aibot.gemini.model', 'gemini-2.0-flash'));

        return $this->postGenerateContent($model, $body);
    }

    public function generateForModel(string $model, array $body): Response
    {
        $model = trim($model);
        if ($model === '') {
            throw new RuntimeException('Gemini model slug is empty.');
        }

        return $this->postGenerateContent($model, $body);
    }

    private function postGenerateContent(string $model, array $body): Response
    {
        $apiKey = (string) config('aibot.gemini.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $timeout = (int) config('aibot.gemini.timeout', 60);
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            $model
        );

        return Http::timeout($timeout)
            ->retry(2, 500, null, false)
            ->acceptJson()
            ->withQueryParameters(['key' => $apiKey])
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post($url, $body);
    }
}
