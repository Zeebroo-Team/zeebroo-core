<?php

namespace Modules\HRManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Services\EmployeeSystemAccessService;

/**
 * Employee page → "System access" tab: invite the employee to sign in to the business as a team member.
 */
class HrEmployeeSystemAccessController extends Controller
{
    public function __construct(private readonly EmployeeSystemAccessService $access) {}

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $business = $this->authorizeManage($request, $employee);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', $this->access->roleSlugs($business))],
        ]);

        $wasActive = $this->access->status($employee)['status'] === EmployeeSystemAccessService::STATUS_ACTIVE;
        $result    = $this->access->grant($employee, $business, $validated['role'], $request->user());

        $message = match (true) {
            $wasActive            => __('Role updated.'),
            $result['email_sent'] => __('Invitation sent to :email.', ['email' => $this->access->accessEmail($employee)]),
            default               => __('Invitation saved, but the email could not be sent. Use "Resend" to try again.'),
        };

        return $this->back($employee, $message);
    }

    public function resend(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeManage($request, $employee);

        $sent = $this->access->resend($employee);

        return $this->back($employee, $sent
            ? __('Invitation resent to :email.', ['email' => $this->access->accessEmail($employee)])
            : __('The email could not be sent. Please try again in a few minutes.'));
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeManage($request, $employee);

        $this->access->revoke($employee);

        return $this->back($employee, __('System access removed.'));
    }

    private function authorizeManage(Request $request, Employee $employee): Business
    {
        $business = Business::currentForNavbar($request->user());
        abort_if($business === null, 403);
        abort_if((int) $employee->business_id !== (int) $business->id, 404);
        abort_unless($this->access->canManageAccess($business, $request->user()), 403, __('Only the business owner or an admin can manage system access.'));

        return $business;
    }

    private function back(Employee $employee, string $message): RedirectResponse
    {
        return redirect()->to(route('hr.employees.show', $employee).'#access')->with('status', $message);
    }
}
