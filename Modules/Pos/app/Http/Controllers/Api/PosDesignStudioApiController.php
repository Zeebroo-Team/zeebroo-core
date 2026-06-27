<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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

        if ((int) $design->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Design not found.'], 404);
        }

        $design->delete();

        return response()->json(['message' => 'Design deleted.']);
    }

    private function format(Design $d): array
    {
        return [
            'id'         => $d->id,
            'title'      => $d->title,
            'type'       => $d->type,
            'width'      => $d->width,
            'height'     => $d->height,
            'has_canvas' => ! empty($d->canvas_json),
            'created_at' => $d->created_at?->format('Y-m-d'),
            'updated_at' => $d->updated_at?->format('Y-m-d H:i'),
        ];
    }

    private function formatFull(Design $d): array
    {
        return [
            'id'          => $d->id,
            'title'       => $d->title,
            'type'        => $d->type,
            'width'       => $d->width,
            'height'      => $d->height,
            'canvas_json' => $d->canvas_json,
            'has_canvas'  => ! empty($d->canvas_json),
            'created_at'  => $d->created_at?->format('Y-m-d'),
            'updated_at'  => $d->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
