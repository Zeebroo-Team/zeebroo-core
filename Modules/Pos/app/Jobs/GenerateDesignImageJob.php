<?php

namespace Modules\Pos\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateDesignImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Max attempts before marking failed */
    public int $tries = 2;

    /** Seconds before the job is considered timed out */
    public int $timeout = 120;

    /** Gemini models that support native image output, in priority order */
    private const IMAGE_MODELS = [
        'gemini-3.1-flash-image',        // Nano Banana 2 — best quality/speed
        'gemini-3.1-flash-lite-image',   // lighter fallback
        'gemini-2.5-flash-image',        // older fallback
    ];

    /** How long to keep cache entries (status + file reference) */
    private const CACHE_TTL_HOURS = 2;

    /** Temp disk path prefix for generated image files */
    private const IMG_DIR = 'dsai_images';

    public function __construct(
        private readonly string $jobId,
        private readonly string $prompt,
        private readonly string $aspect,   // 'landscape' | 'portrait' | 'square'
        private readonly string $apiKey,
    ) {}

    // ── Cache key helpers ──────────────────────────────────────────────────────

    public static function cacheKey(string $jobId): string
    {
        return 'dsai_job_' . $jobId;
    }

    // ── Main job logic ─────────────────────────────────────────────────────────

    public function handle(): void
    {
        $cacheKey = self::cacheKey($this->jobId);

        // Mark processing so the poller can show a meaningful status
        Cache::put($cacheKey, [
            'status' => 'processing',
            'path'   => null,
            'error'  => null,
        ], now()->addHours(self::CACHE_TTL_HOURS));

        $aspectMap   = ['landscape' => '16:9', 'portrait' => '9:16', 'square' => '1:1'];
        $aspectRatio = $aspectMap[$this->aspect] ?? '1:1';

        $payload = [
            'contents' => [[
                'role'  => 'user',
                'parts' => [['text' => $this->prompt]],
            ]],
            'generationConfig' => [
                'responseModalities' => ['IMAGE'],
                'imageConfig'        => ['aspectRatio' => $aspectRatio],
            ],
        ];

        foreach (self::IMAGE_MODELS as $model) {
            $url = sprintf(
                'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
                $model,
                $this->apiKey,
            );

            try {
                $response = Http::timeout(90)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($url, $payload);

                // Back off once on rate-limit
                if ($response->status() === 429) {
                    sleep(4);
                    $response = Http::timeout(90)
                        ->withHeaders(['Content-Type' => 'application/json'])
                        ->post($url, $payload);
                }

                if (!$response->successful()) {
                    Log::warning('GenerateDesignImageJob: non-2xx', [
                        'jobId'  => $this->jobId,
                        'model'  => $model,
                        'status' => $response->status(),
                        'body'   => substr($response->body(), 0, 300),
                    ]);
                    continue; // try next model
                }

                $json = $response->json();

                foreach (($json['candidates'][0]['content']['parts'] ?? []) as $part) {
                    $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
                    if (!is_array($inline)) {
                        continue;
                    }

                    $b64Data = $inline['data'] ?? null;
                    if (!is_string($b64Data) || $b64Data === '') {
                        continue;
                    }

                    $mime = (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png');
                    $ext  = str_contains(strtolower($mime), 'jpeg') ? 'jpg' : 'png';

                    // Save binary to temp storage so cache only holds a small path reference
                    $relPath = self::IMG_DIR . '/' . $this->jobId . '.' . $ext;
                    Storage::disk('local')->put($relPath, base64_decode($b64Data, true));

                    Cache::put($cacheKey, [
                        'status' => 'completed',
                        'path'   => $relPath,
                        'mime'   => $mime,
                        'error'  => null,
                    ], now()->addHours(self::CACHE_TTL_HOURS));

                    Log::info('GenerateDesignImageJob: completed', [
                        'jobId' => $this->jobId,
                        'model' => $model,
                        'mime'  => $mime,
                    ]);

                    return; // success — stop trying models
                }

                Log::warning('GenerateDesignImageJob: no inline image in response', [
                    'jobId' => $this->jobId,
                    'model' => $model,
                ]);

            } catch (\Exception $e) {
                Log::error('GenerateDesignImageJob: exception', [
                    'jobId'   => $this->jobId,
                    'model'   => $model,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // All models exhausted with no image
        Cache::put($cacheKey, [
            'status' => 'failed',
            'path'   => null,
            'error'  => 'Image generation failed. Please try again.',
        ], now()->addHours(self::CACHE_TTL_HOURS));
    }

    /** Called by Laravel when all retries are exhausted */
    public function failed(\Throwable $e): void
    {
        Cache::put(self::cacheKey($this->jobId), [
            'status' => 'failed',
            'path'   => null,
            'error'  => $e->getMessage(),
        ], now()->addHours(self::CACHE_TTL_HOURS));

        Log::error('GenerateDesignImageJob: all retries failed', [
            'jobId'   => $this->jobId,
            'message' => $e->getMessage(),
        ]);
    }
}
