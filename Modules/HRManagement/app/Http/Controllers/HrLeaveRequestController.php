<?php

declare(strict_types=1);

namespace Modules\HRManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\LeaveRequest;
use Modules\HRManagement\Services\HrHubInboxService;
use Modules\HRManagement\Services\HrPayrollSettingsService;

class HrLeaveRequestController extends Controller
{
    public function __construct(
        private readonly HrPayrollSettingsService $hrPayrollSettings,
        private readonly HrHubInboxService $hrHubInbox,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);

        return view('hrmanagement::leave-requests.index', [
            'business' => $business,
            'leaveRequests' => $this->hrHubInbox->pendingLeaveRequestsPaginated($business),
        ]);
    }

    public function storeForEmployee(Request $request, Employee $employee): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);
        abort_unless((int) $employee->business_id === (int) $business->id, 404);

        $validated = $request->validate([
            'leave_type' => ['required', 'string', Rule::in(LeaveRequest::LEAVE_TYPES)],
            'leave_starts_on' => ['required', 'date'],
            'leave_ends_on' => ['required', 'date', 'after_or_equal:leave_starts_on'],
            'leave_note' => ['nullable', 'string', 'max:2000'],
        ]);

        LeaveRequest::query()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'leave_type' => $validated['leave_type'],
            'starts_on' => $validated['leave_starts_on'],
            'ends_on' => $validated['leave_ends_on'],
            'note' => $validated['leave_note'] ?? null,
            'status' => LeaveRequest::STATUS_PENDING,
            'recorded_by_user_id' => $request->user()->id,
        ]);

        return redirect()->to(route('hr.employees.show', $employee).'#leave')
            ->with('status', __('Leave request recorded.'));
    }

    public function updateStatus(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);

        if (! $this->hrPayrollSettings->optedIn($business)) {
            return redirect()->route('hr.onboarding');
        }

        abort_unless(Business::canAccess($request->user(), $business), 403);
        abort_unless((int) $leaveRequest->business_id === (int) $business->id, 404);
        abort_unless($leaveRequest->isPending(), 404);

        $validated = $request->validate([
            'leave_status' => ['required', 'string', Rule::in([LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_REJECTED])],
        ]);

        $leaveRequest->update(['status' => $validated['leave_status']]);

        $msg = $validated['leave_status'] === LeaveRequest::STATUS_APPROVED
            ? __('Leave request approved.')
            : __('Leave request rejected.');

        return redirect()->back(fallback: route('hr.leave-requests.index'))->with('status', $msg);
    }
}
