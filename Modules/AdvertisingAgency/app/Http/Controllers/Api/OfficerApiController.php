<?php

namespace Modules\AdvertisingAgency\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AdvertisingAgency\Http\Controllers\Api\Concerns\ResolvesBusinessForApi;
use Modules\AdvertisingAgency\Models\Officer;
use Modules\AdvertisingAgency\Services\OfficerService;

class OfficerApiController extends Controller
{
    use ResolvesBusinessForApi;

    public function __construct(
        private readonly OfficerService $officerService,
    ) {}

    /** GET /brand-mgmt/officers */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $officers = $this->officerService->list($business, $request->query('q') ?: null);
        return response()->json(['data' => $officers]);
    }

    /** POST /brand-mgmt/officers */
    public function store(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        if (Officer::where('business_id', $business->id)->where('email', $validated['email'])->exists()) {
            return response()->json(['message' => 'An officer with this email already exists.'], 422);
        }

        return response()->json(['data' => $this->officerService->create($business, $validated)], 201);
    }

    /** PUT /brand-mgmt/officers/{officer} */
    public function update(Request $request, int $id): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $officer   = Officer::where('business_id', $business->id)->findOrFail($id);
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:150'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:100'],
        ]);

        if (Officer::where('business_id', $business->id)->where('email', $validated['email'])->where('id', '!=', $officer->id)->exists()) {
            return response()->json(['message' => 'Another officer is already using this email address.'], 422);
        }

        return response()->json(['data' => $this->officerService->update($officer, $validated)]);
    }

    /** DELETE /brand-mgmt/officers/{officer} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $officer  = Officer::where('business_id', $business->id)->findOrFail($id);
        $this->officerService->delete($officer);
        return response()->json(['message' => 'Officer deleted.']);
    }
}
