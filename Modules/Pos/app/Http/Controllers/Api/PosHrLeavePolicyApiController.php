<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\LeavePolicy;
use Modules\HRManagement\Services\LeavePolicyService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosHrLeavePolicyApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly LeavePolicyService $service) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_leave_policies')) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $this->service->list($business)->map(fn (LeavePolicy $p) => $this->format($p))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if (! Schema::hasTable('hr_leave_policies')) {
            return response()->json(['message' => 'HR module is not set up yet.'], 422);
        }

        $policy = $this->service->create($business, $this->validated($request, $business));

        return response()->json(['message' => 'Leave rule created.', 'data' => $this->format($policy)], 201);
    }

    public function update(Request $request, LeavePolicy $leavePolicy): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $leavePolicy->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $policy = $this->service->update($leavePolicy, $this->validated($request, $business, $leavePolicy));

        return response()->json(['message' => 'Leave rule updated.', 'data' => $this->format($policy)]);
    }

    public function destroy(Request $request, LeavePolicy $leavePolicy): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $leavePolicy->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $this->service->delete($leavePolicy);

        return response()->json(['message' => 'Leave rule deleted.']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Business $business, ?LeavePolicy $ignore = null): array
    {
        $unique = Rule::unique('hr_leave_policies', 'name')->where(fn ($q) => $q->where('business_id', $business->id));
        if ($ignore) {
            $unique->ignore($ignore->id);
        }

        return $request->validate([
            'name'                    => ['required', 'string', 'max:100', $unique],
            'annual_leave_days'       => ['required', 'integer', 'min:0', 'max:365'],
            'casual_leave_days'       => ['required', 'integer', 'min:0', 'max:365'],
            'sick_leave_days'         => ['nullable', 'integer', 'min:0', 'max:365'],
            'short_leave_enabled'     => ['boolean'],
            'short_leaves_per_month'  => ['exclude_unless:short_leave_enabled,true', 'required', 'integer', 'min:1', 'max:31'],
            'short_leave_max_minutes' => ['exclude_unless:short_leave_enabled,true', 'required', 'integer', 'min:15', 'max:480'],
            'half_day_allowed'        => ['boolean'],
            'carry_forward_max_days'  => ['nullable', 'integer', 'min:0', 'max:365'],
            'eligible_after_months'   => ['nullable', 'integer', 'min:0', 'max:24'],
            'is_default'              => ['boolean'],
            'notes'                   => ['nullable', 'string', 'max:1000'],
        ], [
            'name.unique'                      => 'A leave rule with this name already exists.',
            'short_leaves_per_month.required'  => 'Enter how many short leaves are allowed per month.',
            'short_leave_max_minutes.required' => 'Enter the maximum length of a short leave.',
        ]);
    }

    private function format(LeavePolicy $p): array
    {
        return [
            'id'                      => $p->id,
            'name'                    => $p->name,
            'annual_leave_days'       => $p->annual_leave_days,
            'casual_leave_days'       => $p->casual_leave_days,
            'sick_leave_days'         => $p->sick_leave_days,
            'short_leave_enabled'     => $p->short_leave_enabled,
            'short_leaves_per_month'  => $p->short_leaves_per_month,
            'short_leave_max_minutes' => $p->short_leave_max_minutes,
            'half_day_allowed'        => $p->half_day_allowed,
            'carry_forward_max_days'  => $p->carry_forward_max_days,
            'eligible_after_months'   => $p->eligible_after_months,
            'is_default'              => $p->is_default,
            'notes'                   => $p->notes,
        ];
    }
}
