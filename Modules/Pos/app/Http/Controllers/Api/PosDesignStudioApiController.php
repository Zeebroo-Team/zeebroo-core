<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\DesignStudio\Models\Design;
use Modules\DesignStudio\Services\ProposalService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosDesignStudioApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly ProposalService $proposals)
    {
    }

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
    // Business logic lives in Modules\DesignStudio\Services\ProposalService,
    // shared with the session-auth web UI (ProposalController).

    public function proposals(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('design_studio_designs')) {
            return response()->json(['data' => []]);
        }

        $grouped = $this->proposals->listGrouped($business)->map(fn (array $g) => [
            ...$g,
            'pages' => collect($g['pages'])->map(fn (Design $d) => $this->format($d))->values(),
        ]);

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

        $result = $this->proposals->create($business, $request->user()?->id, $validated['title'], $validated['client'] ?? null);

        return response()->json([
            'data' => [
                'group'  => $result['group'],
                'title'  => $result['title'],
                'pages'  => $result['pages']->map(fn (Design $d) => $this->format($d))->values(),
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

        $invoiceId   = $request->input('invoice_id')   ? (int) $request->input('invoice_id')   : null;
        $quotationId = $request->input('quotation_id') ? (int) $request->input('quotation_id') : null;

        $design = $this->proposals->addPage($business, $request->user()?->id, $group, $invoiceId, $quotationId);

        if (! $design) {
            return response()->json(['message' => 'Proposal not found.'], 404);
        }

        return response()->json(['data' => $this->format($design)], 201);
    }

    public function proposalPages(Request $request, string $group): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $result = $this->proposals->getGroupPages($business, $group);

        if ($result === null) {
            return response()->json(['message' => 'Proposal not found.'], 404);
        }

        return response()->json([
            'data' => [
                'group'  => $result['group'],
                'title'  => $result['title'],
                'client' => $result['client'],
                'pages'  => $result['pages']->map(fn ($d) => $this->formatFull($d))->values(),
            ],
        ]);
    }

    public function aiProposalContent(Request $request): JsonResponse
    {
        $this->businessOrAbort($request);

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:120'],
            'client'      => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1500'],
        ]);

        $result = $this->proposals->generateContent($validated['title'], $validated['client'] ?? null, $validated['description']);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['content' => $result['content']]);
    }

    public function aiProposalFill(Request $request, string $group): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'design_all');

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:120'],
            'client'      => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1500'],
        ]);

        $result = $this->proposals->fillWithAi($business, $group, $validated['title'], $validated['client'] ?? null, $validated['description']);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], $result['message'] === 'Proposal not found.' ? 404 : 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data'    => [
                'group' => $group,
                'pages' => $result['pages']->map(fn (Design $d) => $this->format($d))->values(),
            ],
        ]);
    }

    public function destroyProposal(Request $request, string $group): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'design_all');

        $this->proposals->destroy($business, $group);

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

        if ($invoiceId !== null) {
            abort_unless($this->proposals->invoiceBelongsToBusiness($business, $invoiceId), 422, 'Invoice not found.');
        }

        $this->proposals->linkInvoice($business, $group, $invoiceId);

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
