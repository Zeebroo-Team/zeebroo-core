<?php

namespace Modules\AdvertisingAgency\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AdvertisingAgency\Http\Controllers\Api\Concerns\ResolvesBusinessForApi;
use Modules\AdvertisingAgency\Models\PromoterPosition;
use Modules\AdvertisingAgency\Services\PromoterPositionService;

class PromoterPositionApiController extends Controller
{
    use ResolvesBusinessForApi;

    public function __construct(private readonly PromoterPositionService $service) {}

    /** GET /brand-mgmt/promoter-positions */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        return response()->json(['data' => $this->service->list($business, $request->query('q') ?: null)]);
    }

    /** POST /brand-mgmt/promoter-positions */
    public function store(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $position = $this->service->create($business, $validated);
        return response()->json(['data' => [
            'id'          => $position->id,
            'name'        => $position->name,
            'description' => $position->description,
            'created_at'  => $position->created_at,
        ]], 201);
    }

    /** PUT /brand-mgmt/promoter-positions/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $position  = PromoterPosition::where('business_id', $business->id)->findOrFail($id);
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $position = $this->service->update($position, $validated);
        return response()->json(['data' => [
            'id'          => $position->id,
            'name'        => $position->name,
            'description' => $position->description,
            'created_at'  => $position->created_at,
        ]]);
    }

    /** DELETE /brand-mgmt/promoter-positions/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $position = PromoterPosition::where('business_id', $business->id)->findOrFail($id);
        $this->service->delete($position);
        return response()->json(['message' => 'Position deleted.']);
    }
}
