<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\Project;
use Modules\CRM\Services\LeadStageService;

/**
 * php artisan crm:map-stage-statuses                  → dry run: shows how each stage would be mapped
 * php artisan crm:map-stage-statuses --apply          → saves the mapping
 * php artisan crm:map-stage-statuses --apply --seed   → also adds the missing standard stages to every pipeline
 */
class MapStageStatusesCommand extends Command
{
    protected $signature = 'crm:map-stage-statuses
        {--apply : Actually save the mapping (default is a dry run)}
        {--seed : Also add a stage for every standard status a pipeline is missing}
        {--business= : Only this business id}';

    protected $description = 'Map existing CRM pipeline stages to the standard statuses (won → won, lost → lost, first stage → new, others → in process)';

    public function handle(LeadStageService $stages): int
    {
        $business = $this->option('business') !== null ? (int) $this->option('business') : null;
        $apply    = (bool) $this->option('apply');

        $projects = Project::query()
            ->when($business !== null, fn ($q) => $q->where('business_id', $business))
            ->orderBy('business_id')
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($projects as $project) {
            $report = $apply
                ? DB::transaction(fn () => $this->option('seed') ? $stages->seedStandard($project) : $stages->mapExisting($project))
                : $stages->mapExisting($project, pretend: true);

            foreach ($report as $r) {
                $rows[] = [
                    $project->business_id,
                    $project->id . ' ' . $project->name,
                    $r['stage_id'] . ' ' . $r['name'],
                    $r['system_key'] . ' (' . LeadStage::statusLabel($r['system_key']) . ')',
                    $r['action'],
                ];
            }
        }

        $this->info(count($projects) . ' pipeline(s) checked' . ($business !== null ? " (business {$business})" : '') . ', ' . count($rows) . ' stage(s) ' . ($apply ? 'changed' : 'to map') . '.');
        if ($rows !== []) {
            $this->table(['business', 'pipeline', 'stage', 'status', 'action'], $rows);
        }

        if (! $apply) {
            $this->line('Dry run — nothing changed. Run again with --apply (add --seed to also create the missing standard stages).');
        }

        return self::SUCCESS;
    }
}
