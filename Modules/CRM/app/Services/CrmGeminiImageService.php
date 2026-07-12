<?php

namespace Modules\CRM\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Business\Models\Business;
use Modules\FileManager\Models\FileManagerFile;
use Modules\FileManager\Services\FileManagerService;
use RuntimeException;

class CrmGeminiImageService
{
    public function __construct(private readonly FileManagerService $fileManagerService)
    {
    }

    public function generateAndStore(
        Business $business,
        ?string $subject,
        ?string $prompt,
        ?int $uploadedByUserId,
    ): FileManagerFile {
        $apiKey = trim((string) env('GEMINI_API_KEY', config('aibot.gemini.api_key', '')));
        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $model = trim((string) config('product.gemini_image.model', ''));
        if ($model === '') {
            throw new RuntimeException('Image AI model is not configured.');
        }

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $this->composePrompt($business, $subject, $prompt)],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
        ];

        $aspectRatio = trim((string) config('product.gemini_image.aspect_ratio', ''));
        $imageSize   = trim((string) config('product.gemini_image.image_size', ''));
        $imageConfig = [];
        if ($aspectRatio !== '') {
            $imageConfig['aspectRatio'] = $aspectRatio;
        }
        if ($imageSize !== '') {
            $imageConfig['imageSize'] = $imageSize;
        }
        if ($imageConfig !== []) {
            $body['generationConfig']['imageConfig'] = $imageConfig;
        }

        $timeout  = max(30, (int) config('product.gemini_image.timeout', 120));
        $response = $this->postGenerateContent($model, $body, $apiKey, $timeout);

        if (!$response->successful()) {
            $json = $response->json();
            $msg  = is_array($json) && isset($json['error']['message'])
                ? (string) $json['error']['message']
                : ('Gemini HTTP ' . $response->status());

            throw new RuntimeException($msg);
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw new RuntimeException('Invalid Gemini response.');
        }

        $decoded = $this->extractFirstInlineImageBase64($json);
        if ($decoded === null) {
            throw new RuntimeException('The model returned no image. Try again or adjust the prompt.');
        }

        [$raw, $mime] = $decoded;
        $binary = base64_decode($raw, true);
        if ($binary === false) {
            throw new RuntimeException('Could not decode image data.');
        }

        $mimeLower = strtolower($mime);
        $ext       = str_contains($mimeLower, 'png') ? 'png' : (str_contains($mimeLower, 'jpeg') || str_contains($mimeLower, 'jpg') ? 'jpg' : 'png');

        $meta = @getimagesizefromstring($binary);
        if ($meta === false || !isset($meta[0], $meta[1])) {
            throw new RuntimeException('Generated file is not a valid image.');
        }

        $folder   = $this->fileManagerService->crmFolder($business);
        $filename = 'crm-' . Str::lower(Str::random(10)) . '.' . $ext;
        $dir      = 'business-files/' . $business->id . '/' . $folder->id;
        \Illuminate\Support\Facades\Storage::disk('public')->put($dir . '/' . $filename, $binary);

        return FileManagerFile::query()->create([
            'business_id'         => $business->id,
            'folder_id'           => $folder->id,
            'uploaded_by_user_id' => $uploadedByUserId,
            'original_filename'   => $filename,
            'stored_path'         => $dir . '/' . $filename,
            'mime_type'           => 'image/' . $ext,
            'size_bytes'          => strlen($binary),
            'notes'               => 'CRM form image (AI generated)',
        ]);
    }

    private function composePrompt(Business $business, ?string $subject, ?string $prompt): string
    {
        $biz    = trim((string) $business->name);
        $custom = trim((string) ($prompt ?? ''));
        $subj   = trim((string) ($subject ?? ''));

        $subjectLine = $subj !== '' ? "Image context: {$subj}\n" : '';
        $customBlock = $custom !== '' ? "Additional directions:\n{$custom}\n" : '';

        return <<<TXT
Generate an image for a lead-capture landing page for the business "{$biz}".

{$subjectLine}{$customBlock}
Constraints:
— Clean, professional, marketing-page suitable.
— No text overlays baked into the image.
— No photographic human faces unless explicitly requested above.
TXT;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{0: string, 1: string}|null [base64, mime]
     */
    private function extractFirstInlineImageBase64(array $json): ?array
    {
        $candidates = $json['candidates'] ?? [];
        if (!is_array($candidates) || !isset($candidates[0]) || !is_array($candidates[0])) {
            return null;
        }

        $content = $candidates[0]['content'] ?? null;
        if (!is_array($content) || !isset($content['parts']) || !is_array($content['parts'])) {
            return null;
        }

        foreach ($content['parts'] as $part) {
            if (!is_array($part)) {
                continue;
            }

            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (!is_array($inline)) {
                continue;
            }

            $data = $inline['data'] ?? null;
            if (!is_string($data) || $data === '') {
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
