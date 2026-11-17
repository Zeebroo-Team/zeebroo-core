<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\LeavePolicy;
use Modules\HRManagement\Services\EmployeeLeaveBalanceService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

/** Employee detail → Leave tab: the leave rule that applies, this year's balances and the leave history. */
class PosHrEmployeeLeaveApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly EmployeeLeaveBalanceService $service) {}

    public function show(Request $request, Employee $employee): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $employee->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Employee not found.'], 404);
        }

        $request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']]);
        $year = $request->integer('year') ?: null;

        $employee->load('jobTitle.leavePolicy');
        $summary      = $this->service->yearlySummary($business, $employee, $year);
        $eligibleFrom = $this->service->eligibleFrom($employee, $summary['policy']);

        $balances = [];
        foreach ($summary['types'] as $type => $b) {
            $balances[] = ['type' => $type] + $b;
        }

        return response()->json(['data' => [
            'year'          => $summary['year'],
            'policy'        => $summary['policy'] ? $this->formatPolicy($summary['policy']) : null,
            'policy_source' => $summary['policy_source'],
            'position'      => $employee->jobTitle?->name,
            'eligible_from' => $eligibleFrom?->toDateString(),
            'eligible_now'  => $eligibleFrom === null || $eligibleFrom->lte(now()),
            'balances'      => $balances,
            'short_leave'   => $this->service->shortLeaveUsage($employee, $summary['policy'], CarbonImmutable::now()),
            'history'       => $this->service->history($business, $employee),
        ]]);
    }

    private function formatPolicy(LeavePolicy $p): array
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
        ];
    }
}
