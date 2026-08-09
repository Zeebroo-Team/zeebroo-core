<?php

namespace Modules\AdvertisingAgency\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AdvertisingAgency\Http\Controllers\Api\Concerns\ResolvesBusinessForApi;
use Modules\AdvertisingAgency\Models\Coordinator;
use Modules\AdvertisingAgency\Services\CoordinatorService;

class CoordinatorApiController extends Controller
{
    use ResolvesBusinessForApi;

    public function __construct(
        private readonly CoordinatorService $coordinatorService,
    ) {}

    /** GET /brand-mgmt/coordinators */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $data     = $this->coordinatorService->list($business, $request->query('q') ?: null);
        return response()->json(['data' => $data]);
    }

    /** POST /brand-mgmt/coordinators */
    public function store(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'nic'          => ['required', 'string', 'max:50'],
            'phone'        => ['required', 'string', 'max:30'],
            'status'       => ['required', 'string', 'in:active,inactive'],
            'bank_name'    => ['required', 'string', 'max:100'],
            'bank_branch'  => ['required', 'string', 'max:100'],
            'bank_account' => ['required', 'string', 'max:50'],
        ]);

        $coordinator = $this->coordinatorService->create($business, $validated);
        return response()->json(['data' => $coordinator], 201);
    }

    /** PUT /brand-mgmt/coordinators/{coordinator} */
    public function update(Request $request, int $id): JsonResponse
    {
        $business    = $this->businessOrAbort($request);
        $coordinator = Coordinator::where('business_id', $business->id)->findOrFail($id);
        $validated   = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'nic'          => ['required', 'string', 'max:50'],
            'phone'        => ['required', 'string', 'max:30'],
            'status'       => ['required', 'string', 'in:active,inactive'],
            'bank_name'    => ['required', 'string', 'max:100'],
            'bank_branch'  => ['required', 'string', 'max:100'],
            'bank_account' => ['required', 'string', 'max:50'],
        ]);

        return response()->json(['data' => $this->coordinatorService->update($coordinator, $validated)]);
    }

    /** DELETE /brand-mgmt/coordinators/{coordinator} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $business    = $this->businessOrAbort($request);
        $coordinator = Coordinator::where('business_id', $business->id)->findOrFail($id);
        $this->coordinatorService->delete($coordinator);
        return response()->json(['message' => 'Coordinator deleted.']);
    }
}
