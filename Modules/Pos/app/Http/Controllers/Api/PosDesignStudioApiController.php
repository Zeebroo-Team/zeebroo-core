<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\DesignStudio\Models\Design;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosDesignStudioApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('design_studio_designs')) {
            return response()->json(['data' => [], 'total_count' => 0]);
        }

        $query = Design::query()
            ->where('business_id', $business->id)
            ->whereNull('proposal_group')
            ->orderByDesc('updated_at');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $designs = $query->get();

        $byType = $designs->groupBy(fn (Design $d) => $d->type ?? 'custom');

        return response()->json([
            'data'        => $designs->map(fn (Design $d) => $this->format($d))->values(),
            'total_count' => $designs->count(),
            'by_type'     => $byType->map->count(),
        ]);
    }

    private const SINGLETON_TYPES = ['letterhead', 'company-profile'];

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'design_all');
        $user     = $request->user();

        if (! Schema::hasTable('design_studio_designs')) {
            return response()->json(['message' => 'Design Studio module is not set up yet.'], 422);
        }

        $validated = $request->validate([
            'title'  => ['required', 'string', 'max:120'],
            'type'   => ['nullable', 'string', 'max:50'],
            'width'  => ['required', 'integer', 'min:100', 'max:8000'],
            'height' => ['required', 'integer', 'min:100', 'max:8000'],
        ]);

        $type = $validated['type'] ?? null;

        if ($type && in_array($type, self::SINGLETON_TYPES, true)) {
            $existing = Design::where('business_id', $business->id)
                ->where('type', $type)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => ucfirst(str_replace('-', ' ', $type)) . ' already exists for this business.',
                    'data'    => $this->format($existing),
                ], 422);
            }
        }

        $design = Design::create([
            'business_id' => $business->id,
            'user_id'     => $user->id,
            'title'       => $validated['title'],
            'type'        => $type,
            'width'       => $validated['width'],
            'height'      => $validated['height'],
            'canvas_json' => null,
        ]);

        return response()->json(['data' => $this->format($design), 'message' => 'Design created.'], 201);
    }

    public function show(Request $request, Design $design): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $design->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Design not found.'], 404);
        }

        return response()->json(['data' => $this->formatFull($design)]);
    }

    public function update(Request $request, Design $design): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'design_all');

        if ((int) $design->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Design not found.'], 404);
        }

        $validated = $request->validate([
            'title'       => ['sometimes', 'string', 'max:120'],
            'canvas_json' => ['nullable', 'string'],
        ]);

        $design->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json(['data' => $this->formatFull($design), 'message' => 'Saved.']);
    }

    public function destroy(Request $request, Design $design): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'design_all');

        if ((int) $design->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Design not found.'], 404);
        }

        $design->delete();

        return response()->json(['message' => 'Design deleted.']);
    }

    // ── Project Proposals ─────────────────────────────────────────────────────

    private const PROPOSAL_PAGES = [
        ['label' => 'Cover Page',           'sort' => 1],
        ['label' => 'Executive Summary',    'sort' => 2],
        ['label' => 'Our Services',         'sort' => 3],
        ['label' => 'Project Timeline',     'sort' => 4],
        ['label' => 'Investment & Pricing', 'sort' => 5],
        ['label' => 'Contact & Next Steps', 'sort' => 6],
    ];

    public function proposals(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('design_studio_designs')) {
            return response()->json(['data' => []]);
        }

        $pages = Design::where('business_id', $business->id)
            ->whereNotNull('proposal_group')
            ->orderBy('proposal_group')
            ->orderBy('proposal_sort')
            ->get();

        $grouped = $pages->groupBy('proposal_group')->map(function ($groupPages, $group) {
            $first    = $groupPages->first();
            // Each page title: "ProjectTitle — ClientName — PageLabel"  (client optional)
            // $propPrefix = everything before the last " — " separator (page label)
            $rawTitle  = $first->title ?? '';
            $propPrefix = str_contains($rawTitle, ' — ')
                ? substr($rawTitle, 0, strrpos($rawTitle, ' — '))
                : $rawTitle;

            // Split prefix into project title + optional client
            $client = null;
            if (str_contains($propPrefix, ' — ')) {
                [$propTitle, $client] = explode(' — ', $propPrefix, 2);
            } else {
                $propTitle = $propPrefix;
            }

            return [
                'group'      => $group,
                'title'      => $propTitle,
                'client'     => $client,
                'page_count' => $groupPages->count(),
                'has_canvas' => $groupPages->where('canvas_json', '!=', null)->isNotEmpty(),
                'updated_at' => $groupPages->max('updated_at'),
                'pages'      => $groupPages->map(fn ($d) => $this->format($d))->values(),
            ];
        })->sortByDesc('updated_at')->values();

        return response()->json(['data' => $grouped]);
    }

    public function storeProposal(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'design_all');

        if (! Schema::hasTable('design_studio_designs')) {
            return response()->json(['message' => 'Design Studio is not set up yet.'], 422);
        }

        $validated = $request->validate([
            'title'  => ['required', 'string', 'max:100'],
            'client' => ['nullable', 'string', 'max:100'],
        ]);

        $group  = (string) Str::uuid();
        $prefix = trim($validated['title'])
            . ($validated['client'] ? ' — ' . trim($validated['client']) : '');

        $created = [];
        foreach (self::PROPOSAL_PAGES as $page) {
            $design = Design::create([
                'business_id'    => $business->id,
                'user_id'        => $request->user()?->id,
                'title'          => $prefix . ' — ' . $page['label'],
                'type'           => 'project-proposal',
                'width'          => 794,
                'height'         => 1123,
                'canvas_json'    => null,
                'proposal_group' => $group,
                'proposal_sort'  => $page['sort'],
            ]);
            $created[] = $this->format($design);
        }

        return response()->json([
            'data' => [
                'group'  => $group,
                'title'  => $prefix,
                'pages'  => $created,
            ],
            'message' => 'Project proposal created.',
        ], 201);
    }

    public function addProposalPage(Request $request, string $group): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'design_all');

        if (! Schema::hasTable('design_studio_designs')) {
            return response()->json(['message' => 'Design Studio is not set up yet.'], 422);
        }

        $existing = Design::where('business_id', $business->id)
            ->where('proposal_group', $group)
            ->orderByDesc('proposal_sort')
            ->first();

        if (! $existing) {
            return response()->json(['message' => 'Proposal not found.'], 404);
        }

        $nextSort = ((int) $existing->proposal_sort) + 1;

        $prefix     = str_contains($existing->title, ' — ')
            ? substr($existing->title, 0, strrpos($existing->title, ' — '))
            : $existing->title;

        $invoiceId    = $request->input('invoice_id')    ? (int) $request->input('invoice_id')    : null;
        $quotationId  = $request->input('quotation_id')  ? (int) $request->input('quotation_id')  : null;

        $design = Design::create([
            'business_id'    => $business->id,
            'user_id'        => $request->user()?->id,
            'title'          => $prefix . ' — Page ' . $nextSort,
            'type'           => 'project-proposal',
            'width'          => $existing->width,
            'height'         => $existing->height,
            'canvas_json'    => null,
            'proposal_group' => $group,
            'proposal_sort'  => $nextSort,
            'invoice_id'     => $invoiceId,
            'quotation_id'   => $quotationId,
        ]);

        return response()->json(['data' => $this->format($design)], 201);
    }

    public function proposalPages(Request $request, string $group): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $pages = Design::where('business_id', $business->id)
            ->where('proposal_group', $group)
            ->orderBy('proposal_sort')
            ->get();

        if ($pages->isEmpty()) {
            return response()->json(['message' => 'Proposal not found.'], 404);
        }

        $first     = $pages->first();
        $rawTitle  = $first->title ?? '';
        $propPrefix = str_contains($rawTitle, ' — ')
            ? substr($rawTitle, 0, strrpos($rawTitle, ' — '))
            : $rawTitle;

        $client    = null;
        $propTitle = $propPrefix;
        if (str_contains($propPrefix, ' — ')) {
            [$propTitle, $client] = explode(' — ', $propPrefix, 2);
        }

        return response()->json([
            'data' => [
                'group'  => $group,
                'title'  => $propTitle,
                'client' => $client,
                'pages'  => $pages->map(fn ($d) => $this->formatFull($d))->values(),
            ],
        ]);
    }

    private const PROPOSAL_AI_MODELS = [
        'gemini-2.5-flash',
        'gemini-2.0-flash',
        'gemini-1.5-flash',
    ];

    private const PROPOSAL_AI_SYSTEM = <<<'SYS'
You are a professional business proposal writer.
Your ENTIRE response must be a single raw JSON object. Rules:
1. Start with { and end with }. No markdown. No ```json fences. No prose before or after.
2. Do NOT wrap the JSON in code blocks. The very first character must be {.
3. All string values must be on one line — no literal newlines inside JSON string values.
SYS;

    public function aiProposalContent(Request $request): JsonResponse
    {
        $this->businessOrAbort($request);

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:120'],
            'client'      => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1500'],
        ]);

        $apiKey = config('services.gemini.key');
        if (! $apiKey) {
            return response()->json(['message' => 'AI is not configured on this server.'], 422);
        }

        $userMessage = $this->buildProposalUserMessage(
            $validated['title'],
            $validated['client'] ?? '',
            $validated['description']
        );

        $models   = array_unique(array_merge(
            [config('services.gemini.model', 'gemini-2.5-flash')],
            self::PROPOSAL_AI_MODELS
        ));

        $payload = [
            'systemInstruction' => ['parts' => [['text' => self::PROPOSAL_AI_SYSTEM]]],
            'contents'          => [['role' => 'user', 'parts' => [['text' => $userMessage]]]],
            'generationConfig'  => [
                'responseMimeType' => 'application/json',
                'maxOutputTokens'  => 8192,
                'temperature'      => 0.7,
            ],
        ];

        $content = null;
        try {
            foreach ($models as $model) {
                $url  = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                $resp = Http::timeout(90)->post($url, $payload);

                if ($resp->status() === 429) {
                    sleep(3);
                    $resp = Http::timeout(90)->post($url, $payload);
                }

                if (! $resp->successful()) {
                    \Log::warning('Proposal AI: non-2xx', ['model' => $model, 'status' => $resp->status(), 'body' => substr($resp->body(), 0, 300)]);
                    continue;
                }

                $raw = $resp->json('candidates.0.content.parts.0.text');
                if (! $raw) {
                    \Log::warning('Proposal AI: empty text', ['model' => $model]);
                    continue;
                }

                $parsed = json_decode($raw, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Fallback: strip any accidental fences and try again
                    $clean = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($raw));
                    $clean = ltrim($clean, "\xEF\xBB\xBF");
                    $clean = $this->sanitizeProposalJson($clean);
                    $parsed = json_decode($clean, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $start = strpos($clean, '{');
                        if ($start !== false) {
                            $parsed = json_decode(substr($clean, $start), true);
                        }
                    }
                }

                if (json_last_error() === JSON_ERROR_NONE && is_array($parsed) &&
                    (isset($parsed['tagline']) || isset($parsed['summary']) || isset($parsed['services']))) {
                    $content = $parsed;
                    break;
                }

                \Log::warning('Proposal AI: JSON parse failed', ['model' => $model, 'error' => json_last_error_msg(), 'raw_prefix' => substr($raw, 0, 400)]);
            }
        } catch (\Exception $e) {
            \Log::error('Proposal AI: exception', ['message' => $e->getMessage()]);
        }

        if (! $content) {
            return response()->json(['message' => 'AI generation failed. Please try again.'], 422);
        }

        return response()->json(['content' => $content]);
    }

    private function buildProposalUserMessage(string $title, string $client, string $description): string
    {
        $clientLine = $client ? "Client: {$client}\n" : '';

        $schema = <<<'SCHEMA'
{
  "tagline": "A compelling 5-8 word tagline",
  "summary": "A 2-3 sentence executive summary",
  "summary_points": ["Key outcome 1", "Key outcome 2", "Key outcome 3"],
  "services": [
    {"name": "Service Name", "desc": "One-line description"}
  ],
  "timeline": [
    {"phase": "Phase 1", "title": "Phase Name", "duration": "Week 1-2", "desc": "What happens"}
  ],
  "pricing": {
    "subtitle": "Investment Overview",
    "items": [{"name": "Item Name", "amount": "$X,XXX"}],
    "total": "$XX,XXX",
    "note": "Payment terms note"
  },
  "contact": {
    "intro": "One sentence introducing next steps",
    "steps": ["Step 1", "Step 2", "Step 3"]
  }
}
SCHEMA;

        return "Generate professional proposal content for:\n\n"
            . "Project Title: {$title}\n"
            . $clientLine
            . "Description: {$description}\n\n"
            . "Return a JSON object matching this exact schema (include 4-6 services, 3-4 timeline phases, 4-6 pricing items):\n"
            . $schema;
    }

    private function sanitizeProposalJson(string $text): string
    {
        $result   = '';
        $inString = false;
        $escape   = false;

        for ($i = 0, $len = strlen($text); $i < $len; $i++) {
            $c = $text[$i];
            if ($escape) { $result .= $c; $escape = false; continue; }
            if ($c === '\\' && $inString) { $result .= $c; $escape = true; continue; }
            if ($c === '"') { $result .= $c; $inString = !$inString; continue; }
            if ($inString) {
                if ($c === "\n") { $result .= '\\n'; continue; }
                if ($c === "\r") { $result .= '\\r'; continue; }
                if ($c === "\t") { $result .= '\\t'; continue; }
                if (ord($c) < 0x20) { continue; }
            }
            $result .= $c;
        }

        return $result;
    }

    public function aiProposalFill(Request $request, string $group): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'design_all');

        $pages = Design::where('business_id', $business->id)
            ->where('proposal_group', $group)
            ->orderBy('proposal_sort')
            ->get();

        if ($pages->isEmpty()) {
            return response()->json(['message' => 'Proposal not found.'], 404);
        }

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:120'],
            'client'      => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1500'],
        ]);

        $apiKey = config('services.gemini.key');
        if (! $apiKey) {
            return response()->json(['message' => 'AI is not configured on this server.'], 422);
        }

        $userMessage = $this->buildProposalUserMessage(
            $validated['title'],
            $validated['client'] ?? '',
            $validated['description']
        );

        $models  = array_unique(array_merge(
            [config('services.gemini.model', 'gemini-2.5-flash')],
            self::PROPOSAL_AI_MODELS
        ));
        $payload = [
            'systemInstruction' => ['parts' => [['text' => self::PROPOSAL_AI_SYSTEM]]],
            'contents'          => [['role' => 'user', 'parts' => [['text' => $userMessage]]]],
            'generationConfig'  => [
                'responseMimeType' => 'application/json',
                'maxOutputTokens'  => 8192,
                'temperature'      => 0.7,
            ],
        ];

        $content = null;
        try {
            foreach ($models as $model) {
                $url  = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                $resp = Http::timeout(90)->post($url, $payload);
                if ($resp->status() === 429) { sleep(3); $resp = Http::timeout(90)->post($url, $payload); }
                if (! $resp->successful()) {
                    \Log::warning('Proposal fill AI: non-2xx', ['model' => $model, 'status' => $resp->status(), 'body' => substr($resp->body(), 0, 300)]);
                    continue;
                }
                $raw = $resp->json('candidates.0.content.parts.0.text');
                if (! $raw) { continue; }

                $parsed = json_decode($raw, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $clean = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($raw));
                    $clean = ltrim($clean, "\xEF\xBB\xBF");
                    $clean = $this->sanitizeProposalJson($clean);
                    $parsed = json_decode($clean, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $start = strpos($clean, '{');
                        if ($start !== false) { $parsed = json_decode(substr($clean, $start), true); }
                    }
                }

                if (json_last_error() === JSON_ERROR_NONE && is_array($parsed) &&
                    (isset($parsed['tagline']) || isset($parsed['summary']) || isset($parsed['services']))) {
                    $content = $parsed;
                    break;
                }
                \Log::warning('Proposal fill AI: parse failed', ['model' => $model, 'err' => json_last_error_msg(), 'raw_prefix' => substr($raw ?? '', 0, 400)]);
            }
        } catch (\Exception $e) {
            \Log::error('Proposal fill AI: exception', ['msg' => $e->getMessage()]);
        }

        if (! $content) {
            return response()->json(['message' => 'AI generation failed. Please try again.'], 422);
        }

        $title  = $validated['title'];
        $client = $validated['client'] ?? '';

        foreach ($pages as $page) {
            $canvasJson = $this->buildPageCanvasJson((int) $page->proposal_sort, $content, $title, $client);
            $page->canvas_json = $canvasJson;
            $page->save();
        }

        return response()->json([
            'message' => 'Proposal filled with AI content.',
            'data'    => [
                'group' => $group,
                'pages' => $pages->map(fn ($d) => $this->format($d))->values(),
            ],
        ]);
    }

    private function buildPageCanvasJson(int $sort, array $ai, string $title, string $client): string
    {
        $W   = 794;
        $H   = 1123;
        $P   = '#7c3aed';
        $PD  = '#6d28d9';
        $PDK = '#1e1b4b';
        $TX  = '#1f2937';
        $MT  = '#6b7280';
        $WH  = '#ffffff';
        $PL  = '#f5f3ff';

        $objects    = [];
        $background = $WH;

        $rect = function (int $l, int $t, int $w, int $h, string $fill) use (&$objects): void {
            $objects[] = ['type' => 'rect', 'left' => $l, 'top' => $t, 'width' => $w, 'height' => $h, 'fill' => $fill];
        };
        $txt = function (string $str, int $l, int $t, int $w, int $size, bool $bold, string $color, string $font, float $lh = 1.5) use (&$objects): void {
            $str       = mb_substr(trim($str) ?: ' ', 0, 500);
            $objects[] = [
                'type' => 'textbox', 'text' => $str,
                'left' => $l, 'top' => $t, 'width' => $w,
                'fontSize' => $size, 'fontWeight' => $bold ? 'bold' : 'normal',
                'fill' => $color, 'fontFamily' => $font, 'lineHeight' => $lh,
            ];
        };
        $header = function (string $label) use ($rect, $txt, $W, $P, $PD, $WH, $title, $client): void {
            $rect(0, 0, $W, 100, $P);
            $rect(0, 100, $W, 4, $PD);
            $txt($label, 50, 28, $W - 100, 26, true, $WH, 'Montserrat', 1.2);
            $sub = $title . ($client ? '  ·  ' . $client : '');
            $txt($sub, 50, 67, $W - 100, 10, false, 'rgba(255,255,255,0.6)', 'Inter', 1.0);
        };
        $footer = function (int $pg) use ($rect, $txt, $W, $H, $MT, $title, $client): void {
            $rect(50, $H - 46, $W - 100, 1, '#e5e7eb');
            $sub = $title . ($client ? '  ·  ' . $client : '');
            $txt($sub, 50, $H - 34, $W - 160, 10, false, $MT, 'Inter', 1.0);
            $txt('Page ' . $pg . ' of 6', $W - 110, $H - 34, 70, 10, false, $MT, 'Inter', 1.0);
        };

        $services = (array) ($ai['services'] ?? []);
        $timeline = (array) ($ai['timeline'] ?? []);
        $pr       = (array) ($ai['pricing'] ?? []);
        $ct       = (array) ($ai['contact'] ?? []);
        $points   = (array) ($ai['summary_points'] ?? []);
        $phColors = [$P, '#059669', '#f59e0b', '#ef4444', '#0284c7'];

        switch ($sort) {

            // ─── COVER PAGE ───────────────────────────────────────────────────
            case 1:
                $background = $PDK;
                $rect(0, 0, $W, $H, $PDK);
                $rect(0, 0, 6, $H, $P);
                $rect(0, 0, $W, 5, $P);
                $rect(0, $H - 5, $W, 5, $P);

                // ── Top section ──
                $txt('PROJECT PROPOSAL', 50, 48, 300, 9, true, 'rgba(167,139,250,0.75)', 'Inter', 1.0);
                $rect(50, 66, 694, 1, 'rgba(124,58,237,0.35)');
                $txt($title ?: 'Project Proposal', 50, 82, 694, 46, true, $WH, 'Montserrat', 1.15);
                if ($client) {
                    $txt('PREPARED FOR', 50, 210, 200, 9, true, 'rgba(167,139,250,0.7)', 'Inter', 1.0);
                    $txt($client, 50, 227, 500, 20, true, 'rgba(255,255,255,0.88)', 'Montserrat', 1.2);
                }
                $taglineY = $client ? 264 : 210;
                if (! empty($ai['tagline'])) {
                    $txt($ai['tagline'], 50, $taglineY, 694, 14, false, 'rgba(255,255,255,0.5)', 'Inter', 1.45);
                }

                // ── Purple overview panel ──
                $rect(0, 308, $W, 365, $P);
                $txt('PROPOSAL OVERVIEW', 50, 330, 400, 9, true, 'rgba(255,255,255,0.65)', 'Inter', 1.0);
                if (! empty($ai['summary'])) {
                    $summaryClip = mb_substr($ai['summary'], 0, 260);
                    $txt($summaryClip, 50, 350, $W - 100, 13, false, 'rgba(255,255,255,0.88)', 'Inter', 1.65);
                }
                $rect(50, 455, 694, 1, 'rgba(255,255,255,0.22)');
                $txt('KEY OUTCOMES', 50, 472, 400, 9, true, 'rgba(255,255,255,0.65)', 'Inter', 1.0);
                $py = 493;
                foreach (array_slice($points, 0, 3) as $pt) {
                    $rect(50, $py + 5, 5, 5, 'rgba(255,255,255,0.55)');
                    $txt(mb_substr((string) $pt, 0, 120), 64, $py, $W - 120, 12, false, 'rgba(255,255,255,0.82)', 'Inter', 1.45);
                    $py += 32;
                }

                // ── Table of contents ──
                $rect(50, 718, 694, 1, 'rgba(255,255,255,0.12)');
                $txt("WHAT'S INSIDE THIS PROPOSAL", 50, 730, 400, 9, true, 'rgba(255,255,255,0.38)', 'Inter', 1.0);
                $tocPages = ['Cover Page', 'Executive Summary', 'Our Services', 'Project Timeline', 'Investment & Pricing', 'Contact & Next Steps'];
                foreach ($tocPages as $ti => $tlabel) {
                    $col2 = $ti < 3 ? 50 : 420;
                    $row  = $ti % 3;
                    $ty   = 758 + $row * 36;
                    $txt(sprintf('%02d', $ti + 1), $col2, $ty, 22, 11, true, 'rgba(167,139,250,0.75)', 'Montserrat', 1.0);
                    $txt($tlabel, $col2 + 28, $ty, 340, 12, false, 'rgba(255,255,255,0.62)', 'Inter', 1.0);
                }

                // ── Footer date ──
                $rect(50, 1040, 694, 1, 'rgba(255,255,255,0.1)');
                $txt(date('F Y'), 50, 1057, 250, 10, false, 'rgba(255,255,255,0.3)', 'Inter', 1.0);
                $txt('CONFIDENTIAL', $W - 168, 1057, 118, 9, true, 'rgba(255,255,255,0.25)', 'Inter', 1.0);
                break;

            // ─── EXECUTIVE SUMMARY ───────────────────────────────────────────
            case 2:
                $header('Executive Summary');
                $footer(2);
                $y = 128;

                // Summary paragraph
                if (! empty($ai['summary'])) {
                    $txt($ai['summary'], 50, $y, $W - 100, 13, false, $TX, 'Inter', 1.65);
                    $lc = max(2, (int) ceil(mb_strlen($ai['summary']) / 78));
                    $y += $lc * 22 + 22;
                }

                // Key highlights
                $rect(50, $y, $W - 100, 1, '#e5e7eb');
                $y += 18;
                $txt('Key Highlights', 50, $y, $W - 100, 16, true, $PD, 'Montserrat', 1.2);
                $y += 34;
                foreach (array_slice($points, 0, 3) as $pt) {
                    $rect(50, $y + 6, 4, 14, $P);
                    $txt((string) $pt, 66, $y, $W - 130, 13, false, $TX, 'Inter', 1.5);
                    $y += 36;
                }
                $y += 14;

                // Stats boxes
                $svcCount  = count($services);
                $phCnt     = count($timeline);
                $totalCost = $pr['total'] ?? 'On Request';
                $stats     = [[$P, (string) $svcCount . ' Services', 'Deliverables'], ['#059669', (string) $phCnt . ' Phases', 'Project Phases'], ['#ec4899', $totalCost, 'Total Investment']];
                foreach ($stats as $bi => [$bc, $bv, $bl]) {
                    $bx  = 50 + $bi * 228;
                    $fsz = mb_strlen($bv) > 9 ? 17 : 24;
                    $rect($bx, $y, 214, 82, $bc);
                    $txt($bv, $bx + 14, $y + 13, 186, $fsz, true, $WH, 'Montserrat', 1.0);
                    $txt($bl, $bx + 14, $y + 56, 186, 9, false, 'rgba(255,255,255,0.72)', 'Inter', 1.0);
                }
                $y += 100;

                // Proposed services preview
                $rect(50, $y, $W - 100, 1, '#e5e7eb');
                $y += 18;
                $txt('Proposed Services', 50, $y, $W - 100, 16, true, $PD, 'Montserrat', 1.2);
                $y += 32;
                foreach (array_slice($services, 0, 4) as $si => $svc) {
                    $rect(50, $y + 3, 4, 42, $P);
                    $txt($svc['name'] ?? '', 62, $y + 3, $W - 130, 13, true, $PDK, 'Montserrat', 1.2);
                    $txt($svc['desc'] ?? '', 62, $y + 22, $W - 130, 12, false, $MT, 'Inter', 1.45);
                    $y += 54;
                    if ($si < 3) { $rect(62, $y - 2, $W - 160, 1, '#f3f4f6'); }
                }
                $y += 10;

                // Commitment panel — fills remaining height to footer
                $panelTop = $y;
                $panelH   = max(80, ($H - 50) - $panelTop);
                $rect(50, $panelTop, $W - 100, $panelH, $PL);
                $rect(50, $panelTop, 4, $panelH, $P);
                $txt('Our Commitment', 70, $panelTop + 16, $W - 130, 14, true, $PD, 'Montserrat', 1.2);
                if (! empty($ai['tagline'])) {
                    $txt($ai['tagline'], 70, $panelTop + 40, $W - 130, 13, false, $TX, 'Inter', 1.5);
                }
                $commitment = 'We are committed to delivering measurable results across all ' . $svcCount . ' service areas — on time, within budget, and to the highest standard.';
                $txt($commitment, 70, $panelTop + 72, $W - 130, 12, false, $MT, 'Inter', 1.55);
                break;

            // ─── OUR SERVICES ────────────────────────────────────────────────
            case 3:
                $header('Our Services');
                $footer(3);
                $y = 125;

                // Service list
                foreach ($services as $i => $svc) {
                    if ($y > 820) break;
                    if ($i > 0) { $rect(50, $y, $W - 100, 1, '#e5e7eb'); $y += 14; }
                    $rect(50, $y + 4, 3, 46, $P);
                    $txt($svc['name'] ?? '', 63, $y + 4, $W - 130, 14, true, $PDK, 'Montserrat', 1.2);
                    $txt($svc['desc'] ?? '', 63, $y + 26, $W - 130, 12, false, $MT, 'Inter', 1.5);
                    $y += 68;
                }
                $y += 10;

                // Why Choose Us — 3 feature boxes
                $rect(50, $y, $W - 100, 1, '#e5e7eb');
                $y += 18;
                $txt('Why Choose Us', 50, $y, $W - 100, 16, true, $PD, 'Montserrat', 1.2);
                $y += 30;
                $feats  = [[$P, 'Expert Team', 'Skilled professionals dedicated to your success and long-term growth.'], ['#059669', 'Proven Process', 'Structured methodology that ensures quality results on every project.'], ['#f59e0b', 'Full Support', 'End-to-end assistance from kickoff through completion and beyond.']];
                foreach ($feats as $fi => [$fc, $fn, $fd]) {
                    $fx = 50 + $fi * 232;
                    $rect($fx, $y, 212, 100, $PL);
                    $rect($fx, $y, 212, 4, $fc);
                    $txt($fn, $fx + 12, $y + 16, 188, 13, true, $PDK, 'Montserrat', 1.2);
                    $txt($fd, $fx + 12, $y + 36, 188, 11, false, $MT, 'Inter', 1.5);
                }
                $y += 118;

                // Full-width tagline panel — anchored near bottom
                $panelTop3 = max($y + 10, $H - 155);
                $rect(0, $panelTop3, $W, 105, $PDK);
                $tagText = ! empty($ai['tagline']) ? $ai['tagline'] : 'Excellence in every deliverable.';
                $txt($tagText, 50, $panelTop3 + 22, $W - 100, 20, true, $WH, 'Montserrat', 1.2);
                $txt('Contact us today to learn more about how we can deliver this scope for your business.', 50, $panelTop3 + 56, $W - 100, 12, false, 'rgba(255,255,255,0.7)', 'Inter', 1.5);
                break;

            // ─── PROJECT TIMELINE ────────────────────────────────────────────
            case 4:
                $header('Project Timeline');
                $footer(4);
                $y     = 125;
                $total = count($timeline);

                // Timeline phases
                foreach ($timeline as $i => $phase) {
                    if ($y > 700) break;
                    $col = $phColors[$i % count($phColors)];
                    $rect(50, $y + 9, 16, 16, $col);
                    if ($i < $total - 1) { $rect(57, $y + 25, 2, 62, '#e5e7eb'); }
                    $txt($phase['phase'] ?? ('Phase ' . ($i + 1)), 78, $y + 2, 120, 10, true, $col, 'Inter', 1.0);
                    $txt($phase['title'] ?? '', 78, $y + 16, $W - 240, 14, true, $PDK, 'Montserrat', 1.15);
                    if (! empty($phase['duration'])) {
                        $rect($W - 178, $y + 12, 128, 24, $PL);
                        $txt($phase['duration'], $W - 174, $y + 17, 120, 11, false, $PD, 'Inter', 1.0);
                    }
                    $txt($phase['desc'] ?? '', 78, $y + 36, $W - 200, 12, false, $MT, 'Inter', 1.5);
                    $y += 92;
                }
                $y += 8;

                // Key deliverables grid (from services list)
                $rect(50, $y, $W - 100, 1, '#e5e7eb');
                $y += 18;
                $txt('Key Deliverables', 50, $y, $W - 100, 16, true, $PD, 'Montserrat', 1.2);
                $y += 30;
                foreach (array_slice($services, 0, 6) as $di => $deliv) {
                    $dx = $di % 2 === 0 ? 50 : 420;
                    $dy = $y + (int) ($di / 2) * 38;
                    $rect($dx, $dy + 8, 6, 6, $phColors[$di % count($phColors)]);
                    $txt($deliv['name'] ?? '', $dx + 18, $dy + 5, 330, 12, false, $TX, 'Inter', 1.35);
                }
                $y += (int) ceil(count(array_slice($services, 0, 6)) / 2) * 38 + 16;

                // Project success panel — fills to footer
                $panelTop4 = max($y + 10, $H - 165);
                $rect(0, $panelTop4, $W, 120, $P);
                $txt('Delivering Your Vision, On Time', 50, $panelTop4 + 18, $W - 100, 18, true, $WH, 'Montserrat', 1.2);
                $successText = 'Each phase is carefully planned to ensure seamless execution, transparent communication, and results that exceed expectations.';
                $txt($successText, 50, $panelTop4 + 50, $W - 100, 12, false, 'rgba(255,255,255,0.82)', 'Inter', 1.55);
                break;

            // ─── INVESTMENT & PRICING ────────────────────────────────────────
            case 5:
                $header('Investment & Pricing');
                $footer(5);
                $y = 128;

                // Subtitle
                if (! empty($pr['subtitle'])) {
                    $txt($pr['subtitle'], 50, $y, $W - 100, 15, true, $PDK, 'Montserrat', 1.2);
                    $y += 32;
                }

                // Pricing table
                $rect(50, $y, $W - 100, 40, $PDK);
                $txt('Service / Deliverable', 66, $y + 12, 360, 11, true, $WH, 'Inter', 1.0);
                $txt('Amount', $W - 186, $y + 12, 110, 11, true, $WH, 'Inter', 1.0);
                $y += 40;
                foreach ((array) ($pr['items'] ?? []) as $i => $item) {
                    $bg = $i % 2 === 0 ? $WH : $PL;
                    $rect(50, $y, $W - 100, 38, $bg);
                    $rect(50, $y, $W - 100, 1, '#e5e7eb');
                    $txt($item['name'] ?? '', 66, $y + 12, 340, 12, false, $TX, 'Inter', 1.0);
                    $txt($item['amount'] ?? '', $W - 186, $y + 12, 110, 12, false, $TX, 'Inter', 1.0);
                    $y += 38;
                }
                $y += 6;

                // Total row
                $rect(50, $y, $W - 100, 54, $P);
                $txt('TOTAL INVESTMENT', 66, $y + 18, 280, 12, true, $WH, 'Montserrat', 1.0);
                $txt($pr['total'] ?? '', $W - 204, $y + 11, 148, 24, true, $WH, 'Montserrat', 1.0);
                $y += 72;

                // Payment note
                if (! empty($pr['note'])) {
                    $txt($pr['note'], 50, $y, $W - 100, 11, false, $MT, 'Inter', 1.5);
                    $y += 40;
                }

                // What's Included section
                $rect(50, $y, $W - 100, 1, '#e5e7eb');
                $y += 18;
                $txt("What's Included", 50, $y, $W - 100, 16, true, $PD, 'Montserrat', 1.2);
                $y += 30;
                $incColors = [$P, '#059669', '#f59e0b', '#ec4899', '#0284c7', '#8b5cf6'];
                foreach (array_slice($services, 0, 6) as $si => $svc) {
                    $sx = $si % 2 === 0 ? 50 : 420;
                    $sy = $y + (int) ($si / 2) * 40;
                    $ic = $incColors[$si % count($incColors)];
                    $rect($sx, $sy + 5, 18, 18, $ic);
                    $txt($svc['name'] ?? '', $sx + 26, $sy + 7, 325, 12, false, $TX, 'Inter', 1.25);
                }
                $y += (int) ceil(count(array_slice($services, 0, 6)) / 2) * 40 + 14;

                // Value panel — fills to footer
                $panelTop5 = max($y + 10, $H - 145);
                $rect(50, $panelTop5, $W - 100, 100, $PDK);
                $txt('A smart investment in your business future.', 70, $panelTop5 + 18, $W - 140, 15, true, $WH, 'Montserrat', 1.2);
                $txt('Flexible payment options available. All prices are exclusive of applicable taxes.', 70, $panelTop5 + 48, $W - 140, 11, false, 'rgba(255,255,255,0.65)', 'Inter', 1.55);
                break;

            // ─── CONTACT & NEXT STEPS ────────────────────────────────────────
            case 6:
                $header('Contact & Next Steps');
                $footer(6);
                $y = 128;

                // Intro text
                if (! empty($ct['intro'])) {
                    $txt($ct['intro'], 50, $y, $W - 100, 13, false, $TX, 'Inter', 1.65);
                    $lc2 = max(1, (int) ceil(mb_strlen($ct['intro']) / 78));
                    $y  += $lc2 * 22 + 22;
                }

                // Steps
                $txt('Next Steps', 50, $y, $W - 100, 16, true, $PD, 'Montserrat', 1.2);
                $y += 30;
                foreach ((array) ($ct['steps'] ?? []) as $si => $step) {
                    $rect(50, $y, 28, 28, $P);
                    $txt((string) ($si + 1), 59, $y + 7, 16, 12, true, $WH, 'Montserrat', 1.0);
                    $txt((string) $step, 88, $y + 8, $W - 158, 13, false, $TX, 'Inter', 1.45);
                    $y += 50;
                }
                $y += 14;

                // "Ready to start" CTA panel
                $rect(50, $y, $W - 100, 115, $P);
                $txt('Ready to Get Started?', 70, $y + 18, $W - 140, 20, true, $WH, 'Montserrat', 1.2);
                $txt('Contact us today to discuss this proposal and take the first step toward your project.', 70, $y + 50, $W - 140, 12, false, 'rgba(255,255,255,0.85)', 'Inter', 1.55);
                $txt('We look forward to building something great together.', 70, $y + 80, $W - 140, 12, false, 'rgba(255,255,255,0.72)', 'Inter', 1.4);
                $y += 134;

                // Contact information box
                $rect(50, $y, $W - 100, 140, $PL);
                $rect(50, $y, 4, 140, $P);
                $txt('Contact Information', 70, $y + 14, $W - 130, 13, true, $PD, 'Montserrat', 1.2);
                $txt('Email', 70, $y + 38, 80, 10, true, $P, 'Inter', 1.0);
                $txt('hello@yourcompany.com', 70, $y + 53, 290, 12, false, $TX, 'Inter', 1.0);
                $txt('Phone', 70, $y + 76, 80, 10, true, $P, 'Inter', 1.0);
                $txt('+1 (555) 000-0000', 70, $y + 91, 290, 12, false, $TX, 'Inter', 1.0);
                $txt('Website', 430, $y + 38, 80, 10, true, $P, 'Inter', 1.0);
                $txt('www.yourcompany.com', 430, $y + 53, 280, 12, false, $TX, 'Inter', 1.0);
                $txt('Location', 430, $y + 76, 80, 10, true, $P, 'Inter', 1.0);
                $txt('Your City, Country', 430, $y + 91, 280, 12, false, $TX, 'Inter', 1.0);
                $y += 158;

                // Thank you — fills remaining space to footer
                $panelTop6 = max($y + 10, $H - 155);
                $rect(0, $panelTop6, $W, 110, $PDK);
                $txt('Thank You for Considering This Proposal', 50, $panelTop6 + 20, $W - 100, 17, true, $WH, 'Montserrat', 1.2);
                $tagText6 = ! empty($ai['tagline']) ? $ai['tagline'] : 'We are excited to bring your vision to life.';
                $txt($tagText6, 50, $panelTop6 + 54, $W - 100, 12, false, 'rgba(255,255,255,0.65)', 'Inter', 1.45);
                break;
        }

        return (string) json_encode(
            ['version' => '5.3.0', 'objects' => $objects, 'background' => $background],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    public function destroyProposal(Request $request, string $group): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'design_all');

        Design::where('business_id', $business->id)
            ->where('proposal_group', $group)
            ->delete();

        return response()->json(['message' => 'Proposal deleted.']);
    }

    public function linkProposalToInvoice(Request $request, string $group): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'design_all');

        $validated = $request->validate([
            'invoice_id' => ['nullable', 'integer'],
        ]);

        $invoiceId = $validated['invoice_id'] ?? null;

        // Verify the invoice belongs to this business if provided
        if ($invoiceId !== null) {
            $exists = \Modules\Sales\Models\Invoice::where('id', $invoiceId)
                ->where('business_id', $business->id)
                ->exists();
            abort_unless($exists, 422, 'Invoice not found.');
        }

        Design::where('business_id', $business->id)
            ->where('proposal_group', $group)
            ->update(['invoice_id' => $invoiceId]);

        return response()->json(['message' => 'Proposal linked to invoice.']);
    }

    private function format(Design $d): array
    {
        return [
            'id'             => $d->id,
            'title'          => $d->title,
            'type'           => $d->type,
            'width'          => $d->width,
            'height'         => $d->height,
            'has_canvas'     => ! empty($d->canvas_json),
            'proposal_group' => $d->proposal_group,
            'proposal_sort'  => $d->proposal_sort,
            'invoice_id'     => $d->invoice_id   ? (int) $d->invoice_id   : null,
            'quotation_id'   => $d->quotation_id  ? (int) $d->quotation_id : null,
            'created_at'     => $d->created_at?->format('Y-m-d'),
            'updated_at'     => $d->updated_at?->format('Y-m-d H:i'),
        ];
    }

    private function formatFull(Design $d): array
    {
        return [
            'id'             => $d->id,
            'title'          => $d->title,
            'type'           => $d->type,
            'width'          => $d->width,
            'height'         => $d->height,
            'canvas_json'    => $d->canvas_json,
            'has_canvas'     => ! empty($d->canvas_json),
            'proposal_group' => $d->proposal_group,
            'proposal_sort'  => $d->proposal_sort,
            'invoice_id'     => $d->invoice_id   ? (int) $d->invoice_id   : null,
            'quotation_id'   => $d->quotation_id  ? (int) $d->quotation_id : null,
            'created_at'     => $d->created_at?->format('Y-m-d'),
            'updated_at'     => $d->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
