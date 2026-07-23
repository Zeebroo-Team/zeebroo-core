<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Pos\Models\PosCounter;

class PosCounterApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function index(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $branchId  = $request->query('branch_id');

        $query = PosCounter::where('business_id', $business->id)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', (int) $branchId);
            });
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'branch_id'  => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $counter = PosCounter::create([
            'business_id' => $business->id,
            'branch_id'   => isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
            'name'        => trim($validated['name']),
            'sort_order'  => (int) ($validated['sort_order'] ?? 0),
            'is_active'   => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json(['message' => 'Counter created.', 'data' => $counter], 201);
    }

    public function update(Request $request, PosCounter $counter): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_if($counter->business_id !== $business->id, 403);

        $validated = $request->validate([
            'name'       => ['sometimes', 'string', 'max:100'],
            'branch_id'  => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        if (isset($validated['name'])) {
            $counter->name = trim($validated['name']);
        }
        if (array_key_exists('branch_id', $validated)) {
            $counter->branch_id = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        }
        if (isset($validated['sort_order'])) {
            $counter->sort_order = (int) $validated['sort_order'];
        }
        if (array_key_exists('is_active', $validated)) {
            $counter->is_active = (bool) $validated['is_active'];
        }
        $counter->save();

        return response()->json(['message' => 'Counter updated.', 'data' => $counter]);
    }

    public function destroy(Request $request, PosCounter $counter): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        abort_if($counter->business_id !== $business->id, 403);

        $counter->delete();

        return response()->json(['message' => 'Counter deleted.']);
    }
}
