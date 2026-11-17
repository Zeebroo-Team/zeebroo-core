<?php

namespace Modules\ProjectManage\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ProjectManage\Models\Milestone;
use Modules\ProjectManage\Models\Project;
use Modules\ProjectManage\Models\Task;

class MilestoneService
{
    /** Milestones in display order (sort number, then start/due date), with task counts. */
    public function listForProject(Project $project): Collection
    {
        return Milestone::query()
            ->where('project_id', $project->id)
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn ($q) => $q->where('completion_status', Task::COMPLETION_COMPLETE),
            ])
            ->orderBy('sort_order')
            ->orderByRaw('start_date IS NULL')
            ->orderBy('start_date')
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Splits tasks into one group per milestone (display order) plus a trailing
     * "No Milestone" group (milestone = null) for tasks without one.
     *
     * @return array<int, array{milestone: ?Milestone, tasks: Collection}>
     */
    public function groupTasks(Collection $milestones, Collection $tasks): array
    {
        $ids     = $milestones->pluck('id')->map(fn ($id) => (int) $id)->all();
        $byGroup = $tasks->groupBy(fn (Task $t) => in_array((int) $t->milestone_id, $ids, true) ? (int) $t->milestone_id : 0);

        $groups = $milestones->map(fn (Milestone $m) => [
            'milestone' => $m,
            'tasks'     => $byGroup->get((int) $m->id, collect())->values(),
        ])->values()->all();

        $groups[] = ['milestone' => null, 'tasks' => $byGroup->get(0, collect())->values()];

        return $groups;
    }

    /** Validation rules for creating / updating a milestone (shared by web + API). */
    public static function rules(bool $partial = false): array
    {
        return [
            'name'        => [$partial ? 'sometimes' : 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date'  => ['nullable', 'date'],
            'due_date'    => ['nullable', 'date'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /** The period's end must not be before its start (either side may be open). */
    private function assertPeriod(?string $start, ?string $due): void
    {
        if ($start && $due && Carbon::parse($due)->lt(Carbon::parse($start))) {
            throw ValidationException::withMessages(['due_date' => 'The end date must be on or after the start date.']);
        }
    }

    public function create(Project $project, array $data): Milestone
    {
        $this->assertPeriod($data['start_date'] ?? null, $data['due_date'] ?? null);

        // Default: after the last milestone of the project.
        $defaultSort = (int) Milestone::where('project_id', $project->id)->max('sort_order') + 1;

        return Milestone::create([
            'project_id'  => $project->id,
            'name'        => $data['name'],
            'description' => filled($data['description'] ?? '') ? $data['description'] : null,
            'start_date'  => filled($data['start_date'] ?? '') ? $data['start_date'] : null,
            'due_date'    => filled($data['due_date'] ?? '') ? $data['due_date'] : null,
            'sort_order'  => filled($data['sort_order'] ?? '') ? (int) $data['sort_order'] : $defaultSort,
            'status'      => Milestone::STATUS_PENDING,
        ]);
    }

    /** Updates only the fields present in $data. */
    public function update(Milestone $milestone, array $data): Milestone
    {
        $updates = [];
        if (filled($data['name'] ?? '')) {
            $updates['name'] = $data['name'];
        }
        foreach (['description', 'start_date', 'due_date'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = filled($data[$field]) ? $data[$field] : null;
            }
        }
        if (filled($data['sort_order'] ?? '')) {
            $updates['sort_order'] = (int) $data['sort_order'];
        }

        // A partial update may send only one side of the period — check it against the stored other side.
        $this->assertPeriod(
            array_key_exists('start_date', $updates) ? $updates['start_date'] : $milestone->start_date?->toDateString(),
            array_key_exists('due_date', $updates)   ? $updates['due_date']   : $milestone->due_date?->toDateString(),
        );

        $milestone->update($updates);

        return $milestone->fresh();
    }

    /**
     * Renumbers the project's milestones 1..n in the given id order.
     * Ids not belonging to the project are ignored; milestones not listed keep their place after the listed ones.
     *
     * @param int[] $ids
     */
    public function reorder(Project $project, array $ids): void
    {
        DB::transaction(function () use ($project, $ids) {
            $owned = Milestone::where('project_id', $project->id)->pluck('id')->all();
            $ordered = array_values(array_unique(array_intersect(array_map('intval', $ids), $owned)));
            $rest    = array_values(array_diff($owned, $ordered));

            foreach (array_merge($ordered, $rest) as $i => $id) {
                Milestone::where('id', $id)->update(['sort_order' => $i + 1]);
            }
        });
    }

    public function complete(Milestone $milestone): Milestone
    {
        $milestone->update([
            'status'       => Milestone::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return $milestone;
    }

    public function reopen(Milestone $milestone): Milestone
    {
        $milestone->update([
            'status'       => Milestone::STATUS_PENDING,
            'completed_at' => null,
        ]);

        return $milestone;
    }

    /** Deletes the milestone; its tasks stay in the project without a milestone. */
    public function delete(Milestone $milestone): void
    {
        DB::transaction(function () use ($milestone) {
            Task::where('milestone_id', $milestone->id)->update(['milestone_id' => null]);
            $milestone->delete();
        });
    }
}
