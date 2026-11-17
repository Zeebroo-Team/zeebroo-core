<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Models\CustomStatus;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\Project;

class LeadStageService
{
    public function listForProject(Project $project): Collection
    {
        $this->ensureDefaults($project);

        return LeadStage::query()
            ->where('project_id', $project->id)
            ->with('customStatus')
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
     * Standardise a pipeline that has never been mapped (no stages, or stages without a status).
     * A pipeline whose stages all carry a status was already set up, so stages the user deleted
     * stay deleted.
     */
    public function seedIfUnmapped(Project $project): void
    {
        $stages = LeadStage::query()->where('project_id', $project->id);

        if ($stages->exists() && ! (clone $stages)->whereNull('system_key')->exists()) {
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
        $custom    = $this->customStatusAttributes($data, $project);

        return LeadStage::create([
            'project_id' => $project->id,
            'name'       => $data['name'],
            'color'      => filled($data['color'] ?? '') ? $data['color'] : '#64748b',
            'sort_order' => $nextOrder,
        ] + $custom + $this->behaviourAttributes($data) + $this->statusAttributes($data, null) + $this->lookAttributes($data) + $this->entryRulesAttributes($data, $project));
    }

    public function update(LeadStage $stage, array $data): LeadStage
    {
        $custom = $this->customStatusAttributes($data, $stage->project);

        $stage->update([
            'name'    => $data['name'],
            'color'   => filled($data['color'] ?? '') ? $data['color'] : $stage->color,
        ] + $custom + $this->behaviourAttributes($data) + $this->statusAttributes($data, $stage) + $this->lookAttributes($data) + $this->entryRulesAttributes($data, $stage->project));

        return $stage->fresh();
    }

    /**
     * A "Custom" stage and its ticks, only when the caller sent them. A custom stage counts as
     * one of LeadStage::CUSTOM_KEYS (open / won / lost / paused) and is never one of your own
     * statuses. Any stage may carry the ticks; an ordinary stage saved without them clears them.
     */
    private function behaviourAttributes(array $data): array
    {
        if (! array_key_exists('is_custom', $data)) {
            return $this->tickAttributes($data);
        }
        if (! (bool) $data['is_custom']) {
            return ['is_custom' => false] + $this->tickAttributes($data)
                + ['needs_approval' => false, 'locks_lead' => false, 'exit_checklist' => null, 'no_skip' => false];
        }

        if (! in_array($data['system_key'] ?? null, LeadStage::CUSTOM_KEYS, true)) {
            throw ValidationException::withMessages(['system_key' => 'A custom stage stays open, closes the lead as won or lost, or pauses it.']);
        }

        return ['is_custom' => true, 'custom_status_id' => null] + $this->tickAttributes($data);
    }

    /** Approval, lead lock, no skipping and the exit checklist, only those the caller sent. */
    private function tickAttributes(array $data): array
    {
        $attrs = [];
        foreach (['needs_approval', 'locks_lead', 'no_skip'] as $tick) {
            if (array_key_exists($tick, $data)) {
                $attrs[$tick] = (bool) $data[$tick];
            }
        }
        if (array_key_exists('exit_checklist', $data)) {
            $checklist = collect((array) ($data['exit_checklist'] ?? []))
                ->map(fn ($i) => mb_substr(trim((string) $i), 0, 120))
                ->filter(fn ($i) => $i !== '')
                ->unique()
                ->take(10)
                ->values()
                ->all();
            $attrs['exit_checklist'] = $checklist ?: null;
        }

        return $attrs;
    }

    /**
     * The stage's behaviour settings: built-in behaviours of its standard status switched on or
     * off (LeadStage::BUILT_IN_BEHAVIOURS), plus the ticks and the reason prompt any stage can add.
     * Keys the stage's status doesn't have, or that can't be switched off, are ignored.
     */
    public function updateBehaviours(LeadStage $stage, array $data): LeadStage
    {
        $attrs = $this->tickAttributes($data);

        if (array_key_exists('disabled_behaviours', $data)) {
            $off = array_values(array_intersect(LeadStage::switchableBehaviours($stage->system_key), (array) ($data['disabled_behaviours'] ?? [])));
            $attrs['disabled_behaviours'] = $off ?: null;
        }
        if (array_key_exists('requires_reason', $data)) {
            $attrs['requires_reason'] = (bool) $data['requires_reason'] || $stage->isLostStage();
        }

        $stage->update($attrs);

        return $stage->fresh();
    }

    /**
     * The business's own status the stage is made from, only when the caller sent it. The stage
     * then counts as that status's standard one, so $data's system_key is set to match.
     */
    private function customStatusAttributes(array &$data, Project $project): array
    {
        if (! array_key_exists('custom_status_id', $data)) {
            return [];
        }
        if (blank($data['custom_status_id'])) {
            return ['custom_status_id' => null];
        }

        $custom = CustomStatus::query()->where('business_id', $project->business_id)->find((int) $data['custom_status_id']);
        if ($custom === null) {
            throw ValidationException::withMessages(['custom_status_id' => 'That status no longer exists.']);
        }
        $data['system_key'] = $custom->base_key;

        return ['custom_status_id' => $custom->id];
    }

    /**
     * What happens when a lead enters the stage, only when the caller sent it. People picked for
     * auto-assign / notify must work the pipeline's department; a task needs a title.
     */
    private function entryRulesAttributes(array $data, ?Project $project): array
    {
        if (! array_key_exists('entry_rules', $data)) {
            return [];
        }

        return ['entry_rules' => $this->cleanEntryRules((array) ($data['entry_rules'] ?? []), $project)];
    }

    /**
     * Entry rules in the LeadStage::ENTRY_RULES_DEFAULT shape, or null when they do nothing.
     * People are checked against the pipeline's department when one is given.
     */
    public function cleanEntryRules(array $in, ?Project $project): ?array
    {
        $userIds  = array_values(array_unique(array_filter(array_map('intval', (array) ($in['notify']['user_ids'] ?? [])))));
        $assignIds = array_values(array_unique(array_filter(array_map('intval', (array) ($in['assign_user_ids'] ?? [])))));
        $picked    = array_values(array_unique([...$userIds, ...$assignIds]));
        if ($picked !== [] && $project !== null) {
            $project->loadMissing('business');
            $allowed = app(CrmOrgService::class)->assignableUsers($project->business, $project->department_id)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (array_diff($picked, $allowed) !== []) {
                throw ValidationException::withMessages(['entry_rules' => 'Only people who work this pipeline\'s department can be picked.']);
            }
        }

        $task = null;
        if ((bool) ($in['task']['enabled'] ?? filled($in['task']['title'] ?? null))) {
            $title = trim((string) ($in['task']['title'] ?? ''));
            if ($title === '') {
                throw ValidationException::withMessages(['entry_rules' => 'Give the follow-up task a title.']);
            }
            $task = ['title' => mb_substr($title, 0, 255), 'due_days' => max(0, min(365, (int) ($in['task']['due_days'] ?? 0)))];
        }

        $rules = [
            'require'   => array_values(array_intersect(array_keys(LeadStage::ENTRY_FIELDS), (array) ($in['require'] ?? []))),
            'task'      => $task,
            'notify'    => [
                'owner'    => (bool) ($in['notify']['owner'] ?? false),
                'head'     => (bool) ($in['notify']['head'] ?? false),
                'user_ids' => $userIds,
            ],
            'assign_user_ids' => $assignIds,
            'max_days'  => filled($in['max_days'] ?? null) && (int) $in['max_days'] > 0 ? min(365, (int) $in['max_days']) : null,
        ];

        return $rules === LeadStage::ENTRY_RULES_DEFAULT ? null : $rules;
    }

    /** Icon and description, only when the caller sent them; blank = fall back to the status's own. */
    private function lookAttributes(array $data): array
    {
        $attrs = [];
        foreach (['icon', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $attrs[$field] = filled($data[$field]) ? trim($data[$field]) : null;
            }
        }

        return $attrs;
    }

    public function delete(LeadStage $stage): void
    {
        if ($this->isProtected($stage)) {
            throw ValidationException::withMessages(['stage' => 'The "New" stage cannot be deleted — new leads land there.']);
        }

        if ($stage->leads()->exists()) {
            throw ValidationException::withMessages(['stage' => 'Move or remove leads in this stage before deleting it.']);
        }

        $stage->delete();
    }

    /**
     * Delete several stages of one pipeline. Stages that can't be deleted (protected or
     * still holding leads) are skipped and reported back instead of failing the batch.
     *
     * @param  list<int>  $ids
     * @return array{deleted:int, failed:list<array{id:int,name:string,message:string}>}
     */
    public function bulkDelete(Project $project, array $ids): array
    {
        $stages  = LeadStage::query()->where('project_id', $project->id)->whereIn('id', $ids)->get();
        $deleted = 0;
        $failed  = [];

        foreach ($stages as $stage) {
            try {
                $this->delete($stage);
                $deleted++;
            } catch (ValidationException $e) {
                $failed[] = ['id' => $stage->id, 'name' => $stage->name, 'message' => $e->errors()['stage'][0] ?? 'Cannot delete stage.'];
            }
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }

    /** The "New" stage is where leads land, so the pipeline's only stage carrying that status is kept. */
    public function isProtected(LeadStage $stage): bool
    {
        return $stage->system_key === LeadStage::STATUS_NEW
            && ! LeadStage::query()
                ->where('project_id', $stage->project_id)
                ->where('system_key', LeadStage::STATUS_NEW)
                ->where('id', '!=', $stage->id)
                ->exists();
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
