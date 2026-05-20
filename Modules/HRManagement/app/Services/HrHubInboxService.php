<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;
use Modules\HRManagement\Models\HrComplaint;
use Modules\HRManagement\Models\LeaveRequest;

/** Pending leave and open HR complaints for the hub sidebar. */
final class HrHubInboxService
{
    private const PANEL_LIMIT = 12;

    /** @return Collection<int, LeaveRequest> */
    public function pendingLeaveRequests(Business $business): Collection
    {
        return LeaveRequest::query()
            ->where('business_id', $business->id)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->with('employee')
            ->orderByDesc('created_at')
            ->limit(self::PANEL_LIMIT)
            ->get();
    }

    public function pendingLeaveRequestsCount(Business $business): int
    {
        return LeaveRequest::query()
            ->where('business_id', $business->id)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->count();
    }

    public function pendingLeaveRequestsPaginated(Business $business, int $perPage = 30): LengthAwarePaginator
    {
        return LeaveRequest::query()
            ->where('business_id', $business->id)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->with('employee')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return Collection<int, HrComplaint> */
    public function openComplaints(Business $business): Collection
    {
        return HrComplaint::query()
            ->where('business_id', $business->id)
            ->where('status', HrComplaint::STATUS_OPEN)
            ->with('employee')
            ->orderByDesc('created_at')
            ->limit(self::PANEL_LIMIT)
            ->get();
    }

    /** @return Collection<int, Employee> */
    public function employeesForSelect(Business $business): Collection
    {
        return $business->employees()
            ->orderBy('full_name')
            ->orderBy('id')
            ->get(['id', 'full_name', 'employee_id']);
    }
}
