<?php

namespace Modules\AdvertisingAgency\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AdvertisingAgency\Http\Controllers\Api\Concerns\ResolvesBusinessForApi;
use Modules\AdvertisingAgency\Models\Agency;
use Modules\AdvertisingAgency\Services\AgencyService;

class AgencyApiController extends Controller
{
    use ResolvesBusinessForApi;

    public function __construct(private readonly AgencyService $agencyService) {}

    /** GET /brand-mgmt/agencies */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        return response()->json([
            'data' => $this->agencyService->list($business, $request->query('q') ?: null),
        ]);
    }

    /** POST /brand-mgmt/agencies */
    public function store(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:200'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'email'          => ['nullable', 'email', 'max:150'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'address'        => ['nullable', 'string', 'max:300'],
            'status'         => ['required', 'string', 'in:active,inactive'],
        ]);
        $agency = $this->agencyService->create($business, $validated);
        return response()->json(['data' => $agency], 201);
    }

    /** PUT /brand-mgmt/agencies/{agency} */
    public function update(Request $request, int $id): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $agency    = Agency::where('business_id', $business->id)->findOrFail($id);
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:200'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'email'          => ['nullable', 'email', 'max:150'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'address'        => ['nullable', 'string', 'max:300'],
            'status'         => ['required', 'string', 'in:active,inactive'],
        ]);
        return response()->json(['data' => $this->agencyService->update($agency, $validated)]);
    }

    /** DELETE /brand-mgmt/agencies/{agency} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $agency   = Agency::where('business_id', $business->id)->findOrFail($id);
        $this->agencyService->delete($agency);
        return response()->json(['message' => 'Agency deleted.']);
    }
}
