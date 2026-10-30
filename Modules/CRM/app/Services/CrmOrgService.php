<?php

namespace Modules\CRM\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessMember;
use Modules\CRM\Models\DepartmentSetting;
use Modules\CRM\Models\Division;
use Modules\CRM\Models\Project;
use Modules\HRManagement\Models\Department;
use Modules\HRManagement\Models\Employee;

/**
 * The org structure the CRM routes leads through: HR department → CRM division, one
 * pipeline (crm_projects) per department, and per-department CRM settings. The HR
 * department head is the CRM department head.
 */
class CrmOrgService
{
    public function __construct(private readonly ProjectService $projects) {}

    /** Every department of the business with its divisions, pipeline, settings and warnings. */
    public function departments(Business $business): array
    {
        $departments = Department::query()
            ->where('business_id', $business->id)
            ->with(['headEmployee.user', 'coHeadEmployee.user'])
            ->orderBy('name')
            ->get();

        $ids = $departments->pluck('id');

        $pipelines = Project::query()
            ->where('business_id', $business->id)
            ->whereIn('department_id', $ids)
            ->get(['id', 'department_id', 'name', 'status'])
            ->keyBy('department_id');

        $settings = DepartmentSetting::query()
            ->where('business_id', $business->id)
            ->whereIn('department_id', $ids)
            ->get()
            ->keyBy('department_id');

        $divisions = Division::query()
            ->where('business_id', $business->id)
            ->whereIn('department_id', $ids)
            ->with('headUser:id,name,email')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('department_id');

        // [department_id => ['total' => n, 'unlinked' => n]]
        $staff = Employee::query()
            ->where('business_id', $business->id)
            ->whereIn('department_id', $ids)
            ->selectRaw('department_id, COUNT(*) as total, SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as unlinked')
            ->groupBy('department_id')
            ->get()
            ->keyBy('department_id');

        return $departments->map(function (Department $d) use ($pipelines, $settings, $divisions, $staff) {
            $pipeline = $pipelines->get($d->id);
            $setting  = $settings->get($d->id);
            $counts   = $staff->get($d->id);

            return [
                'id'            => $d->id,
                'name'          => $d->name,
                'head'          => $this->employeeSummary($d->headEmployee),
                'co_head'       => $this->employeeSummary($d->coHeadEmployee),
                'head_user_id'  => $this->headUserIdFromLoaded($d),
                'pipeline'      => $pipeline ? [
                    'id'     => $pipeline->id,
                    'name'   => $pipeline->name,
                    'status' => $pipeline->status,
                ] : null,
                'settings'      => $this->settingsSummary($setting),
                'divisions'     => $divisions->get($d->id, collect())->map(fn (Division $v) => $this->divisionSummary($v))->values()->all(),
                'staff_count'   => (int) ($counts->total ?? 0),
                'unlinked_staff_count' => (int) ($counts->unlinked ?? 0),
                'warnings'      => $this->warnings($d, (int) ($counts->unlinked ?? 0)),
            ];
        })->values()->all();
    }

    public function departmentForBusiness(Business $business, int $departmentId): Department
    {
        return Department::query()
            ->where('business_id', $business->id)
            ->findOrFail($departmentId);
    }

    public function divisionForBusiness(Business $business, int $divisionId): Division
    {
        return Division::query()
            ->where('business_id', $business->id)
            ->findOrFail($divisionId);
    }

    /**
     * The user who heads this department for CRM purposes: a division's own head wins,
     * then the HR head, then the HR co-head. Null when none of them has a Zeebroo user.
     */
    public function headUserId(Department $department, ?Division $division = null): ?int
    {
        if ($division !== null && (int) $division->department_id === (int) $department->id && $division->head_user_id) {
            return (int) $division->head_user_id;
        }

        $department->loadMissing(['headEmployee', 'coHeadEmployee']);

        return $this->headUserIdFromLoaded($department);
    }

    /** Users of the department's HR staff who have a Zeebroo login. @return int[] */
    public function memberUserIds(Department $department): array
    {
        return Employee::query()
            ->where('business_id', $department->business_id)
            ->where('department_id', $department->id)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Who a pipeline's leads can be assigned to: the department's staff with a login plus its
     * head, or every business user for a pipeline no department owns.
     *
     * @return Collection<int, User>
     */
    public function assignableUsers(Business $business, ?int $departmentId): Collection
    {
        $department = $departmentId !== null
            ? Department::query()->where('business_id', $business->id)->find($departmentId)
            : null;

        if ($department === null) {
            return $this->businessUsers($business);
        }

        $ids = $this->memberUserIds($department);
        if ($headId = $this->headUserId($department)) {
            $ids[] = $headId;
        }

        return User::query()->whereIn('id', array_unique($ids))->orderBy('name')->get(['id', 'name', 'email']);
    }

    /** Departments the user works in through their HR employee record. @return int[] */
    public function departmentIdsForUser(Business $business, int $userId): array
    {
        return Employee::query()
            ->where('business_id', $business->id)
            ->where('user_id', $userId)
            ->whereNotNull('department_id')
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** Departments whose head user (see headUserId) is this user. @return int[] */
    public function headedDepartmentIds(Business $business, int $userId): array
    {
        return Department::query()
            ->where('business_id', $business->id)
            ->with(['headEmployee', 'coHeadEmployee'])
            ->get()
            ->filter(fn (Department $d) => $this->headUserIdFromLoaded($d) === $userId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /** Active divisions this user heads. @return array<int, int> division id => department id */
    public function headedDivisions(Business $business, int $userId): array
    {
        return Division::query()
            ->where('business_id', $business->id)
            ->where('head_user_id', $userId)
            ->where('is_active', true)
            ->pluck('department_id', 'id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** The department every new lead lands in first, if one is marked. */
    public function marketingIntakeDepartment(Business $business): ?Department
    {
        $departmentId = DepartmentSetting::query()
            ->where('business_id', $business->id)
            ->where('is_marketing_intake', true)
            ->value('department_id');

        return $departmentId
            ? Department::query()->where('business_id', $business->id)->find($departmentId)
            : null;
    }

    /** The owner and active members — the users a division head can be picked from. */
    public function businessUsers(Business $business): Collection
    {
        $memberIds = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->pluck('user_id');

        return User::query()
            ->whereIn('id', $memberIds->push($business->user_id))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    // ── Divisions ────────────────────────────────────────────────────────

    public function createDivision(Business $business, Department $department, array $data): Division
    {
        $name = trim($data['name']);
        $this->assertUniqueDivisionName($department, $name);

        return Division::create([
            'business_id'   => $business->id,
            'department_id' => $department->id,
            'name'          => $name,
            'head_user_id'  => $this->cleanHeadUserId($business, $data['head_user_id'] ?? null),
            'is_active'     => (bool) ($data['is_active'] ?? true),
            'sort_order'    => (int) ($data['sort_order']
                ?? (Division::where('department_id', $department->id)->max('sort_order') + 1)),
        ]);
    }

    public function updateDivision(Business $business, Division $division, array $data): Division
    {
        $name = trim($data['name']);
        $this->assertUniqueDivisionName($division->department, $name, $division->id);

        $division->update([
            'name'         => $name,
            'head_user_id' => array_key_exists('head_user_id', $data)
                ? $this->cleanHeadUserId($business, $data['head_user_id'])
                : $division->head_user_id,
            'is_active'    => (bool) ($data['is_active'] ?? $division->is_active),
            'sort_order'   => (int) ($data['sort_order'] ?? $division->sort_order),
        ]);

        return $division->fresh();
    }

    public function deleteDivision(Division $division): void
    {
        $division->delete();
    }

    // ── Pipeline link ────────────────────────────────────────────────────

    /**
     * Link a pipeline to the department, replacing any pipeline linked before. With no
     * project id a new pipeline named after the department is created.
     */
    public function linkPipeline(Business $business, Department $department, ?int $projectId): Project
    {
        return DB::transaction(function () use ($business, $department, $projectId) {
            if ($projectId !== null) {
                $project = Project::query()->where('business_id', $business->id)->find($projectId);
                if ($project === null) {
                    throw ValidationException::withMessages(['project_id' => 'Pipeline not found.']);
                }
                if ($project->department_id !== null && (int) $project->department_id !== (int) $department->id) {
                    throw ValidationException::withMessages(['project_id' => 'This pipeline is already linked to another department.']);
                }
            } else {
                $project = $this->projects->create($business, [
                    'name'        => $this->freePipelineName($business, $department->name),
                    'description' => 'Pipeline for the ' . $department->name . ' department.',
                ]);
            }

            Project::query()
                ->where('business_id', $business->id)
                ->where('department_id', $department->id)
                ->whereKeyNot($project->id)
                ->update(['department_id' => null]);

            $project->update(['department_id' => $department->id]);

            return $project->fresh();
        });
    }

    public function unlinkPipeline(Business $business, Department $department): void
    {
        Project::query()
            ->where('business_id', $business->id)
            ->where('department_id', $department->id)
            ->update(['department_id' => null]);
    }

    // ── Settings ─────────────────────────────────────────────────────────

    /** Save a department's CRM settings. Marking it the intake unmarks every other department. */
    public function saveSettings(Business $business, Department $department, array $data): DepartmentSetting
    {
        return DB::transaction(function () use ($business, $department, $data) {
            $isIntake = (bool) ($data['is_marketing_intake'] ?? false);

            if ($isIntake) {
                DepartmentSetting::query()
                    ->where('business_id', $business->id)
                    ->where('department_id', '!=', $department->id)
                    ->update(['is_marketing_intake' => false]);
            }

            $limit = $data['discount_approval_limit_percent'] ?? null;

            return DepartmentSetting::updateOrCreate(
                ['department_id' => $department->id],
                [
                    'business_id'                     => $business->id,
                    'is_marketing_intake'             => $isIntake,
                    'discount_approval_limit_percent' => $limit === null || $limit === '' ? null : round((float) $limit, 2),
                    'followup_days'                   => $this->cleanFollowupDays($data['followup_days'] ?? null),
                ],
            );
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function headUserIdFromLoaded(Department $department): ?int
    {
        $userId = $department->headEmployee?->user_id ?? $department->coHeadEmployee?->user_id;

        return $userId ? (int) $userId : null;
    }

    private function warnings(Department $department, int $unlinkedStaff): array
    {
        $warnings = [];

        if ($department->headEmployee === null) {
            $warnings[] = 'No department head is set in HR.';
        } elseif ($department->headEmployee->user_id === null) {
            $warnings[] = 'Head ' . $department->headEmployee->full_name . ' has no linked Zeebroo user.';
        }

        if ($department->coHeadEmployee !== null && $department->coHeadEmployee->user_id === null) {
            $warnings[] = 'Co-head ' . $department->coHeadEmployee->full_name . ' has no linked Zeebroo user.';
        }

        if ($unlinkedStaff > 0) {
            $warnings[] = $unlinkedStaff . ' staff member' . ($unlinkedStaff === 1 ? ' has' : 's have') . ' no linked Zeebroo user.';
        }

        return $warnings;
    }

    private function employeeSummary(?Employee $employee): ?array
    {
        if ($employee === null) {
            return null;
        }

        return [
            'employee_id' => $employee->id,
            'name'        => $employee->full_name,
            'user_id'     => $employee->user_id ? (int) $employee->user_id : null,
            'user_name'   => $employee->user?->name,
        ];
    }

    private function settingsSummary(?DepartmentSetting $setting): array
    {
        return [
            'is_marketing_intake'             => (bool) $setting?->is_marketing_intake,
            'discount_approval_limit_percent' => $setting?->discount_approval_limit_percent !== null
                ? (float) $setting->discount_approval_limit_percent
                : null,
            'followup_days'                   => $setting?->followupDays() ?? DepartmentSetting::DEFAULT_FOLLOWUP_DAYS,
        ];
    }

    private function divisionSummary(Division $division): array
    {
        return [
            'id'             => $division->id,
            'name'           => $division->name,
            'head_user_id'   => $division->head_user_id ? (int) $division->head_user_id : null,
            'head_user_name' => $division->headUser?->name,
            'is_active'      => (bool) $division->is_active,
            'sort_order'     => (int) $division->sort_order,
        ];
    }

    private function assertUniqueDivisionName(Department $department, string $name, ?int $ignoreId = null): void
    {
        $taken = Division::query()
            ->where('department_id', $department->id)
            ->where('name', $name)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => 'This department already has a division with that name.']);
        }
    }

    private function cleanHeadUserId(Business $business, mixed $userId): ?int
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        if (! $this->businessUsers($business)->contains('id', (int) $userId)) {
            throw ValidationException::withMessages(['head_user_id' => 'The division head must be a user of this business.']);
        }

        return (int) $userId;
    }

    /** Positive whole days, unique and sorted; null when empty so the default applies. */
    private function cleanFollowupDays(mixed $days): ?array
    {
        if (is_string($days)) {
            $days = preg_split('/[\s,]+/', $days, -1, PREG_SPLIT_NO_EMPTY);
        }
        if (! is_array($days)) {
            return null;
        }

        $clean = collect($days)
            ->map(fn ($d) => (int) $d)
            ->filter(fn (int $d) => $d >= 1 && $d <= 365)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $clean ?: null;
    }

    private function freePipelineName(Business $business, string $base): string
    {
        $base = mb_substr($base, 0, 140);
        $name = $base;
        for ($i = 2; Project::where('business_id', $business->id)->where('name', $name)->exists(); $i++) {
            $name = $base . ' (' . $i . ')';
        }

        return $name;
    }
}
