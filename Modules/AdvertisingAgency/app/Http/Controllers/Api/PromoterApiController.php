<?php

namespace Modules\AdvertisingAgency\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AdvertisingAgency\Http\Controllers\Api\Concerns\ResolvesBusinessForApi;
use Modules\AdvertisingAgency\Models\Promoter;
use Modules\AdvertisingAgency\Services\PromoterService;

class PromoterApiController extends Controller
{
    use ResolvesBusinessForApi;

    public function __construct(
        private readonly PromoterService $promoterService,
    ) {}

    /** GET /brand-mgmt/promoters */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $data     = $this->promoterService->list($business, $request->query('q') ?: null);
        return response()->json(['data' => $data]);
    }

    /** POST /brand-mgmt/promoters */
    public function store(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'position'     => ['nullable', 'string', 'max:100'],
            'nic'          => ['nullable', 'string', 'max:50'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'bank_name'    => ['required', 'string', 'max:100'],
            'bank_branch'  => ['required', 'string', 'max:100'],
            'bank_account' => ['required', 'string', 'max:50'],
        ]);

        $promoter = $this->promoterService->create($business, $validated);
        return response()->json(['data' => $promoter], 201);
    }

    /** PUT /brand-mgmt/promoters/{promoter} */
    public function update(Request $request, int $id): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $promoter  = Promoter::where('business_id', $business->id)->findOrFail($id);
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'position'     => ['nullable', 'string', 'max:100'],
            'nic'          => ['nullable', 'string', 'max:50'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'bank_name'    => ['required', 'string', 'max:100'],
            'bank_branch'  => ['required', 'string', 'max:100'],
            'bank_account' => ['required', 'string', 'max:50'],
        ]);

        return response()->json(['data' => $this->promoterService->update($promoter, $validated)]);
    }

    /** DELETE /brand-mgmt/promoters/{promoter} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $promoter = Promoter::where('business_id', $business->id)->findOrFail($id);
        $this->promoterService->delete($promoter);
        return response()->json(['message' => 'Promoter deleted.']);
    }
}
