<?php

namespace Modules\AdvertisingAgency\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AdvertisingAgency\Http\Controllers\Api\Concerns\ResolvesBusinessForApi;
use Modules\AdvertisingAgency\Models\Job;
use Modules\AdvertisingAgency\Services\JobService;

class JobApiController extends Controller
{
    use ResolvesBusinessForApi;

    public function __construct(
        private readonly JobService $jobService,
    ) {}

    /** GET /brand-mgmt/jobs */
    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $data     = $this->jobService->list(
            $business,
            $request->query('q')      ?: null,
            $request->query('status') ?: null,
        );
        return response()->json(['data' => $data]);
    }

    /** POST /brand-mgmt/jobs */
    public function store(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:200'],
            'client_brand_id' => ['nullable', 'integer', 'exists:bm_brands,id'],
            'officer_id'      => ['nullable', 'integer'],
            'reporter_id'     => ['nullable', 'integer'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'status'          => ['required', 'string', 'in:pending,in_progress,completed,cancelled'],
            'start_date'      => ['nullable', 'date'],
        ]);

        $job = $this->jobService->create($business, $validated);
        $job->load(['clientBrand:id,name,short_code', 'officer:id,name', 'reporter:id,name']);

        return response()->json(['data' => $job], 201);
    }

    /** PUT /brand-mgmt/jobs/{job} */
    public function update(Request $request, int $id): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $job       = Job::where('business_id', $business->id)->findOrFail($id);
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:200'],
            'client_brand_id' => ['nullable', 'integer', 'exists:bm_brands,id'],
            'officer_id'      => ['nullable', 'integer'],
            'reporter_id'     => ['nullable', 'integer'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'status'          => ['required', 'string', 'in:pending,in_progress,completed,cancelled'],
            'start_date'      => ['nullable', 'date'],
        ]);

        return response()->json(['data' => $this->jobService->update($job, $validated)]);
    }

    /** DELETE /brand-mgmt/jobs/{job} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $job      = Job::where('business_id', $business->id)->findOrFail($id);
        $this->jobService->delete($job);
        return response()->json(['message' => 'Job deleted.']);
    }
}
