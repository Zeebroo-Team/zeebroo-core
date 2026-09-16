<?php

namespace Modules\Pos\Services;

use Illuminate\Support\Facades\Http;

/**
 * Stateless Gemini generateContent client for the POS module, sharing the
 * same config keys (services.gemini.key/model) and model-fallback list
 * PosGuideChatApiController already used before this class existed.
 */
class PosGeminiClient
{
    private const MODELS = [
        'gemini-2.5-flash',
        'gemini-2.0-flash',
        'gemini-1.5-flash',
    ];

    /**
     * @param  array<string, mixed>  $body
     * @return array{successful: bool, status: int, json: ?array<string, mixed>}
     */
    public function generate(array $body): array
    {
        $apiKey = (string) config('services.gemini.key', '');
        if ($apiKey === '') {
            return ['successful' => false, 'status' => 0, 'json' => null];
        }

        $envModel = config('services.gemini.model');
        $models = $envModel
            ? array_values(array_unique(array_merge([$envModel], self::MODELS)))
            : self::MODELS;

        $last = ['successful' => false, 'status' => 429, 'json' => null];

        foreach ($models as $model) {
            $response = Http::timeout(30)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                $body
            );

            if ($response->status() === 429) {
                $last = ['successful' => false, 'status' => 429, 'json' => $response->json()];

                continue;
            }

            if (! $response->successful()) {
                return ['successful' => false, 'status' => $response->status(), 'json' => $response->json()];
            }

            return ['successful' => true, 'status' => 200, 'json' => $response->json()];
        }

        return $last;
    }
}
