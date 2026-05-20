<?php

declare(strict_types=1);

namespace Modules\Business\Services;

use Modules\Business\Models\Business;
use Modules\Business\Support\BrandCompanyCategoryCatalog;
use Modules\AIBot\Services\GeminiGenerateContentClient;
use RuntimeException;

final class BusinessBrandCopyGeneratorService
{
    public function __construct(private readonly GeminiGenerateContentClient $client) {}

    /**
     * @return array{short_description?: string, description?: string, error?: string}
     */
    public function generate(
        Business $business,
        string $kind,
        ?string $userHint,
        ?string $existingShort,
        ?string $existingLong,
        string $companyCategorySlug
    ): array {
        $kind = strtolower(trim($kind));
        if (! in_array($kind, ['short', 'full', 'both'], true)) {
            return ['error' => 'Invalid generation kind.'];
        }

        try {
            $body = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $this->buildPrompt(
                            business: $business,
                            kind: $kind,
                            hint: $userHint ?? '',
                            existingShort: $existingShort ?? '',
                            existingLong: $existingLong ?? '',
                            slug: $companyCategorySlug
                        )]],
                    ],
                ],
            ];

            $response = $this->client->generate($body);
            $json = $response->json();

            if (! $response->successful()) {
                $msg = is_array($json) && isset($json['error']['message'])
                    ? (string) $json['error']['message']
                    : ('Gemini HTTP '.$response->status());

                return ['error' => $msg];
            }

            if (! is_array($json)) {
                return ['error' => 'Invalid Gemini response.'];
            }

            if ($this->blockedBySafetyFilters($json)) {
                return ['error' => 'This request was rejected by Gemini safety filters. Rephrase hints and try again.'];
            }

            $text = $this->extractModelText($json);
            $text = trim($text ?? '');
            if ($text === '') {
                return ['error' => 'The model returned an empty reply.'];
            }

            $parsed = json_decode($this->sanitizeJsonEnvelope($text), true);
            if (! is_array($parsed)) {
                return ['error' => 'Could not parse AI reply as JSON. Try again or shorten your hint.'];
            }

            $out = [];

            if ($kind === 'short' || $kind === 'both') {
                $s = isset($parsed['short_description'])
                    ? $this->clip((string) $parsed['short_description'], 360)
                    : '';
                if ($s !== '') {
                    $out['short_description'] = $s;
                } elseif ($kind === 'short') {
                    return ['error' => 'Missing short_description in AI response.'];
                }
            }

            if ($kind === 'full' || $kind === 'both') {
                $l = isset($parsed['description'])
                    ? $this->clip((string) $parsed['description'], 6000)
                    : '';
                if ($l !== '') {
                    $out['description'] = $l;
                } elseif ($kind === 'full') {
                    return ['error' => 'Missing description in AI response.'];
                }
            }

            if ($out === []) {
                return ['error' => 'No usable copy was generated.'];
            }

            return $out;
        } catch (RuntimeException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function buildPrompt(
        Business $business,
        string $kind,
        string $hint,
        string $existingShort,
        string $existingLong,
        string $slug
    ): string {
        $label = BrandCompanyCategoryCatalog::labelsByValue()[$slug] ?? $slug;

        $which = match ($kind) {
            'short' => 'Return JSON with key "short_description" only.',
            'full' => 'Return JSON with key "description" only.',
            default => 'Return JSON with both keys "short_description" and "description".',
        };

        $nameEsc = self::squishOneLine((string) $business->name, 280);
        $lblEsc = self::squishOneLine($label, 120);
        $shortEsc = self::squishOneLine($existingShort, 2000);
        $longEsc = self::squishOneLine($existingLong, 2000);
        $hintEsc = self::squishOneLine($hint, 1200);

        return implode("\n", [
            'Write professional business profile marketing copy for a company directory.',
            '',
            'Facts:',
            '- Business name: '.$nameEsc,
            '- Company category/industry (authoritative slug: '.$slug.'): '.$lblEsc,
            '- Existing short description (may be empty; you may revise or extend): '.$shortEsc,
            '- Existing full description draft (may be empty): '.$longEsc,
            '- Extra instructions from the user (may be empty): '.$hintEsc,
            '',
            'Requirements:',
            $which,
            '',
            'short_description must be ONE or TWO concise sentences suitable as a hero tagline, maximum 360 characters.',
            'description must be fuller copy (tone: professional, clear), paragraphs allowed, maximum 6000 characters.',
            'Never invent factual claims unsupported by hints (employees, certifications, awards). Prefer general truthful positioning unless the hints give specifics.',
            '',
            'Output rules:',
            'Respond with ONLY a single JSON object, no markdown code fences, no commentary.',
            'Keys must match exactly short_description and/or description as required above. Use UTF-8 plain strings.',
        ]);
    }

    /** Single-line excerpt for prompting (no newline injection). */
    private static function squishOneLine(string $text, int $maxChars): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return mb_substr($text, 0, max(8, $maxChars));
    }

    private function sanitizeJsonEnvelope(string $text): string
    {
        $t = trim($text);
        if (str_starts_with($t, '```')) {
            $t = preg_replace('/^```(?:json)?\s*/i', '', $t);
            $t = preg_replace('/\s*```$/', '', $t);
        }

        return trim((string) $t);
    }

    private function clip(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return trim($text);
        }

        return trim(mb_substr($text, 0, max(1, $max - 3))).'…';
    }

    /**
     * @param  array<string,mixed>  $json
     */
    private function blockedBySafetyFilters(array $json): bool
    {
        $candidate = (($json['candidates'] ?? [])[0] ?? null);
        if (! is_array($candidate)) {
            return false;
        }
        $finish = strtoupper((string) ($candidate['finishReason'] ?? ''));

        return in_array($finish, ['SAFETY', 'BLOCKLIST', 'PROHIBITED_CONTENT'], true);
    }

    /**
     * @param  array<string,mixed>  $json
     */
    private function extractModelText(array $json): ?string
    {
        $candidate = (($json['candidates'] ?? [])[0] ?? null);
        if (! is_array($candidate)) {
            return null;
        }

        $modelContent = $candidate['content'] ?? null;
        if (! is_array($modelContent) || ! isset($modelContent['parts'])) {
            return null;
        }

        $chunks = [];
        foreach ($modelContent['parts'] as $part) {
            if (is_array($part) && ! empty($part['text'])) {
                $chunks[] = (string) $part['text'];
            }
        }

        return $chunks !== [] ? implode('', $chunks) : null;
    }
}
