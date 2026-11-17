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
            ->with('leavePolicy:id,name')
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
            'leave_policy_id' => $this->leavePolicyRule($business->id),
        ]);

        $jobTitle = $this->service->create($business, $validated['name'], $validated['leave_policy_id'] ?? null);
        $jobTitle->load('leavePolicy:id,name')->loadCount('employees');

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
            'leave_policy_id' => $this->leavePolicyRule($business->id),
        ]);

        $jobTitle = $this->service->update($jobTitle, $validated['name'], $validated['leave_policy_id'] ?? null);
        $jobTitle->load('leavePolicy:id,name')->loadCount('employees');

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

    /** Optional; must be one of this business's leave rules. Null = default rule applies. */
    private function leavePolicyRule(int $businessId): array
    {
        return [
            'nullable', 'integer',
            Rule::exists('hr_leave_policies', 'id')->where(fn ($q) => $q->where('business_id', $businessId)),
        ];
    }

    private function format(JobTitle $jt): array
    {
        return [
            'id'                => $jt->id,
            'name'              => $jt->name,
            'employees_count'   => (int) ($jt->employees_count ?? 0),
            'leave_policy_id'   => $jt->leave_policy_id,
            'leave_policy_name' => $jt->leavePolicy?->name,
        ];
    }
}
