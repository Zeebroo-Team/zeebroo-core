<?php

declare(strict_types=1);

namespace Modules\DesignStudio\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

final class CompanyProfileGeneratorService
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * @param  array<string, mixed>  $data  Wizard form data
     * @return array{success: bool, content?: array<string,mixed>, coverImage?: string|null, interiorImage?: string|null, servicesImage?: string|null, error?: string}
     */
    public function generate(array $data): array
    {
        $apiKey = trim((string) config('aibot.gemini.api_key', ''));
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'GEMINI_API_KEY is not configured.'];
        }

        $textModel  = trim((string) config('aibot.gemini.model', 'gemini-2.0-flash'));
        $imageModel = trim((string) config('business.logo_ai.model', 'gemini-2.5-flash-image'));

        $imgCfg = [
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
                'imageConfig'        => ['aspectRatio' => '16:9'],
            ],
        ];

        try {
            $responses = Http::pool(fn (Pool $pool) => [
                /* Text content */
                $pool->as('text')
                    ->timeout(60)->acceptJson()
                    ->withOptions(['http_errors' => false])
                    ->withQueryParameters(['key' => $apiKey])
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post(self::BASE_URL.$textModel.':generateContent', [
                        'contents' => [
                            ['role' => 'user', 'parts' => [['text' => $this->buildTextPrompt($data)]]],
                        ],
                    ]),

                /* Cover image — dramatic dark abstract background */
                $pool->as('cover')
                    ->timeout(120)->acceptJson()
                    ->withOptions(['http_errors' => false])
                    ->withQueryParameters(['key' => $apiKey])
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post(self::BASE_URL.$imageModel.':generateContent', array_merge([
                        'contents' => [
                            ['role' => 'user', 'parts' => [['text' => $this->buildCoverImagePrompt($data)]]],
                        ],
                    ], $imgCfg)),

                /* Interior image — professional office / workspace atmosphere */
                $pool->as('interior')
                    ->timeout(120)->acceptJson()
                    ->withOptions(['http_errors' => false])
                    ->withQueryParameters(['key' => $apiKey])
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post(self::BASE_URL.$imageModel.':generateContent', array_merge([
                        'contents' => [
                            ['role' => 'user', 'parts' => [['text' => $this->buildInteriorImagePrompt($data)]]],
                        ],
                    ], $imgCfg)),

                /* Services image — tech / innovation abstract background */
                $pool->as('services')
                    ->timeout(120)->acceptJson()
                    ->withOptions(['http_errors' => false])
                    ->withQueryParameters(['key' => $apiKey])
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post(self::BASE_URL.$imageModel.':generateContent', array_merge([
                        'contents' => [
                            ['role' => 'user', 'parts' => [['text' => $this->buildServicesImagePrompt($data)]]],
                        ],
                    ], $imgCfg)),
            ]);

            return [
                'success'        => true,
                'content'        => $this->parseTextResponse($responses['text']->json()),
                'coverImage'     => $this->parseImageResponse($responses['cover']->json()),
                'interiorImage'  => $this->parseImageResponse($responses['interior']->json()),
                'servicesImage'  => $this->parseImageResponse($responses['services']->json()),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** @param array<string,mixed> $d */
    private function buildTextPrompt(array $d): string
    {
        $name     = trim((string) ($d['name']     ?? 'Company'));
        $category = trim((string) ($d['category'] ?? ''));
        $tagline  = trim((string) ($d['tagline']  ?? ''));
        $desc     = trim((string) ($d['desc']     ?? ''));
        $features = is_array($d['features'] ?? null)
            ? implode(', ', array_filter((array) $d['features']))
            : (string) ($d['features'] ?? '');

        return <<<PROMPT
You are an expert copywriter creating rich, detailed content for a 10-page premium company profile presentation.

Business information:
- Company name: {$name}
- Industry / Category: {$category}
- Existing tagline (improve it): {$tagline}
- Existing description (enhance it): {$desc}
- Known services / features (expand with detail): {$features}

Generate professional, polished, DETAILED copy for ALL fields. Return ONLY this JSON (no markdown fences, no extra text):

{
  "headline": "2-5 impactful words in ALL CAPS style (max 42 chars)",
  "tagline": "One polished, memorable sentence — max 95 characters",
  "about_heading": "2-3 word heading for the About section",
  "description": "3-4 compelling sentences rich with value propositions — max 520 characters total",
  "services": [
    {"title": "Max 24 chars", "desc": "2 detailed sentences about this service — max 110 characters"},
    {"title": "Max 24 chars", "desc": "2 detailed sentences about this service — max 110 characters"},
    {"title": "Max 24 chars", "desc": "2 detailed sentences about this service — max 110 characters"},
    {"title": "Max 24 chars", "desc": "2 detailed sentences about this service — max 110 characters"}
  ],
  "cta": "Strong call-to-action phrase — max 44 characters",
  "mission": "Specific, inspiring mission statement — max 160 characters",
  "vision": "Bold, aspirational vision statement — max 160 characters",
  "why_choose_us": [
    {"title": "Max 24 chars", "desc": "2 sentences explaining this advantage in detail — max 130 characters"},
    {"title": "Max 24 chars", "desc": "2 sentences explaining this advantage in detail — max 130 characters"},
    {"title": "Max 24 chars", "desc": "2 sentences explaining this advantage in detail — max 130 characters"}
  ],
  "process_steps": [
    {"number": "01", "title": "Max 18 chars", "desc": "Detailed description of this phase — max 90 characters"},
    {"number": "02", "title": "Max 18 chars", "desc": "Detailed description of this phase — max 90 characters"},
    {"number": "03", "title": "Max 18 chars", "desc": "Detailed description of this phase — max 90 characters"},
    {"number": "04", "title": "Max 18 chars", "desc": "Detailed description of this phase — max 90 characters"}
  ],
  "values": [
    {"title": "Max 18 chars", "desc": "2 sentences about this value — max 75 characters"},
    {"title": "Max 18 chars", "desc": "2 sentences about this value — max 75 characters"},
    {"title": "Max 18 chars", "desc": "2 sentences about this value — max 75 characters"},
    {"title": "Max 18 chars", "desc": "2 sentences about this value — max 75 characters"}
  ],
  "testimonials": [
    {"quote": "Detailed, convincing client testimonial — max 165 characters", "name": "Realistic full name", "role": "Max 32 chars"},
    {"quote": "Detailed, convincing client testimonial — max 165 characters", "name": "Realistic full name", "role": "Max 32 chars"}
  ],
  "portfolio_heading": "Max 22 chars",
  "portfolio_items": [
    {"title": "Max 26 chars", "category": "Max 20 chars"},
    {"title": "Max 26 chars", "category": "Max 20 chars"},
    {"title": "Max 26 chars", "category": "Max 20 chars"}
  ]
}

Rules:
- Respect ALL character limits — text maps directly to fixed-width canvas text boxes.
- Do NOT fabricate certifications, awards, or employee counts unless provided.
- Enhance and expand existing content; keep all claims truthful and professional.
- Testimonials must sound authentic; use plausible, diverse names.
- Portfolio items should reflect the industry with realistic project titles.
- Make ALL descriptions substantive and detailed — avoid vague filler phrases.
- Output ONLY the raw JSON object. Nothing else.
PROMPT;
    }

    /** @param array<string,mixed> $d */
    private function buildCoverImagePrompt(array $d): string
    {
        $name     = trim((string) ($d['name']     ?? 'Company'));
        $category = trim((string) ($d['category'] ?? 'business'));
        $color    = trim((string) ($d['color']    ?? '#3B82F6'));

        return <<<PROMPT
Create a stunning premium corporate presentation cover background image.

Context:
- Business: {$name}
- Industry: {$category}
- Brand accent color: {$color}

Visual requirements:
- Landscape 16:9 format, full-bleed
- Dramatic dark abstract design (near-black base: navy, charcoal, or deep slate)
- Prominently feature {$color} — sweeping diagonal gradients, luminous edge glows, bold colour bands
- Style: ultra-premium enterprise, Fortune 500 quality, editorial magazine cover
- Layered depth: geometric shapes, angular light rays, fine mesh grid lines, bokeh orbs
- Atmosphere: authoritative, sophisticated, forward-looking
- Absolutely NO text, NO words, NO numbers, NO logos, NO human faces, NO identifiable real-world objects
- Must work as a dramatic slide background with white text overlaid on top
- Maximum visual impact — this is the most important image in the presentation
PROMPT;
    }

    /** @param array<string,mixed> $d */
    private function buildInteriorImagePrompt(array $d): string
    {
        $name     = trim((string) ($d['name']     ?? 'Company'));
        $category = trim((string) ($d['category'] ?? 'professional services'));
        $color    = trim((string) ($d['color']    ?? '#3B82F6'));

        return <<<PROMPT
Create a premium professional interior / workspace atmosphere image for a company profile presentation.

Context:
- Business: {$name}
- Industry: {$category}
- Brand accent color: {$color}

Visual requirements:
- Landscape 16:9 format, full-bleed
- Mood: modern corporate interior — glass walls, clean architectural lines, ambient lighting
- Soft bokeh depth of field effect, subtle {$color} accent lighting in the environment
- Overall tone: dark-to-mid — sophisticated and premium, suitable for white text overlay
- Style: architectural photography aesthetic, cinematic, editorial quality
- Can include: abstract office architecture, glass reflections, meeting room silhouettes, cityscape through glass
- Absolutely NO text, NO words, NO logos, NO identifiable faces or people
- Must work beautifully with a semi-transparent dark or white overlay on top
PROMPT;
    }

    /** @param array<string,mixed> $d */
    private function buildServicesImagePrompt(array $d): string
    {
        $name     = trim((string) ($d['name']     ?? 'Company'));
        $category = trim((string) ($d['category'] ?? 'technology'));
        $color    = trim((string) ($d['color']    ?? '#3B82F6'));

        return <<<PROMPT
Create a premium technology and innovation abstract background image for a company services presentation.

Context:
- Business: {$name}
- Industry: {$category}
- Brand accent color: {$color}

Visual requirements:
- Landscape 16:9 format, full-bleed
- Abstract tech / innovation aesthetic: data visualization patterns, circuit-like geometric networks, holographic grids
- {$color} as the dominant accent: glowing node connections, data stream lines, light trails
- Base tone: mid-to-dark — deep blue-grey, dark slate, or charcoal
- Style: high-tech, futuristic, innovation-focused, data-driven
- Depth and texture: multiple layered transparent geometric shapes, particle effects, 3D depth
- Absolutely NO text, NO words, NO numbers, NO logos, NO human faces
- Must look excellent with either a dark or light semi-transparent overlay on top
PROMPT;
    }

    /**
     * @param  array<string,mixed>|null  $json
     * @return array<string,mixed>
     */
    private function parseTextResponse(?array $json): array
    {
        $fallback = [
            'headline'          => 'EXCELLENCE IN EVERY STEP',
            'tagline'           => '',
            'about_heading'     => 'About Us',
            'description'       => '',
            'services'          => [],
            'cta'               => 'Get in touch today',
            'mission'           => 'To deliver outstanding solutions that create measurable, lasting value for every client we serve.',
            'vision'            => 'To be the most trusted, innovative, and impactful partner in our industry.',
            'why_choose_us'     => [],
            'process_steps'     => [],
            'values'            => [],
            'testimonials'      => [],
            'portfolio_heading' => 'Our Work',
            'portfolio_items'   => [],
        ];

        if (! is_array($json)) {
            return $fallback;
        }

        $text = '';
        $parts = $json['candidates'][0]['content']['parts'] ?? [];
        foreach ((array) $parts as $part) {
            if (is_array($part) && isset($part['text'])) {
                $text .= (string) $part['text'];
            }
        }

        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text)) ?? '';
        $text = preg_replace('/\s*```\s*$/i', '', $text) ?? '';
        $text = trim($text);

        $parsed = json_decode($text, true);
        if (! is_array($parsed)) {
            return $fallback;
        }

        $str = fn (mixed $v, int $max = 255, string $def = '') =>
            mb_substr(trim((string) ($v ?? $def)), 0, $max);

        $services = [];
        foreach ((array) ($parsed['services'] ?? []) as $s) {
            if (is_array($s) && isset($s['title'])) {
                $services[] = ['title' => $str($s['title'], 32), 'desc' => $str($s['desc'] ?? '', 130)];
            }
        }

        $why = [];
        foreach ((array) ($parsed['why_choose_us'] ?? []) as $w) {
            if (is_array($w) && isset($w['title'])) {
                $why[] = ['title' => $str($w['title'], 32), 'desc' => $str($w['desc'] ?? '', 150)];
            }
        }

        $steps = [];
        foreach ((array) ($parsed['process_steps'] ?? []) as $i => $s) {
            if (is_array($s)) {
                $steps[] = [
                    'number' => $str($s['number'] ?? sprintf('%02d', $i + 1), 4),
                    'title'  => $str($s['title']  ?? '', 24),
                    'desc'   => $str($s['desc']   ?? '', 110),
                ];
            }
        }

        $values = [];
        foreach ((array) ($parsed['values'] ?? []) as $v) {
            if (is_array($v) && isset($v['title'])) {
                $values[] = ['title' => $str($v['title'], 24), 'desc' => $str($v['desc'] ?? '', 90)];
            }
        }

        $testimonials = [];
        foreach ((array) ($parsed['testimonials'] ?? []) as $t) {
            if (is_array($t) && isset($t['quote'])) {
                $testimonials[] = [
                    'quote' => $str($t['quote'], 190),
                    'name'  => $str($t['name']  ?? 'Client', 36),
                    'role'  => $str($t['role']  ?? '', 40),
                ];
            }
        }

        $portfolio = [];
        foreach ((array) ($parsed['portfolio_items'] ?? []) as $p) {
            if (is_array($p) && isset($p['title'])) {
                $portfolio[] = ['title' => $str($p['title'], 34), 'category' => $str($p['category'] ?? '', 28)];
            }
        }

        return [
            'headline'          => $str($parsed['headline']      ?? $fallback['headline'], 60),
            'tagline'           => $str($parsed['tagline']        ?? '', 130),
            'about_heading'     => $str($parsed['about_heading']  ?? 'About Us', 40),
            'description'       => $str($parsed['description']    ?? '', 600),
            'services'          => array_slice($services, 0, 4),
            'cta'               => $str($parsed['cta']            ?? $fallback['cta'], 60),
            'mission'           => $str($parsed['mission']        ?? $fallback['mission'], 190),
            'vision'            => $str($parsed['vision']         ?? $fallback['vision'], 190),
            'why_choose_us'     => array_slice($why, 0, 3),
            'process_steps'     => array_slice($steps, 0, 4),
            'values'            => array_slice($values, 0, 4),
            'testimonials'      => array_slice($testimonials, 0, 2),
            'portfolio_heading' => $str($parsed['portfolio_heading'] ?? 'Our Work', 30),
            'portfolio_items'   => array_slice($portfolio, 0, 3),
        ];
    }

    /**
     * @param  array<string,mixed>|null  $json
     */
    private function parseImageResponse(?array $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        $parts = $json['candidates'][0]['content']['parts'] ?? [];
        foreach ((array) $parts as $part) {
            if (! is_array($part)) {
                continue;
            }
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (! is_array($inline)) {
                continue;
            }
            $data = $inline['data'] ?? null;
            if (! is_string($data) || $data === '') {
                continue;
            }
            $mime = (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? 'image/jpeg');

            return 'data:'.$mime.';base64,'.$data;
        }

        return null;
    }
}
