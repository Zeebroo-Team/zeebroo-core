<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\LeaveRequest;
use Modules\HRManagement\Services\EmployeeLeaveBalanceService;
use Modules\HRManagement\Services\LeaveRequestService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

/** Desktop leave process: list (Attendance → Leave), preview, give leave, approve / reject, delete. */
class PosHrLeaveRequestApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(
        private readonly LeaveRequestService $service,
        private readonly EmployeeLeaveBalanceService $balances,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(LeaveRequest::STATUSES)],
            'q'      => ['nullable', 'string', 'max:100'],
            'from'   => ['nullable', 'date'],
            'to'     => ['nullable', 'date'],
        ]);

        $rows = $this->service->list($business, $filters)->map(fn (LeaveRequest $lr) => $this->balances->formatRequest($lr) + [
            'employee_name' => $lr->employee?->full_name,
            'employee_code' => $lr->employee?->employee_id,
        ]);

        return response()->json(['data' => $rows->values(), 'counts' => $this->service->counts($business)]);
    }

    /** What a request would charge and which rules it breaks — drives the live summary in the Give Leave form. */
    public function preview(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        [$employee, $data] = $this->validatedInput($request, $business);

        $eval = $this->service->evaluate($business, $employee, $data);

        return response()->json(['data' => [
            'days'        => $eval['days'],
            'remaining'   => $eval['remaining'],
            'short_usage' => $eval['short_usage'],
            'errors'      => $eval['errors'],
            'warnings'    => $eval['warnings'],
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'hr_employees');
        [$employee, $data] = $this->validatedInput($request, $business, forStore: true);

        $leave = $this->service->create($business, $employee, $data, $request->user());

        return response()->json([
            'message' => $leave->isPending() ? 'Leave request saved as pending.' : 'Leave recorded.',
            'data'    => $this->balances->formatRequest($leave),
        ], 201);
    }

    public function updateStatus(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'hr_employees');

        if ((int) $leaveRequest->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in([LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_REJECTED])],
        ]);

        $leave = $this->service->decide($leaveRequest, $validated['status'], $request->user());

        return response()->json([
            'message' => $leave->status === LeaveRequest::STATUS_APPROVED ? 'Leave approved.' : 'Leave rejected.',
            'data'    => $this->balances->formatRequest($leave),
        ]);
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'hr_employees');

        if ((int) $leaveRequest->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $this->service->delete($leaveRequest);

        return response()->json(['message' => 'Leave deleted.']);
    }

    /** @return array{0: Employee, 1: array<string, mixed>} */
    private function validatedInput(Request $request, Business $business, bool $forStore = false): array
    {
        $isShort = $request->input('leave_type') === LeaveRequest::TYPE_SHORT;

        $data = $request->validate([
            'employee_id'         => ['required', 'integer', Rule::exists('hr_employees', 'id')->where('business_id', $business->id)],
            'leave_type'          => ['required', Rule::in([...LeaveRequest::LEAVE_TYPES, LeaveRequest::TYPE_SHORT])],
            'starts_on'           => ['required', 'date'],
            'ends_on'             => [$isShort ? 'nullable' : 'required', 'date'],
            'is_half_day'         => ['boolean'],
            'half_day_period'     => ['nullable', Rule::in(['am', 'pm'])],
            'short_leave_start'   => [$isShort ? 'required' : 'nullable', 'date_format:H:i'],
            'short_leave_minutes' => [$isShort ? 'required' : 'nullable', 'integer', 'min:15', 'max:480'],
            'note'                => ['nullable', 'string', 'max:2000'],
            'status'              => [$forStore ? 'nullable' : 'exclude', Rule::in([LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_PENDING])],
            'allow_override'      => ['boolean'],
        ], [
            'employee_id.required'         => 'Choose an employee.',
            'short_leave_start.required'   => 'Enter the time the short leave starts.',
            'short_leave_minutes.required' => 'Enter how long the short leave is.',
        ]);

        return [Employee::findOrFail($data['employee_id']), $data];
    }
}
