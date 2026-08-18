<?php

namespace Modules\AdvertisingAgency\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AdvertisingAgency\Http\Controllers\Api\Concerns\ResolvesBusinessForApi;
use Modules\AdvertisingAgency\Models\Reporter;
use Modules\AdvertisingAgency\Services\ReporterService;

class ReporterApiController extends Controller
{
    use ResolvesBusinessForApi;

    public function __construct(
        private readonly ReporterService $reporterService,
    ) {}

    /** GET /brand-mgmt/reporters */
    public function index(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $reporters = $this->reporterService->list($business, $request->query('q') ?: null);
        return response()->json(['data' => $reporters]);
    }

    /** POST /brand-mgmt/reporters */
    public function store(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        if (Reporter::where('business_id', $business->id)->where('email', $validated['email'])->exists()) {
            return response()->json(['message' => 'A reporter with this email already exists.'], 422);
        }

        return response()->json(['data' => $this->reporterService->create($business, $validated)], 201);
    }

    /** PUT /brand-mgmt/reporters/{reporter} */
    public function update(Request $request, int $id): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $reporter  = Reporter::where('business_id', $business->id)->findOrFail($id);
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:150'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:100'],
        ]);

        if (Reporter::where('business_id', $business->id)->where('email', $validated['email'])->where('id', '!=', $reporter->id)->exists()) {
            return response()->json(['message' => 'Another reporter is already using this email address.'], 422);
        }

        return response()->json(['data' => $this->reporterService->update($reporter, $validated)]);
    }

    /** DELETE /brand-mgmt/reporters/{reporter} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $reporter = Reporter::where('business_id', $business->id)->findOrFail($id);
        $this->reporterService->delete($reporter);
        return response()->json(['message' => 'Reporter deleted.']);
    }
}
