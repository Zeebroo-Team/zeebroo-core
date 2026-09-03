<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\HRManagement\Models\JobTitle;
use Modules\HRManagement\Services\JobTitleService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosHrJobTitleListApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly JobTitleService $service) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_job_titles')) {
            return response()->json(['data' => []]);
        }

        $jobTitles = JobTitle::where('business_id', $business->id)
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $jobTitles->map(fn (JobTitle $jt) => $this->format($jt))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_job_titles')) {
            return response()->json(['message' => 'HR module is not set up yet.'], 422);
        }

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('hr_job_titles', 'name')
                    ->where(fn ($q) => $q->where('business_id', $business->id)),
            ],
        ]);

        $jobTitle = $this->service->create($business, $validated['name']);
        $jobTitle->loadCount('employees');

        return response()->json(['data' => $this->format($jobTitle)], 201);
    }

    public function update(Request $request, JobTitle $jobTitle): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $jobTitle->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('hr_job_titles', 'name')
                    ->where(fn ($q) => $q->where('business_id', $business->id))
                    ->ignore($jobTitle->id),
            ],
        ]);

        $jobTitle = $this->service->rename($jobTitle, $validated['name']);
        $jobTitle->loadCount('employees');

        return response()->json(['data' => $this->format($jobTitle)]);
    }

    public function destroy(Request $request, JobTitle $jobTitle): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $jobTitle->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($jobTitle->employees()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a position that is still assigned to employees.',
            ], 422);
        }

        $this->service->delete($jobTitle);

        return response()->json(['message' => 'Position deleted.']);
    }

    private function format(JobTitle $jt): array
    {
        return [
            'id'              => $jt->id,
            'name'            => $jt->name,
            'employees_count' => (int) ($jt->employees_count ?? 0),
        ];
    }
}
