<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\Project;

class LeadStageService
{
    public function listForProject(Project $project): Collection
    {
        $this->ensureDefaults($project);

        return LeadStage::query()
            ->where('project_id', $project->id)
            ->orderBy('sort_order')
            ->get();
    }

    /** A pipeline with no stages at all gets the standard statuses. */
    public function ensureDefaults(Project $project): void
    {
        if (LeadStage::query()->where('project_id', $project->id)->exists()) {
            return;
        }

        $this->seedStandard($project);
    }

    /**
     * Give the pipeline one stage for every standard status. Unmapped stages are mapped first
     * (by name, then by the legacy rules in mapExisting()), and only statuses still missing get
     * a new stage, slotted in after the stage of the status before it.
     *
     * @return list<array{stage_id:int,name:string,system_key:string,action:string}>
     */
    public function seedStandard(Project $project): array
    {
        return DB::transaction(function () use ($project) {
            $report = $this->mapByName($project);
            $report = array_merge($report, $this->mapExisting($project));

            $ordered = LeadStage::query()->where('project_id', $project->id)->orderBy('sort_order')->orderBy('id')->get()->values();
            $keys    = array_keys(LeadStage::STATUSES);
            $created = false;

            foreach ($keys as $i => $key) {
                if ($ordered->contains('system_key', $key)) {
                    continue;
                }

                [$label, $color] = LeadStage::STATUSES[$key];
                $stage = LeadStage::create([
                    'project_id'      => $project->id,
                    'name'            => $this->freeStageName($project, $label),
                    'system_key'      => $key,
                    'color'           => $color,
                    'is_won'          => $key === LeadStage::STATUS_WON,
                    'is_lost'         => $key === LeadStage::STATUS_LOST,
                    'requires_reason' => $key === LeadStage::STATUS_LOST,
                    'sort_order'      => 0,
                ]);

                // After the last stage that carries one of the earlier statuses
                $earlier = array_slice($keys, 0, $i);
                $after   = -1;
                foreach ($ordered as $pos => $s) {
                    if (in_array($s->system_key, $earlier, true)) {
                        $after = $pos;
                    }
                }
                $ordered->splice($after + 1, 0, [$stage]);

                $created  = true;
                $report[] = ['stage_id' => $stage->id, 'name' => $stage->name, 'system_key' => $key, 'action' => 'created'];
            }

            if ($created) {
                foreach ($ordered->values() as $pos => $s) {
                    LeadStage::whereKey($s->id)->update(['sort_order' => $pos + 1]);
                }
            }

            return $report;
        });
    }

    /**
     * Map the pipeline's unmapped stages onto the standard statuses: won → won, lost → lost, the
     * first open stage → new (when no stage is "new" yet), every other open stage → in process.
     *
     * @return list<array{stage_id:int,name:string,system_key:string,action:string}>
     */
    public function mapExisting(Project $project, bool $pretend = false): array
    {
        $stages = LeadStage::query()->where('project_id', $project->id)->orderBy('sort_order')->orderBy('id')->get();
        $hasNew = $stages->contains('system_key', LeadStage::STATUS_NEW);
        $first  = $stages->first(fn (LeadStage $s) => ! $s->is_won && ! $s->is_lost);
        $report = [];

        foreach ($stages->whereNull('system_key') as $stage) {
            $key = match (true) {
                $stage->is_won                        => LeadStage::STATUS_WON,
                $stage->is_lost                       => LeadStage::STATUS_LOST,
                ! $hasNew && $stage->is($first)       => LeadStage::STATUS_NEW,
                default                               => LeadStage::STATUS_IN_PROCESS,
            };

            if (! $pretend) {
                $stage->update(['system_key' => $key, 'requires_reason' => $stage->requires_reason || $key === LeadStage::STATUS_LOST]);
            }

            $report[] = ['stage_id' => $stage->id, 'name' => $stage->name, 'system_key' => $key, 'action' => 'mapped'];
        }

        return $report;
    }

    public function create(Project $project, array $data): LeadStage
    {
        $nextOrder = (int) LeadStage::query()->where('project_id', $project->id)->max('sort_order') + 1;

        return LeadStage::create([
            'project_id' => $project->id,
            'name'       => $data['name'],
            'color'      => filled($data['color'] ?? '') ? $data['color'] : '#64748b',
            'sort_order' => $nextOrder,
        ] + $this->statusAttributes($data, null));
    }

    public function update(LeadStage $stage, array $data): LeadStage
    {
        $stage->update([
            'name'    => $data['name'],
            'color'   => filled($data['color'] ?? '') ? $data['color'] : $stage->color,
        ] + $this->statusAttributes($data, $stage));

        return $stage->fresh();
    }

    public function delete(LeadStage $stage): void
    {
        if ($stage->leads()->exists()) {
            throw ValidationException::withMessages(['stage' => 'Move or remove leads in this stage before deleting it.']);
        }

        $stage->delete();
    }

    public function reorder(Project $project, array $ids): void
    {
        foreach ($ids as $order => $id) {
            LeadStage::query()
                ->where('id', $id)
                ->where('project_id', $project->id)
                ->update(['sort_order' => $order + 1]);
        }
    }

    public function stageForProject(Project $project, LeadStage $stage): ?LeadStage
    {
        return $stage->project_id === $project->id ? $stage : null;
    }

    /** Where new and reopened leads land: the "new" stage, else the first open stage. */
    public function defaultOpenStage(Project $project): ?LeadStage
    {
        $this->ensureDefaults($project);

        return $this->stageForStatus($project, LeadStage::STATUS_NEW)
            ?? LeadStage::query()
                ->where('project_id', $project->id)
                ->where('is_won', false)
                ->where('is_lost', false)
                ->orderBy('sort_order')
                ->first();
    }

    public function wonStage(Project $project): ?LeadStage
    {
        $this->ensureDefaults($project);

        return LeadStage::query()
            ->where('project_id', $project->id)
            ->where('is_won', true)
            ->orderBy('sort_order')
            ->first();
    }

    public function lostStage(Project $project): ?LeadStage
    {
        $this->ensureDefaults($project);

        return LeadStage::query()
            ->where('project_id', $project->id)
            ->where('is_lost', true)
            ->orderBy('sort_order')
            ->first();
    }

    /** The pipeline's first stage carrying this standard status. */
    public function stageForStatus(Project $project, string $key): ?LeadStage
    {
        return LeadStage::query()
            ->where('project_id', $project->id)
            ->where('system_key', $key)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * system_key, is_won, is_lost and requires_reason kept in step: the won/lost statuses set
     * the flags, and callers that only send the flags (older desktop builds) set the status.
     * Lost stages always require a reason.
     */
    private function statusAttributes(array $data, ?LeadStage $stage): array
    {
        $keyGiven = array_key_exists('system_key', $data);
        $key      = $keyGiven ? ($data['system_key'] ?: null) : $stage?->system_key;
        $isWon    = (bool) ($data['is_won'] ?? false);
        $isLost   = (bool) ($data['is_lost'] ?? false);

        if ($key !== null && ! array_key_exists($key, LeadStage::STATUSES)) {
            throw ValidationException::withMessages(['system_key' => 'Unknown status.']);
        }

        if ($key === null || ! $keyGiven) {
            $key = match (true) {
                $isWon  => LeadStage::STATUS_WON,
                $isLost => LeadStage::STATUS_LOST,
                // The flag was unticked on a won/lost stage: it is an ordinary open stage now
                in_array($key, [LeadStage::STATUS_WON, LeadStage::STATUS_LOST], true) => LeadStage::STATUS_IN_PROCESS,
                default => $key,
            };
        }

        $requiresReason = array_key_exists('requires_reason', $data)
            ? (bool) $data['requires_reason']
            : (bool) $stage?->requires_reason;

        return [
            'system_key'      => $key,
            'is_won'          => $key === LeadStage::STATUS_WON,
            'is_lost'         => $key === LeadStage::STATUS_LOST,
            'requires_reason' => $requiresReason || $key === LeadStage::STATUS_LOST,
        ];
    }

    /** Unmapped stages named like a standard status ("Won", "negotiation", …) take that status. */
    private function mapByName(Project $project): array
    {
        $byLabel = [];
        foreach (LeadStage::STATUSES as $key => [$label]) {
            $byLabel[mb_strtolower($label)] = $key;
        }

        $taken  = LeadStage::query()->where('project_id', $project->id)->whereNotNull('system_key')->pluck('system_key')->all();
        $report = [];

        foreach (LeadStage::query()->where('project_id', $project->id)->whereNull('system_key')->orderBy('sort_order')->get() as $stage) {
            $key = $byLabel[mb_strtolower(trim($stage->name))] ?? null;
            if ($key === null || in_array($key, $taken, true)) {
                continue;
            }
            // A stage flagged won/lost keeps that meaning whatever it is called
            if (($stage->is_won && $key !== LeadStage::STATUS_WON) || ($stage->is_lost && $key !== LeadStage::STATUS_LOST)) {
                continue;
            }

            $stage->update($this->statusAttributes(['system_key' => $key], $stage));
            $taken[]  = $key;
            $report[] = ['stage_id' => $stage->id, 'name' => $stage->name, 'system_key' => $key, 'action' => 'mapped'];
        }

        return $report;
    }

    private function freeStageName(Project $project, string $base): string
    {
        $name = $base;
        for ($i = 2; LeadStage::where('project_id', $project->id)->where('name', $name)->exists(); $i++) {
            $name = $base . ' (' . $i . ')';
        }

        return $name;
    }
}
