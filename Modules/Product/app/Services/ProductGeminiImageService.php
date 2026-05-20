<?php

namespace Modules\Product\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Business\Models\Business;
use Modules\Business\Services\BusinessLogoGeminiImageService;
use Modules\FileManager\Models\FileManagerFile;
use Modules\FileManager\Services\FileManagerService;
use RuntimeException;

class ProductGeminiImageService
{
    public function __construct(private readonly FileManagerService $fileManagerService)
    {
    }

    public function generateAndStore(
        Business $business,
        ?string $productName,
        ?string $prompt,
        ?int $uploadedByUserId,
    ): FileManagerFile {
        $apiKey = trim((string) env('GEMINI_API_KEY', config('aibot.gemini.api_key', '')));
        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $model = trim((string) config('product.gemini_image.model', ''));
        if ($model === '') {
            throw new RuntimeException('Product image AI model is not configured.');
        }

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $this->composePrompt($business, $productName, $prompt)],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
        ];

        $imageConfig = [];
        $aspectRatio = trim((string) config('product.gemini_image.aspect_ratio', ''));
        $imageSize = trim((string) config('product.gemini_image.image_size', ''));
        if ($aspectRatio !== '') {
            $imageConfig['aspectRatio'] = $aspectRatio;
        }
        if ($imageSize !== '') {
            $imageConfig['imageSize'] = $imageSize;
        }
        if ($imageConfig !== []) {
            $body['generationConfig']['imageConfig'] = $imageConfig;
        }

        $timeout = max(30, (int) config('product.gemini_image.timeout', 120));
        $response = $this->postGenerateContent($model, $body, $apiKey, $timeout);

        if (!$response->successful()) {
            $json = $response->json();
            $msg = is_array($json) && isset($json['error']['message'])
                ? (string) $json['error']['message']
                : ('Gemini HTTP '.$response->status());

            throw new RuntimeException($msg);
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw new RuntimeException('Invalid Gemini response.');
        }

        $decoded = BusinessLogoGeminiImageService::extractFirstInlineImageBase64($json);
        if ($decoded === null) {
            throw new RuntimeException('The model returned no image. Try a different description.');
        }

        [$raw, $mime] = $decoded;
        $binary = base64_decode($raw, true);
        if ($binary === false) {
            throw new RuntimeException('Could not decode image data.');
        }

        $mimeLower = strtolower($mime);
        $ext = str_contains($mimeLower, 'png')
            ? 'png'
            : (str_contains($mimeLower, 'jpeg') || str_contains($mimeLower, 'jpg') ? 'jpg' : 'png');

        $meta = @getimagesizefromstring($binary);
        if ($meta === false || !isset($meta[0], $meta[1])) {
            throw new RuntimeException('Generated file is not a valid image.');
        }

        [$w, $h] = $meta;
        if ($w > 2048 || $h > 2048 || $w < 16 || $h < 16) {
            throw new RuntimeException('Generated dimensions are outside allowed bounds.');
        }

        $label = trim((string) $productName);
        $filename = 'ai-product-'.Str::lower(Str::random(8)).'.'.$ext;
        $originalName = $label !== ''
            ? Str::limit($label.' (AI).'.$ext, 255, '')
            : 'product-ai-'.Str::lower(Str::random(6)).'.'.$ext;

        $folder = $this->fileManagerService->productsFolder($business);

        return $this->fileManagerService->storeBinary(
            $business,
            $binary,
            $mime,
            $originalName,
            $filename,
            $folder->id,
            $uploadedByUserId,
            'AI-generated product image',
        );
    }

    private function composePrompt(Business $business, ?string $productName, ?string $prompt): string
    {
        $biz = trim((string) $business->name);
        $name = trim((string) $productName);
        $extra = trim((string) $prompt);

        $nameLine = $name !== '' ? "Product: {$name}\n" : '';
        $extraBlock = $extra !== '' ? "Additional directions:\n{$extra}\n" : '';

        return <<<TXT
Generate a professional product photo for an inventory catalog.

Business: {$biz}
{$nameLine}{$extraBlock}
Constraints:
— Single product on a clean, neutral background (e-commerce style).
— No text, watermarks, or logos in the image.
— Photorealistic or clean illustration suitable for a product listing thumbnail.
— No human faces or sensitive content.
TXT;
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
