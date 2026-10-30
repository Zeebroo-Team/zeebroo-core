<?php

namespace Modules\CRM\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Project;
use Modules\CRM\Services\CrmAccess;
use Modules\CRM\Services\CrmPermissionService;

/**
 * Who may do what with a lead and with a pipeline (crm_projects).
 *
 * - Admin/owner: everything.
 * - CEO: views every lead; changes nothing.
 * - Marketing: views and works the marketing intake pipeline and views the leads they
 *   forwarded; forwards intake leads.
 * - Department head (HR head, or a division head for that division): views, edits, assigns
 *   and approves everything in their department, and runs its pipeline.
 * - Staff (and any other member): views their department's leads, edits the ones assigned
 *   to them.
 * - A role with the "All Departments" permission works every pipeline.
 *
 * A pipeline no department owns is visible only to those who see everything. The per-user
 * CrmUserPermission toggles and stage visibility apply on top of all of this (AND).
 *
 * view/update/assign/approve/forward/delete are the Gate abilities (Gate::policy); the can*
 * methods take an already resolved CrmAccess so controllers resolve it once per request.
 */
class LeadPolicy
{
    public function __construct(private readonly CrmPermissionService $permissions) {}

    // ── Gate entry points ────────────────────────────────────────────────

    public function view(Authenticatable $user, Lead $lead): bool
    {
        return $this->canView($this->accessFor($user, $lead), $lead);
    }

    public function update(Authenticatable $user, Lead $lead): bool
    {
        return $this->canEdit($this->accessFor($user, $lead), $lead);
    }

    public function assign(Authenticatable $user, Lead $lead): bool
    {
        return $this->canAssign($this->accessFor($user, $lead), $lead);
    }

    public function approve(Authenticatable $user, Lead $lead): bool
    {
        return $this->canApprove($this->accessFor($user, $lead), $lead);
    }

    public function forward(Authenticatable $user, Lead $lead): bool
    {
        return $this->canForward($this->accessFor($user, $lead), $lead);
    }

    public function delete(Authenticatable $user, Lead $lead): bool
    {
        return $this->canDelete($this->accessFor($user, $lead), $lead);
    }

    // ── Pipelines ────────────────────────────────────────────────────────

    public function canViewProject(CrmAccess $access, Project $project): bool
    {
        if (! $access->can('relations_view') || ! $access->canSeeRelation($project->id)) {
            return false;
        }

        return $access->viewsAllLeads() || in_array($project->id, $access->departmentProjectIds(), true);
    }

    /** Stages, forms, custom fields, stage mail and the pipeline's name. */
    public function canManageProject(CrmAccess $access, Project $project): bool
    {
        if (! $this->canViewProject($access, $project) || $access->isReadOnly()) {
            return false;
        }

        $departmentId = $access->departmentOfProject($project->id);

        return $access->worksAllLeads()
            || $access->headsDepartment($departmentId)
            || $access->worksIntake($departmentId);
    }

    public function canCreateLeadIn(CrmAccess $access, Project $project): bool
    {
        return $access->can('leads_create') && $this->canManageProject($access, $project);
    }

    /** Picking who works the pipeline's leads: admin, "All Departments", or the department head. */
    public function canAssignIn(CrmAccess $access, Project $project): bool
    {
        return $access->can('leads_edit')
            && ! $access->isReadOnly()
            && $this->canViewProject($access, $project)
            && ($access->worksAllLeads() || $access->headsDepartment($access->departmentOfProject($project->id)));
    }

    /** Every lead of this pipeline is editable for the user, assigned to them or not. */
    public function worksWholeProject(CrmAccess $access, Project $project): bool
    {
        return $access->can('leads_edit') && $this->canManageProject($access, $project);
    }

    /** @return Collection<int, Project> */
    public function visibleProjects(CrmAccess $access, Collection $projects): Collection
    {
        return $projects->filter(fn (Project $p) => $this->canViewProject($access, $p))->values();
    }

    // ── Leads ────────────────────────────────────────────────────────────

    public function canView(CrmAccess $access, Lead $lead): bool
    {
        if (! $access->can('leads_view') || ! $access->canSeeStage($lead->project_id, $lead->stage_id)) {
            return false;
        }

        if ($access->viewsAllLeads()) {
            return true;
        }

        $departmentId = $access->departmentOfProject($lead->project_id);

        return $access->inDepartment($departmentId)
            || $access->worksIntake($departmentId)
            || ($access->isMarketing() && $this->forwardedBy($lead, $access->userId));
    }

    public function canEdit(CrmAccess $access, Lead $lead): bool
    {
        return $this->mayEdit($access, $lead)
            && ($this->worksLead($access, $lead) || $this->isAssigned($lead, $access->userId));
    }

    public function canMove(CrmAccess $access, Lead $lead): bool
    {
        return $access->can('leads_move') && $this->canEdit($access, $lead);
    }

    public function canDelete(CrmAccess $access, Lead $lead): bool
    {
        return $access->can('leads_delete')
            && $this->mayAct($access, $lead)
            && ($access->worksAllLeads() || $this->headsLead($access, $lead));
    }

    public function canAssign(CrmAccess $access, Lead $lead): bool
    {
        return $access->can('leads_edit')
            && $this->mayAct($access, $lead)
            && ($access->worksAllLeads() || $this->headsLead($access, $lead));
    }

    /** Approving the lead's quotations (discounts over the department limit). */
    public function canApprove(CrmAccess $access, Lead $lead): bool
    {
        return $this->mayAct($access, $lead)
            && ($access->unrestricted || $this->headsLead($access, $lead));
    }

    public function canForward(CrmAccess $access, Lead $lead): bool
    {
        return $this->mayAct($access, $lead)
            && ($access->unrestricted || $access->worksIntake($access->departmentOfProject($lead->project_id)));
    }

    /** The per-lead flags the desktop board uses to show or hide actions. */
    public function abilities(CrmAccess $access, Lead $lead): array
    {
        return [
            'can_edit'    => $this->canEdit($access, $lead),
            'can_move'    => $this->canMove($access, $lead),
            'can_delete'  => $this->canDelete($access, $lead),
            'can_assign'  => $this->canAssign($access, $lead),
            'can_approve' => $this->canApprove($access, $lead),
            'can_forward' => $this->canForward($access, $lead),
        ];
    }

    // ── Query scope ──────────────────────────────────────────────────────

    /** Limit a lead query to what canView allows. Backs Lead::visibleTo(). */
    public function scopeVisible(Builder $query, CrmAccess $access): Builder
    {
        if (! $access->can('leads_view')) {
            return $query->whereRaw('1 = 0');
        }

        foreach ($access->stageRestrictions() as $projectId => $stageIds) {
            $query->where(fn (Builder $q) => $q->where('crm_leads.project_id', '!=', $projectId)
                ->orWhereIn('crm_leads.stage_id', $stageIds ?: [0]));
        }

        if ($access->viewsAllLeads()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($access) {
            $q->whereIn('crm_leads.project_id', $access->departmentProjectIds() ?: [0]);
            if ($access->isMarketing()) {
                $q->orWhere(fn (Builder $q) => $this->whereForwardedBy($q, (int) $access->userId));
            }
        });
    }

    /**
     * Ids of the given leads the user may edit. One query for the leads they work only by
     * being assigned; the rest is decided per pipeline.
     *
     * @param  Collection<int, Lead>  $leads
     * @return array<int, true>
     */
    public function editableLeadIds(CrmAccess $access, Collection $leads): array
    {
        $assigned = $access->userId === null || $leads->isEmpty() ? [] : $this->whereAssigned(
            Lead::query()->whereIn('id', $leads->pluck('id')),
            $access->userId,
        )->pluck('id')->flip()->all();

        return $leads
            ->filter(fn (Lead $lead) => $this->mayEdit($access, $lead)
                && ($this->worksLead($access, $lead) || isset($assigned[$lead->id])))
            ->mapWithKeys(fn (Lead $lead) => [$lead->id => true])
            ->all();
    }

    // ── Assignment & org hooks ───────────────────────────────────────────

    /**
     * Who a lead is assigned to. This and isAssigned are the only places that know it is the
     * single crm_leads.assigned_to column; switching to several assignees changes this method.
     */
    public function whereAssigned(Builder $query, int $userId): Builder
    {
        return $query->where('crm_leads.assigned_to', $userId);
    }

    public function isAssigned(Lead $lead, ?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        return $this->whereAssigned(Lead::query()->whereKey($lead->id), $userId)->exists();
    }

    /** Leads this user forwarded out of the marketing inbox. Forwarding is not recorded yet. */
    public function whereForwardedBy(Builder $query, int $userId): Builder
    {
        return $query->whereRaw('1 = 0');
    }

    private function forwardedBy(Lead $lead, ?int $userId): bool
    {
        return $userId !== null && $this->whereForwardedBy(Lead::query()->whereKey($lead->id), $userId)->exists();
    }

    /** Sees the lead and is not a view-only role. */
    private function mayAct(CrmAccess $access, Lead $lead): bool
    {
        return ! $access->isReadOnly() && $this->canView($access, $lead);
    }

    /** Edit rights before looking at who works the lead. */
    private function mayEdit(CrmAccess $access, Lead $lead): bool
    {
        return $access->can('leads_edit') && $this->mayAct($access, $lead);
    }

    /** Works the lead without being assigned: all-departments, its head, or marketing in intake. */
    private function worksLead(CrmAccess $access, Lead $lead): bool
    {
        return $access->worksAllLeads()
            || $this->headsLead($access, $lead)
            || $access->worksIntake($access->departmentOfProject($lead->project_id));
    }

    /** The user heads the lead's department, or the division the lead sits in. */
    private function headsLead(CrmAccess $access, Lead $lead): bool
    {
        return $access->headsDepartment($access->departmentOfProject($lead->project_id))
            || $access->headsDivision($lead->division_id !== null ? (int) $lead->division_id : null);
    }

    private function accessFor(Authenticatable $user, Lead $lead): CrmAccess
    {
        return $this->permissions->accessFor($lead->business, $user);
    }
}
