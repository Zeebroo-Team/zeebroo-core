<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\CrmAuditLog;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Project;
use Modules\CRM\Models\StageMoveRequest;
use Modules\CRM\Models\Task;

class ProjectService
{
    public function __construct(
        private readonly LeadStageService $stages,
        private readonly LeadService $leads,
        private readonly CrmAuditService $audit,
    ) {}

    public function listForBusiness(Business $business): Collection
    {
        return Project::query()
            ->where('business_id', $business->id)
            ->withCount('leads')
            ->orderBy('name')
            ->get();
    }

    public function businessHasProjects(Business $business): bool
    {
        return Project::query()->where('business_id', $business->id)->exists();
    }

    public function create(Business $business, array $data): Project
    {
        $project = Project::create([
            'business_id' => $business->id,
            'name'        => $data['name'],
            'description' => filled($data['description'] ?? '') ? $data['description'] : null,
            'status'      => Project::STATUS_ACTIVE,
        ]);

        $this->stages->seedStandard($project);

        return $project;
    }

    public function update(Project $project, array $data): Project
    {
        $project->update([
            'name'        => $data['name'],
            'description' => filled($data['description'] ?? '') ? $data['description'] : null,
        ]);

        return $project->fresh();
    }

    public function archive(Project $project): Project
    {
        $project->update(['status' => Project::STATUS_ARCHIVED]);

        return $project;
    }

    public function reactivate(Project $project): Project
    {
        $project->update(['status' => Project::STATUS_ACTIVE]);

        return $project;
    }

    public function delete(Project $project): void
    {
        if ($project->leads()->exists()) {
            throw ValidationException::withMessages(['project' => 'Move or remove leads in this project before deleting it.']);
        }

        $project->delete();
    }

    /**
     * Delete a relation and every lead in it. Each lead goes through LeadService, because the
     * database cascade would skip its audit row and leave its stored files behind. This is not
     * one transaction: deleted files can't be rolled back, so if it fails part way the leads
     * already gone stay gone and running it again finishes the rest.
     *
     * @return int the number of leads deleted
     */
    public function deleteWithLeads(Project $project, ?int $userId = null): int
    {
        $deleted = 0;

        $project->leads()->chunkById(100, function (Collection $leads) use (&$deleted, $userId) {
            $ids     = $leads->modelKeys();
            $subject = (new Lead)->getMorphClass();

            // These point at the lead without a foreign key, so nothing cascades to them
            Task::where('subject_type', $subject)->whereIn('subject_id', $ids)->delete();
            Activity::where('subject_type', $subject)->whereIn('subject_id', $ids)->delete();
            StageMoveRequest::whereIn('lead_id', $ids)->delete();

            $leads->each(fn (Lead $lead) => $this->leads->delete($lead, $userId));
            $deleted += count($ids);
        });

        $project->delete();

        // crm_lead_audit_logs goes with the relation; this row is what records who removed it
        $this->audit->record($project, CrmAuditLog::EVENT_DELETED, $project->only(['name', 'description', 'department_id', 'status']) + ['leads_deleted' => $deleted], []);

        return $deleted;
    }

    public function projectForBusiness(Business $business, Project $project): ?Project
    {
        return $project->business_id === $business->id ? $project : null;
    }
}
