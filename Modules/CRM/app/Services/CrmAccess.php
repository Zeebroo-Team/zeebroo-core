<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;

/**
 * What one user may do inside the CRM for one business. Function keys default to allowed,
 * and a relation with no stage_access entry shows every stage, so a user nobody has
 * configured keeps full access.
 */
final class CrmAccess
{
    /** @var array<int, int[]> project id => visible stage ids */
    private array $stageAccess = [];

    public function __construct(
        public readonly bool $unrestricted,
        private readonly array $permissions = [],
        array $stageAccess = [],
        public readonly bool $isAdmin = false,
    ) {
        foreach ($stageAccess as $projectId => $stageIds) {
            if (is_array($stageIds)) {
                $this->stageAccess[(int) $projectId] = array_values(array_map('intval', $stageIds));
            }
        }
    }

    public static function full(bool $isAdmin = false): self
    {
        return new self(true, isAdmin: $isAdmin);
    }

    public function can(string $key): bool
    {
        return $this->unrestricted || (bool) ($this->permissions[$key] ?? true);
    }

    /** Visible stage ids for a relation, or null when every stage is visible. */
    public function visibleStageIds(int $projectId): ?array
    {
        if ($this->unrestricted) {
            return null;
        }

        return $this->stageAccess[$projectId] ?? null;
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
            'is_admin'     => $this->isAdmin,
            'unrestricted' => $this->unrestricted,
            'permissions'  => $permissions,
            'stage_access' => $this->unrestricted ? (object) [] : (object) $this->stageAccess,
        ];
    }
}
