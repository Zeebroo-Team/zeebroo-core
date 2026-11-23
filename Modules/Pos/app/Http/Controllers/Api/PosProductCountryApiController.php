<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;
use Modules\Product\Models\ProductCountry;
use Modules\Product\Services\ProductCountryService;

/**
 * Countries a business picks from for a product's Country of Origin and
 * Country of Manufacture. Inactive countries stay on the products that
 * already use them but are not offered for new picks.
 */
class PosProductCountryApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly ProductCountryService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $countries = $this->service->list(
            $business,
            trim((string) $request->query('q', '')),
            (string) $request->query('status', ''),
        );

        return response()->json([
            'data' => $countries->map(fn ($c) => $this->format($c))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:120', Rule::unique('product_countries', 'name')->where('business_id', $business->id)],
            'code'      => 'nullable|string|max:3',
            'is_active' => 'boolean',
        ], [
            'name.unique' => 'This country is already in your list.',
        ]);

        $country = $this->service->create($business, $data);

        return response()->json([
            'message' => 'Country created.',
            'data'    => $this->format($country),
        ], 201);
    }

    public function update(Request $request, ProductCountry $country): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->countryForBusiness($business, $country)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'name'      => ['sometimes', 'required', 'string', 'max:120', Rule::unique('product_countries', 'name')->where('business_id', $business->id)->ignore($country->id)],
            'code'      => 'nullable|string|max:3',
            'is_active' => 'boolean',
        ], [
            'name.unique' => 'This country is already in your list.',
        ]);

        $country = $this->service->update($country, $data);

        return response()->json([
            'message' => 'Country updated.',
            'data'    => $this->format($country),
        ]);
    }

    public function destroy(Request $request, ProductCountry $country): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! $this->service->countryForBusiness($business, $country)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $count = $this->service->usageCount($country);
        if ($count > 0) {
            return response()->json([
                'message' => "Cannot delete a country used by {$count} product(s). Mark it inactive instead.",
            ], 422);
        }

        $this->service->delete($country);

        return response()->json(['message' => 'Country deleted.']);
    }

    private function format(ProductCountry $c): array
    {
        return [
            'id'             => $c->id,
            'name'           => $c->name,
            'code'           => $c->code,
            'is_active'      => (bool) $c->is_active,
            'products_count' => (int) ($c->products_count ?? 0),
        ];
    }
}
