<?php

namespace Modules\AdvertisingAgency\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AdvertisingAgency\Http\Controllers\Api\Concerns\ResolvesBusinessForApi;
use Modules\AdvertisingAgency\Models\Brand;
use Modules\AdvertisingAgency\Services\BrandService;

class BrandApiController extends Controller
{
    use ResolvesBusinessForApi;

    public function __construct(
        private readonly BrandService $brandService,
    ) {}

    /** GET /brand-mgmt/brands */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $brands   = $this->brandService->list($business, $request->query('q') ?: null);
        return response()->json(['data' => $brands]);
    }

    /** POST /brand-mgmt/brands */
    public function store(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:150'],
            'short_code'     => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'email'          => ['required', 'email', 'max:150'],
            'phone'          => ['nullable', 'string', 'max:40'],
            'company_name'   => ['nullable', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'address'        => ['nullable', 'string', 'max:500'],
        ]);

        $exists = Brand::query()
            ->where('business_id', $business->id)
            ->where('short_code', strtoupper($validated['short_code']))
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Short code ' . strtoupper($validated['short_code']) . ' is already in use by another brand.',
            ], 422);
        }

        $brand = $this->brandService->create($business, $validated);
        return response()->json(['data' => $brand], 201);
    }

    /** PUT /brand-mgmt/brands/{brand} */
    public function update(Request $request, int $id): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $brand     = Brand::where('business_id', $business->id)->findOrFail($id);
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:150'],
            'short_code'     => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'email'          => ['required', 'email', 'max:150'],
            'phone'          => ['nullable', 'string', 'max:40'],
            'company_name'   => ['nullable', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'address'        => ['nullable', 'string', 'max:500'],
        ]);

        $conflict = Brand::query()
            ->where('business_id', $business->id)
            ->where('short_code', strtoupper($validated['short_code']))
            ->where('id', '!=', $brand->id)
            ->exists();

        if ($conflict) {
            return response()->json([
                'message' => 'Short code ' . strtoupper($validated['short_code']) . ' is already in use by another brand.',
            ], 422);
        }

        $brand = $this->brandService->update($brand, $validated);
        return response()->json(['data' => $brand]);
    }

    /** POST /brand-mgmt/brands/import */
    public function import(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $validated = $request->validate([
            'rows'                  => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.name'           => ['required', 'string', 'max:150'],
            'rows.*.short_code'     => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'rows.*.email'          => ['required', 'email', 'max:150'],
            'rows.*.phone'          => ['nullable', 'string', 'max:40'],
            'rows.*.company_name'   => ['nullable', 'string', 'max:150'],
            'rows.*.contact_person' => ['nullable', 'string', 'max:150'],
            'rows.*.address'        => ['nullable', 'string', 'max:500'],
        ]);

        $results = $this->brandService->import($business, $validated['rows']);
        return response()->json($results);
    }

    /** DELETE /brand-mgmt/brands/{brand} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $brand    = Brand::where('business_id', $business->id)->findOrFail($id);
        $this->brandService->delete($brand);
        return response()->json(['message' => 'Brand deleted.']);
    }
}
