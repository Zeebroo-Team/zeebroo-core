<?php

namespace Modules\AdvertisingAgency\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AdvertisingAgency\Http\Controllers\Api\Concerns\ResolvesBusinessForApi;
use Modules\AdvertisingAgency\Models\Job;
use Modules\AdvertisingAgency\Models\Reporter;
use Modules\AdvertisingAgency\Services\JobService;
use Modules\Business\Models\BusinessMember;

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

        // If the authenticated user is a business member with the 'reporter' role,
        // restrict the list to jobs assigned to their linked reporter record only.
        $reporterId = null;
        $member = BusinessMember::where('business_id', $business->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();
        if ($member && $member->role === 'reporter') {
            $reporter   = Reporter::where('business_id', $business->id)
                ->where('user_id', $request->user()->id)
                ->first();
            $reporterId = $reporter?->id;
        }

        $data = $this->jobService->list(
            $business,
            $request->query('q')      ?: null,
            $request->query('status') ?: null,
            $reporterId,
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

    /** POST /brand-mgmt/jobs/import */
    public function import(Request $request): JsonResponse
    {
        $business  = $this->businessOrAbort($request);
        $validated = $request->validate([
            'rows'                  => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.name'           => ['required', 'string', 'max:200'],
            'rows.*.brand_code'     => ['nullable', 'string', 'max:10'],
            'rows.*.status'         => ['nullable', 'string', 'in:pending,in_progress,completed,cancelled'],
            'rows.*.start_date'     => ['nullable', 'date'],
            'rows.*.description'    => ['nullable', 'string', 'max:2000'],
            'rows.*.officer'        => ['nullable', 'string', 'max:200'],
            'rows.*.reporter'       => ['nullable', 'string', 'max:200'],
        ]);

        $results = $this->jobService->import($business, $validated['rows']);
        return response()->json($results);
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
