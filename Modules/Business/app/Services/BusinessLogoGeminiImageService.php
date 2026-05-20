<?php

namespace Modules\Business\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessLogoGeneration;
use Modules\Business\Support\LogoGenerationCatalog;
use RuntimeException;

class BusinessLogoGeminiImageService
{
    /** @throws RuntimeException When API key missing or HTTP failure */
    public function generateAndSave(BusinessLogoGeneration $generation): string
    {
        $business = $generation->business;
        if (! $business instanceof Business) {
            throw new RuntimeException('Business not found for generation.');
        }

        $apiKey = trim((string) env('GEMINI_API_KEY', config('aibot.gemini.api_key', '')));
        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $model = trim((string) config('business.logo_ai.model'));
        if ($model === '') {
            throw new RuntimeException('Logo AI model slug is empty.');
        }

        $prompt = self::composePrompt($business, $generation);

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
        ];

        $imageConfig = [];
        $ar = trim((string) config('business.logo_ai.aspect_ratio', ''));
        $isz = trim((string) config('business.logo_ai.image_size', ''));
        if ($ar !== '') {
            $imageConfig['aspectRatio'] = $ar;
        }
        if ($isz !== '') {
            $imageConfig['imageSize'] = $isz;
        }
        if ($imageConfig !== []) {
            $body['generationConfig']['imageConfig'] = $imageConfig;
        }

        $timeout = max(30, (int) config('business.logo_ai.timeout', 120));

        $response = $this->postGenerateContent($model, $body, $apiKey, $timeout);

        if (! $response->successful()) {
            $json = $response->json();
            $msg = is_array($json) && isset($json['error']['message'])
                ? (string) $json['error']['message']
                : ('Gemini HTTP '.$response->status());

            throw new RuntimeException($msg);
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Invalid Gemini response.');
        }

        $decoded = self::extractFirstInlineImageBase64($json);
        if ($decoded === null) {
            throw new RuntimeException('The model returned no image. Try again or adjust the prompt.');
        }

        [$raw, $mime] = $decoded;
        $binary = base64_decode($raw, true);
        if ($binary === false) {
            throw new RuntimeException('Could not decode image data.');
        }

        $mimeLower = strtolower($mime);
        $ext = str_contains($mimeLower, 'png') ? 'png' : (str_contains($mimeLower, 'jpeg') || str_contains($mimeLower, 'jpg') ? 'jpg' : 'png');

        $meta = @getimagesizefromstring($binary);
        if ($meta === false || ! isset($meta[0], $meta[1])) {
            throw new RuntimeException('Generated file is not a valid image.');
        }

        [$w, $h] = $meta;
        if ($w > 2048 || $h > 2048 || $w < 16 || $h < 16) {
            throw new RuntimeException('Generated dimensions are outside allowed bounds.');
        }

        $relative = 'business-logos/'.$business->getKey().'/ai/'.$generation->uuid.'.'.$ext;
        Storage::disk('public')->put($relative, $binary);

        return $relative;
    }

    public static function composePrompt(Business $business, BusinessLogoGeneration $generation): string
    {
        $cats = LogoGenerationCatalog::categoryLabelsByValue();
        $styles = LogoGenerationCatalog::styleLabelsByValue();
        $catLabel = $cats[$generation->company_category] ?? $generation->company_category;
        $styleLabel = $styles[$generation->logo_style] ?? $generation->logo_style;

        $bg = $generation->background_theme === 'dark'
            ? 'Dark UI theme preference: emphasize light-on-dark, deep neutral or rich background-compatible tones, readable on dark dashboards.'
            : 'Light UI theme preference: emphasize dark-on-light, clean light or white-friendly background treatment, readable on light dashboards.';

        $biz = trim((string) $business->name);

        $custom = trim((string) ($generation->custom_prompt ?? ''));
        $customBlock = $custom !== ''
            ? "Additional directions from the business owner:\n{$custom}\n"
            : '';

        return <<<TXT
Generate a square company logo image for the brand "{$biz}".

Industry / category: {$catLabel}
Logo style direction: {$styleLabel}
Visual background treatment: {$bg}

{$customBlock}
Constraints:
— One clear emblem or mark usable as profile / app logo (readable at small sizes).
— No photographic human faces or realistic IDs.
— Keep composition simple enough to work as avatar and navbar logo.
TXT;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{0: string, 1: string}|null [base64, mime]
     */
    public static function extractFirstInlineImageBase64(array $json): ?array
    {
        $candidates = $json['candidates'] ?? [];
        if (! is_array($candidates) || ! isset($candidates[0]) || ! is_array($candidates[0])) {
            return null;
        }

        $content = $candidates[0]['content'] ?? null;
        if (! is_array($content) || ! isset($content['parts']) || ! is_array($content['parts'])) {
            return null;
        }

        foreach ($content['parts'] as $part) {
            if (! is_array($part)) {
                continue;
            }

            // REST JSON uses camelCase (inlineData)
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (! is_array($inline)) {
                continue;
            }

            $data = $inline['data'] ?? null;
            if (! is_string($data) || $data === '') {
                continue;
            }

            $mime = isset($inline['mimeType'])
                ? (string) $inline['mimeType']
                : ((isset($inline['mime_type'])) ? (string) $inline['mime_type'] : 'image/png');

            return [$data, $mime];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postGenerateContent(string $model, array $body, string $apiKey, int $timeout): Response
    {
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            $model,
        );

        return Http::timeout($timeout)
            ->retry(1, 1000)
            ->acceptJson()
            ->withOptions(['http_errors' => false])
            ->withQueryParameters(['key' => $apiKey])
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $body);
    }
}
