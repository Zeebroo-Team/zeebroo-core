<?php

namespace Modules\Pos\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Services\EmployeeSystemAccessService;
use Modules\Pos\Http\Controllers\Api\Concerns\ResolvesPosBusinessForApi;

/**
 * HR → employee → "System access": invite an employee to sign in to the business as a team member.
 */
class PosHrEmployeeAccessApiController extends Controller
{
    use ResolvesPosBusinessForApi;

    public function __construct(private readonly EmployeeSystemAccessService $access) {}

    public function show(Request $request, Employee $employee): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessPerm($request, $business, 'hr_employees');
        $this->abortUnlessOwnEmployee($business, $employee);

        return response()->json([
            'data'       => $this->access->status($employee),
            'can_manage' => $this->canManage($request, $business),
        ]);
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCanManage($request, $business);
        $this->abortUnlessOwnEmployee($business, $employee);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', $this->access->roleSlugs($business))],
        ]);

        $before = $this->access->status($employee)['status'];
        $result = $this->access->grant($employee, $business, $validated['role'], $request->user());
        $status = $this->access->status($employee);

        $message = match (true) {
            $before === EmployeeSystemAccessService::STATUS_ACTIVE => "Role updated to {$status['role_name']}.",
            $result['email_sent']                                  => "Invitation sent to {$status['email']}.",
            default                                                => 'Invitation saved, but the email could not be sent. Use "Resend" to try again.',
        };

        return response()->json(['data' => $status, 'email_sent' => $result['email_sent'], 'message' => $message]);
    }

    public function resend(Request $request, Employee $employee): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCanManage($request, $business);
        $this->abortUnlessOwnEmployee($business, $employee);

        if (! $this->access->resend($employee)) {
            return response()->json(['message' => 'The email could not be sent. Please try again in a few minutes.'], 503);
        }

        $status = $this->access->status($employee);

        return response()->json(['data' => $status, 'message' => "Invitation resent to {$status['email']}."]);
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $business = $this->businessOrAbort($request);
        $this->abortUnlessCanManage($request, $business);
        $this->abortUnlessOwnEmployee($business, $employee);

        $this->access->revoke($employee);

        return response()->json(['data' => $this->access->status($employee), 'message' => 'System access removed.']);
    }

    private function abortUnlessOwnEmployee(Business $business, Employee $employee): void
    {
        if ((int) $employee->business_id !== (int) $business->id) {
            throw new HttpResponseException(response()->json(['message' => 'Employee not found.'], 404));
        }
    }

    /** Cashier tokens (PosCashier) never manage access. */
    private function canManage(Request $request, Business $business): bool
    {
        $user = $request->user();

        return $user instanceof User && $this->access->canManageAccess($business, $user);
    }

    private function abortUnlessCanManage(Request $request, Business $business): void
    {
        if (! $this->canManage($request, $business)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Only the business owner or an admin can manage system access.',
            ], 403));
        }
    }
}
