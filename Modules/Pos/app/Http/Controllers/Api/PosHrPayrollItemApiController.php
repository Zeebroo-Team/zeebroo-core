<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HRManagement\Models\PayrollCycle;
use Modules\HRManagement\Models\PayrollItem;
use Modules\HRManagement\Services\PayrollComputationService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

class PosHrPayrollItemApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly PayrollComputationService $computationService) {}

    public function recompute(Request $request, PayrollCycle $cycle, PayrollItem $item): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        if ((int) $cycle->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Cycle not found.'], 404);
        }

        if ((int) $item->payroll_cycle_id !== (int) $cycle->id) {
            return response()->json(['message' => 'Item does not belong to this cycle.'], 404);
        }

        if ($cycle->isFinalized()) {
            return response()->json(['message' => 'Cannot recompute a finalized cycle.'], 422);
        }

        $validated = $request->validate([
            'overtime_hours'         => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'overtime_rate'          => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'attendance_days'        => ['nullable', 'numeric', 'min:0', 'max:31'],
            'working_days'           => ['nullable', 'numeric', 'min:0', 'max:31'],
            'leave_without_pay_days' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'salary_advance'         => ['nullable', 'numeric', 'min:0', 'max:99999999999.99'],
            'stamp_duty'             => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $inputs = array_filter($validated, fn ($v) => $v !== null);

        $item->load('employee');
        $employee = $item->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee not found on this item.'], 422);
        }

        $result = $this->computationService->computeEmployee($cycle, $employee, $inputs);

        $updatedItem = $result['item'];
        $updatedItem->load(['employee', 'components']);

        return response()->json([
            'data'   => $this->formatItem($updatedItem),
            'errors' => $result['errors'],
        ]);
    }

    private function formatItem(PayrollItem $i): array
    {
        return [
            'id'                   => $i->id,
            'employee_id'          => $i->employee_id,
            'employee_name'        => $i->employee?->full_name,
            'employee_code'        => $i->employee?->employee_id,
            'status'               => $i->status,
            'basic_salary'         => (float) $i->basic_salary,
            'basic_salary_fmt'     => number_format((float) $i->basic_salary, 2, '.', ','),
            'overtime_amount'      => (float) $i->overtime_amount,
            'overtime_amount_fmt'  => number_format((float) $i->overtime_amount, 2, '.', ','),
            'gross_earnings'       => (float) $i->gross_earnings,
            'gross_earnings_fmt'   => number_format((float) $i->gross_earnings, 2, '.', ','),
            'total_deductions'     => (float) $i->total_deductions,
            'total_deductions_fmt' => number_format((float) $i->total_deductions, 2, '.', ','),
            'net_pay'              => (float) $i->net_pay,
            'net_pay_fmt'          => number_format((float) $i->net_pay, 2, '.', ','),
            'inputs_json'          => $i->inputs_json ?? [],
            'has_errors'           => $i->status === 'error',
            'errors'               => $i->snapshot_json['errors'] ?? [],
        ];
    }
}
