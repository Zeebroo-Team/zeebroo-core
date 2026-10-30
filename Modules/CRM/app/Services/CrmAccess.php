<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;

/**
 * What one user may do inside the CRM for one business: their CRM role, where they sit in the
 * org structure (departments, headships, marketing intake) and the per-user function toggles
 * and stage visibility from CrmUserPermission. The toggles only ever take away: a function the
 * role does not allow stays off whatever the toggle says. Lead- and pipeline-level rules built
 * on top of this live in LeadPolicy.
 */
final class CrmAccess
{
    public const ROLE_ADMIN      = 'admin';
    public const ROLE_CEO        = 'crm_ceo';
    public const ROLE_MARKETING  = 'crm_marketing';
    public const ROLE_HEAD       = 'crm_department_head';
    public const ROLE_STAFF      = 'crm_staff';
    /** A member whose role is not a CRM role — treated like staff of their departments. */
    public const ROLE_MEMBER     = 'member';
    /** No CRM at all (e.g. a POS cashier token). */
    public const ROLE_NONE       = 'none';

    /** The functions a view-only role (the CEO) keeps. */
    private const VIEW_KEYS = ['relations_view', 'leads_view', 'contacts_view', 'tasks_view', 'forms_view', 'custom_data_view'];

    /** Functions that act on the whole business rather than one department. */
    private const BUSINESS_KEYS = ['relations_create', 'relations_delete'];

    /** @var array<int, int[]> project id => visible stage ids */
    private array $stageAccess = [];

    /**
     * @param int[]            $departmentIds      departments the user works in or heads
     * @param int[]            $headDepartmentIds  departments the user heads
     * @param array<int, int>  $headDivisions      division id => department id, for divisions the user heads
     * @param array<int, int>  $projectDepartments project id => department id, for linked pipelines
     */
    public function __construct(
        public readonly bool $unrestricted,
        private readonly array $permissions = [],
        array $stageAccess = [],
        public readonly bool $isAdmin = false,
        public readonly ?int $userId = null,
        public readonly string $role = self::ROLE_MEMBER,
        public readonly array $departmentIds = [],
        public readonly array $headDepartmentIds = [],
        public readonly array $headDivisions = [],
        public readonly ?int $intakeDepartmentId = null,
        private readonly array $projectDepartments = [],
        public readonly bool $allDepartments = false,
    ) {
        foreach ($stageAccess as $projectId => $stageIds) {
            if (is_array($stageIds)) {
                $this->stageAccess[(int) $projectId] = array_values(array_map('intval', $stageIds));
            }
        }
    }

    public static function full(bool $isAdmin = false, ?int $userId = null): self
    {
        return new self(true, isAdmin: $isAdmin, userId: $userId, role: self::ROLE_ADMIN);
    }

    public static function none(): self
    {
        return new self(false, role: self::ROLE_NONE);
    }

    public function can(string $key): bool
    {
        if ($this->unrestricted) {
            return true;
        }

        return $this->roleAllows($key) && (bool) ($this->permissions[$key] ?? true);
    }

    private function roleAllows(string $key): bool
    {
        return match (true) {
            $this->role === self::ROLE_NONE => false,
            $this->role === self::ROLE_CEO  => in_array($key, self::VIEW_KEYS, true),
            $this->allDepartments           => true,
            default                         => ! in_array($key, self::BUSINESS_KEYS, true),
        };
    }

    public function hasCrm(): bool
    {
        return $this->role !== self::ROLE_NONE;
    }

    public function isReadOnly(): bool
    {
        return $this->role === self::ROLE_CEO;
    }

    public function isMarketing(): bool
    {
        return $this->role === self::ROLE_MARKETING;
    }

    /** Heads at least one department or division. */
    public function isHead(): bool
    {
        return $this->headDepartmentIds !== [] || $this->headDivisions !== [];
    }

    /** Sees every pipeline and lead of the business (admin, CEO, "All Departments" role). */
    public function viewsAllLeads(): bool
    {
        return $this->unrestricted || $this->role === self::ROLE_CEO || $this->allDepartments;
    }

    /** Works every pipeline and lead of the business (admin, "All Departments" role). */
    public function worksAllLeads(): bool
    {
        return $this->unrestricted || ($this->allDepartments && ! $this->isReadOnly());
    }

    public function headsDepartment(?int $departmentId): bool
    {
        return $departmentId !== null && in_array($departmentId, $this->headDepartmentIds, true);
    }

    public function headsDivision(?int $divisionId): bool
    {
        return $divisionId !== null && array_key_exists($divisionId, $this->headDivisions);
    }

    public function inDepartment(?int $departmentId): bool
    {
        return $departmentId !== null && in_array($departmentId, $this->departmentIds, true);
    }

    /** Marketing working the intake department's pipeline. */
    public function worksIntake(?int $departmentId): bool
    {
        return $this->isMarketing() && $departmentId !== null && $departmentId === $this->intakeDepartmentId;
    }

    /** The department a pipeline belongs to, or null for a pipeline no department owns. */
    public function departmentOfProject(int $projectId): ?int
    {
        return $this->projectDepartments[$projectId] ?? null;
    }

    /** Pipelines of the departments this user may see, without the "views all" shortcut. @return int[] */
    public function departmentProjectIds(): array
    {
        $departments = $this->departmentIds;
        if ($this->isMarketing() && $this->intakeDepartmentId !== null) {
            $departments[] = $this->intakeDepartmentId;
        }

        return array_keys(array_filter(
            $this->projectDepartments,
            fn (int $departmentId) => in_array($departmentId, $departments, true),
        ));
    }

    /** Visible stage ids for a relation, or null when every stage is visible. */
    public function visibleStageIds(int $projectId): ?array
    {
        if ($this->unrestricted) {
            return null;
        }

        return $this->stageAccess[$projectId] ?? null;
    }

    /** @return array<int, int[]> project id => visible stage ids, for every restricted relation */
    public function stageRestrictions(): array
    {
        return $this->unrestricted ? [] : $this->stageAccess;
    }

    public function canSeeRelation(int $projectId): bool
    {
        return $this->visibleStageIds($projectId) !== [];
    }

    public function canSeeStage(int $projectId, ?int $stageId): bool
    {
        $ids = $this->visibleStageIds($projectId);

        return $ids === null || ($stageId !== null && in_array($stageId, $ids, true));
    }

    /** Keep only the stages (models or arrays with an `id`) this user may see. */
    public function filterStages(int $projectId, Collection $stages): Collection
    {
        $ids = $this->visibleStageIds($projectId);
        if ($ids === null) {
            return $stages;
        }

        return $stages->filter(fn ($s) => in_array((int) data_get($s, 'id'), $ids, true))->values();
    }

    /** Shape returned to the desktop client. */
    public function toArray(): array
    {
        $permissions = [];
        foreach (CrmPermissionService::functionKeys() as $key) {
            $permissions[$key] = $this->can($key);
        }

        return [
            'is_admin'             => $this->isAdmin,
            'unrestricted'         => $this->unrestricted,
            'role'                 => $this->role,
            'user_id'              => $this->userId,
            'department_ids'       => array_values($this->departmentIds),
            'head_department_ids'  => array_values($this->headDepartmentIds),
            'head_division_ids'    => array_keys($this->headDivisions),
            'is_head'              => $this->isHead(),
            'intake_department_id' => $this->intakeDepartmentId,
            'read_only'            => $this->isReadOnly(),
            'can_view_all_leads'   => $this->viewsAllLeads(),
            'can_work_all_leads'   => $this->worksAllLeads(),
            'can_forward'          => $this->unrestricted || ($this->isMarketing() && $this->intakeDepartmentId !== null),
            'can_assign'           => $this->worksAllLeads() || $this->isHead(),
            'can_approve'          => $this->unrestricted || $this->isHead(),
            'permissions'          => $permissions,
            'stage_access'         => $this->unrestricted ? (object) [] : (object) $this->stageAccess,
        ];
    }
}
