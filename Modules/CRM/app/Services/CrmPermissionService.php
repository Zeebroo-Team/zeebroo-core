<?php

namespace Modules\CRM\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Business\Models\Business;
use Modules\Business\Models\BusinessMember;
use Modules\Business\Models\BusinessRole;
use Modules\CRM\Models\CrmUserPermission;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\Project;
use Modules\Pos\Models\PosCashier;

/**
 * Per-user CRM permissions: which CRM functions a business member may use and which
 * pipeline stages they see in each relation. The owner and admin-role members always
 * have full access and are the only ones who may change these settings.
 */
class CrmPermissionService
{
    /** Grouped function catalogue — used by the API and the desktop permissions modal. */
    public static function functions(): array
    {
        return [
            [
                'key'   => 'relations',
                'label' => 'Relations',
                'icon'  => 'fa-handshake',
                'items' => [
                    ['key' => 'relations_view',   'label' => 'View relations',   'desc' => 'Open the relations list and relation details'],
                    ['key' => 'relations_create', 'label' => 'Create relations', 'desc' => 'Add new relations (pipeline projects)'],
                    ['key' => 'relations_delete', 'label' => 'Delete relations', 'desc' => 'Permanently delete empty relations'],
                ],
            ],
            [
                'key'   => 'leads',
                'label' => 'Pipeline & Leads',
                'icon'  => 'fa-table-columns',
                'items' => [
                    ['key' => 'leads_view',   'label' => 'View pipeline', 'desc' => 'See the pipeline board and its leads'],
                    ['key' => 'leads_create', 'label' => 'Add leads',     'desc' => 'Create leads from the default form'],
                    ['key' => 'leads_edit',   'label' => 'Edit leads',    'desc' => 'Change lead details'],
                    ['key' => 'leads_move',   'label' => 'Move leads',    'desc' => 'Drag leads between stages'],
                    ['key' => 'leads_delete', 'label' => 'Delete leads',  'desc' => 'Remove leads from the pipeline'],
                ],
            ],
            [
                'key'   => 'stages',
                'label' => 'Stages',
                'icon'  => 'fa-layer-group',
                'items' => [
                    ['key' => 'stages_manage', 'label' => 'Manage stages',       'desc' => 'Add, rename, recolour, reorder and delete stages'],
                    ['key' => 'stage_mail',    'label' => 'Stage mail templates', 'desc' => 'Set up and send stage mail templates'],
                ],
            ],
            [
                'key'   => 'contacts',
                'label' => 'Contacts',
                'icon'  => 'fa-address-book',
                'items' => [
                    ['key' => 'contacts_view', 'label' => 'View contacts', 'desc' => 'See the Contacts tab and relation contacts'],
                ],
            ],
            [
                'key'   => 'tasks',
                'label' => 'Tasks',
                'icon'  => 'fa-list-check',
                'items' => [
                    ['key' => 'tasks_view',     'label' => 'View tasks',             'desc' => 'See open, overdue and completed tasks'],
                    ['key' => 'tasks_create',   'label' => 'Create tasks',           'desc' => 'Add new follow-up tasks'],
                    ['key' => 'tasks_complete', 'label' => 'Complete / reopen tasks', 'desc' => 'Mark tasks done or reopen them'],
                    ['key' => 'tasks_delete',   'label' => 'Delete tasks',           'desc' => 'Remove tasks'],
                ],
            ],
            [
                'key'   => 'forms',
                'label' => 'Forms & Data',
                'icon'  => 'fa-file-lines',
                'items' => [
                    ['key' => 'forms_view',            'label' => 'View forms',           'desc' => 'See the Forms tab of a relation'],
                    ['key' => 'forms_manage',          'label' => 'Manage forms',         'desc' => 'Create, edit, publish, set default and delete forms'],
                    ['key' => 'custom_fields_manage',  'label' => 'Manage custom fields', 'desc' => 'Add custom lead fields'],
                    ['key' => 'custom_data_view',      'label' => 'View custom data',     'desc' => 'See the Custom Data tab of a relation'],
                ],
            ],
        ];
    }

    /** Flat list of all valid function keys — use for validation. */
    public static function functionKeys(): array
    {
        $keys = [];
        foreach (static::functions() as $group) {
            foreach ($group['items'] as $item) {
                $keys[] = $item['key'];
            }
        }
        return $keys;
    }

    /** Resolve what the authenticated user may do in this business's CRM. */
    public function accessFor(Business $business, ?Authenticatable $user): CrmAccess
    {
        // Cashier tokens carry no user id of their own; CRM gating for them stays with the
        // existing role-level checks.
        if ($user === null || $user instanceof PosCashier) {
            return CrmAccess::full();
        }

        if ($this->isAdmin($business, (int) $user->getAuthIdentifier())) {
            return CrmAccess::full(isAdmin: true);
        }

        $row = CrmUserPermission::query()
            ->where('business_id', $business->id)
            ->where('user_id', $user->getAuthIdentifier())
            ->first();

        return new CrmAccess(false, $row?->permissions ?? [], $row?->stage_access ?? []);
    }

    /** Owner, an "admin" member, or a member whose role grants full access. */
    public function isAdmin(Business $business, int $userId): bool
    {
        if ((int) $business->user_id === $userId) {
            return true;
        }

        $member = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('user_id', $userId)
            ->first();

        return $member !== null && $this->memberIsAdmin($business, $member);
    }

    /** Every user in the business with their saved CRM settings, owner first. */
    public function usersForBusiness(Business $business): array
    {
        BusinessRole::seedForBusiness($business->id);

        $saved = CrmUserPermission::query()
            ->where('business_id', $business->id)
            ->get()
            ->keyBy('user_id');

        $owner = $business->user;
        $users = [[
            'user_id'      => (int) $owner->id,
            'name'         => $owner->name,
            'email'        => $owner->email,
            'role'         => 'owner',
            'is_admin'     => true,
            'customized'   => false,
            'permissions'  => (object) [],
            'stage_access' => (object) [],
        ]];

        $members = BusinessMember::query()
            ->where('business_id', $business->id)
            ->with('user')
            ->orderBy('created_at')
            ->get();

        foreach ($members as $member) {
            if ($member->user === null || (int) $member->user_id === (int) $owner->id) {
                continue;
            }
            $row = $saved->get($member->user_id);
            $users[] = [
                'user_id'      => (int) $member->user_id,
                'name'         => $member->user->name,
                'email'        => $member->user->email,
                'role'         => $member->role,
                'status'       => $member->status,
                'is_admin'     => $this->memberIsAdmin($business, $member),
                'customized'   => $row !== null,
                'permissions'  => (object) ($row?->permissions ?? []),
                'stage_access' => (object) ($row?->stage_access ?? []),
            ];
        }

        return $users;
    }

    /** Active relations with their stages, for the stage-visibility editor. */
    public function relationsWithStages(Business $business): array
    {
        $projects = Project::query()
            ->where('business_id', $business->id)
            ->where('status', Project::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name']);

        $stages = LeadStage::query()
            ->whereIn('project_id', $projects->pluck('id'))
            ->orderBy('sort_order')
            ->get(['id', 'project_id', 'name', 'color', 'is_won', 'is_lost'])
            ->groupBy('project_id');

        return $projects->map(fn (Project $p) => [
            'id'     => $p->id,
            'name'   => $p->name,
            'stages' => $stages->get($p->id, collect())->map(fn (LeadStage $s) => [
                'id'      => $s->id,
                'name'    => $s->name,
                'color'   => $s->color,
                'is_won'  => (bool) $s->is_won,
                'is_lost' => (bool) $s->is_lost,
            ])->values(),
        ])->values()->all();
    }

    /**
     * Save a member's CRM settings. Unknown function keys are dropped, and stage ids are
     * kept only when they belong to that relation in this business.
     */
    public function saveForUser(Business $business, int $userId, array $permissions, array $stageAccess): CrmUserPermission
    {
        $validKeys   = array_flip(static::functionKeys());
        $permissions = array_map('boolval', array_intersect_key($permissions, $validKeys));

        $stageIdsByProject = LeadStage::query()
            ->whereIn('project_id', Project::where('business_id', $business->id)->pluck('id'))
            ->get(['id', 'project_id'])
            ->groupBy('project_id')
            ->map(fn ($rows) => $rows->pluck('id')->map(fn ($id) => (int) $id)->all());

        $cleanStageAccess = [];
        foreach ($stageAccess as $projectId => $stageIds) {
            $projectId = (int) $projectId;
            if (! is_array($stageIds) || ! $stageIdsByProject->has($projectId)) {
                continue;
            }
            $cleanStageAccess[$projectId] = array_values(array_intersect(
                $stageIdsByProject->get($projectId),
                array_map('intval', $stageIds),
            ));
        }

        return CrmUserPermission::updateOrCreate(
            ['business_id' => $business->id, 'user_id' => $userId],
            ['permissions' => $permissions, 'stage_access' => $cleanStageAccess ?: null],
        );
    }

    /** Drop a member's CRM settings so they fall back to full access. */
    public function resetForUser(Business $business, int $userId): void
    {
        CrmUserPermission::query()
            ->where('business_id', $business->id)
            ->where('user_id', $userId)
            ->delete();
    }

    private function memberIsAdmin(Business $business, BusinessMember $member): bool
    {
        if ($member->role === 'admin') {
            return true;
        }

        // Same rule as /pos/me: a role template with null permissions grants full access.
        $role = BusinessRole::query()
            ->where('business_id', $business->id)
            ->where('slug', $member->role)
            ->first();

        return $role !== null && $role->permissions === null;
    }
}
