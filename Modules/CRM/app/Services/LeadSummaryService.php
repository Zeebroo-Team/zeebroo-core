<?php

namespace Modules\CRM\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\CRM\Models\FollowUp;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadAssignee;
use Modules\CRM\Models\Task;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Models\Employee;
use Modules\Sales\Models\Quotation;

/**
 * Everything about one lead on a single card — the History Flow side panel: the lead itself,
 * its department with the head / co-head / division head, and who works it with their HR
 * record (job title, department, contact). HR pay, bank and ID fields are never included.
 */
class LeadSummaryService
{
    public function __construct(
        private readonly LeadAssignmentService $assignments,
    ) {}

    public function summary(Lead $lead): array
    {
        $lead->loadMissing([
            'project:id,name,department_id', 'stage', 'division.headUser:id,name,email', 'leadSource',
            'lostReason', 'customer', 'createdBy:id,name', 'verifiedBy:id,name', 'forwardedBy:id,name',
            'requestedDepartment:id,name', 'products:id,name',
        ]);

        $assignees  = $lead->assignees()->with(['user:id,name,email', 'assignedBy:id,name'])->get();
        $department = $this->department($lead);
        $employees  = $this->employees($lead->business_id, [
            ...$assignees->pluck('user_id')->all(),
            $lead->division?->head_user_id,
        ]);

        return [
            'lead'        => $this->lead($lead),
            'department'  => $department ? [
                'id'      => $department->id,
                'name'    => $department->name,
                'head'    => $this->employee($department->headEmployee),
                'co_head' => $this->employee($department->coHeadEmployee),
            ] : null,
            'division'    => $lead->division ? [
                'id'   => $lead->division->id,
                'name' => $lead->division->name,
                'head' => $lead->division->headUser
                    ? $this->person($lead->division->headUser, $employees->get($lead->division->head_user_id))
                    : null,
            ] : null,
            'assignees'   => $assignees->map(fn (LeadAssignee $a) => [
                ...($a->user ? $this->person($a->user, $employees->get($a->user_id)) : ['user_id' => (int) $a->user_id, 'name' => 'Deleted user']),
                'is_primary'  => (bool) $a->is_primary,
                'head_role'   => match ((int) $a->user_id) {
                    (int) $department?->headEmployee?->user_id   => 'head',
                    (int) $department?->coHeadEmployee?->user_id => 'co_head',
                    default                                      => null,
                },
                'assigned_at' => $a->assigned_at?->toIso8601String(),
                'assigned_by' => $a->assignedBy?->name,
            ])->values()->all(),
            'assignment_history' => $this->assignments->presentHistory($lead->assignmentLogs()->limit(10)->get()),
            'counts'      => [
                'messages'           => $lead->messages()->count(),
                'files'              => $lead->attachments()->count(),
                'open_tasks'         => $lead->tasks()->where('status', Task::STATUS_PENDING)->count(),
                'pending_follow_ups' => $lead->followUps()->where('status', FollowUp::STATUS_PENDING)->count(),
                'quotations'         => Quotation::where('lead_id', $lead->id)->count(),
            ],
        ];
    }

    private function lead(Lead $lead): array
    {
        $at = fn ($d) => $d?->toIso8601String();

        return [
            'id'                    => $lead->id,
            'name'                  => $lead->name,
            'company'               => $lead->company,
            'email'                 => $lead->email,
            'phone'                 => $lead->phone,
            'source'                => $lead->source,
            'source_name'           => $lead->leadSource?->label,
            'pipeline'              => $lead->project?->name,
            'stage'                 => $lead->stage ? ['id' => $lead->stage->id, 'name' => $lead->stage->name, 'color' => $lead->stageColor()] : null,
            'is_on_hold'            => $lead->isOnHold(),
            'estimated_value'       => $lead->estimated_value !== null ? (float) $lead->estimated_value : null,
            'score'                 => $lead->score,
            'expected_close_date'   => $lead->expected_close_date?->toDateString(),
            'lost_reason'           => $lead->lostReason?->label ?? $lead->lost_reason,
            'notes'                 => $lead->notes,
            'product_interest_note' => $lead->product_interest_note,
            'products'              => $lead->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'quantity' => $p->pivot->quantity])->values()->all(),
            'customer'              => $lead->customer ? ['id' => $lead->customer->id, 'name' => $lead->customer->name] : null,
            'requested_department'  => $lead->requestedDepartment?->name,
            'created_by'            => $lead->createdBy?->name,
            'created_at'            => $at($lead->created_at),
            'verified_by'           => $lead->verifiedBy?->name,
            'verified_at'           => $at($lead->verified_at),
            'forwarded_by'          => $lead->forwardedBy?->name,
            'forwarded_at'          => $at($lead->forwarded_at),
            'first_response_at'     => $at($lead->first_response_at),
            'converted_at'          => $at($lead->converted_at),
            'closed_at'             => $at($lead->closed_at),
        ];
    }

    /** The lead's own department, else the department owning its pipeline. */
    private function department(Lead $lead): ?Department
    {
        $id = $lead->department_id ?? $lead->project?->department_id;
        if ($id === null) {
            return null;
        }

        return Department::query()
            ->where('business_id', $lead->business_id)
            ->with(['headEmployee.user:id,name,email', 'headEmployee.jobTitle:id,name', 'coHeadEmployee.user:id,name,email', 'coHeadEmployee.jobTitle:id,name'])
            ->find($id);
    }

    /** HR records of these users in the business. @return Collection<int, Employee> keyed by user id */
    private function employees(int $businessId, array $userIds): Collection
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return collect();
        }

        return Employee::query()
            ->where('business_id', $businessId)
            ->whereIn('user_id', $userIds)
            ->with(['jobTitle:id,name', 'department:id,name'])
            ->get()
            ->keyBy('user_id');
    }

    private function person(User $user, ?Employee $employee): array
    {
        return [
            'user_id'  => (int) $user->id,
            'name'     => $employee?->full_name ?: $user->name,
            'email'    => $user->email,
            'employee' => $employee ? $this->employeeFields($employee) : null,
        ];
    }

    private function employee(?Employee $employee): ?array
    {
        if ($employee === null) {
            return null;
        }

        return [
            'user_id'  => $employee->user_id ? (int) $employee->user_id : null,
            'name'     => $employee->full_name,
            'email'    => $employee->user?->email,
            'employee' => $this->employeeFields($employee),
        ];
    }

    private function employeeFields(Employee $employee): array
    {
        return [
            'id'              => $employee->id,
            'employee_no'     => $employee->employee_id,
            'job_title'       => $employee->jobTitle?->name,
            'department'      => $employee->department?->name,
            'phone'           => $employee->phone_number,
            'employment_type' => $employee->employment_type ? $employee->employmentTypeLabel() : null,
            'joined_on'       => $employee->date_of_joining?->toDateString(),
            'photo_url'       => $employee->profilePhotoUrl(),
            'initials'        => $employee->avatarInitials(),
        ];
    }
}
