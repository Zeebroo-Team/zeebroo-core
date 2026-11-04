<?php

namespace Modules\CRM\Services;

use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Models\LeadStageLog;

/**
 * Carries out what a stage is set to do when a lead enters it (LeadStage::entryRules), run from
 * the LeadEnteredStage event after the move is saved: add the chosen people to the lead, plan
 * the follow-up asked for on the move, create the stage's task and tell the chosen people.
 * Each step stands alone — one failing never stops the others (the caller guards each).
 */
class StageEntryActionService
{
    public function __construct(
        private readonly LeadAssignmentService $assignments,
        private readonly FollowUpService $followUps,
        private readonly TaskService $tasks,
        private readonly CrmNotifier $notifier,
    ) {}

    /**
     * @return list<callable> the steps to run, in order (assign first, so a lead with no owner
     *                        gets one before its task is made)
     */
    public function steps(Lead $lead, LeadStage $stage, ?int $byUserId, ?string $followUpAt): array
    {
        $rules = $stage->entryRules();
        $steps = [];

        // The picked people join the lead; its owner stays, or the first picked becomes it
        if (array_diff($rules['assign_user_ids'], $this->assignments->assigneeIds($lead)) !== []) {
            $steps[] = fn () => $this->assignments->add($lead->fresh(), $rules['assign_user_ids'], $byUserId,
                'Added when the lead entered "' . $stage->name . '".');
        }
        if ($followUpAt !== null) {
            $steps[] = fn () => $this->followUps->create($lead->fresh(), [
                'due_at' => $followUpAt,
                'notes'  => 'Follow up — moved to "' . $stage->name . '"',
            ], $byUserId);
        }
        if ($rules['task'] !== null) {
            $steps[] = function () use ($lead, $stage, $rules, $byUserId) {
                $lead = $lead->fresh();
                $this->tasks->create($lead->business, [
                    'title'       => $rules['task']['title'],
                    'description' => 'Created when the lead entered "' . $stage->name . '".',
                    'due_at'      => now()->addDays($rules['task']['due_days'])->toDateTimeString(),
                    'assigned_to' => $lead->assigned_to ?? $byUserId,
                ], $lead, $byUserId);
            };
        }

        $notify = $rules['notify'];
        if ($notify['owner'] || $notify['head'] || $notify['user_ids'] !== []) {
            $steps[] = function () use ($lead, $stage, $notify, $byUserId) {
                $lead = $lead->fresh(['project']);
                $ids  = $notify['user_ids'];
                if ($notify['owner'] && $lead->assigned_to) {
                    $ids[] = (int) $lead->assigned_to;
                }
                if ($notify['head']) {
                    $ids[] = $this->notifier->headUserIdForLead($lead);
                }
                $this->notifier->notify($lead, array_filter($ids), CrmNotifier::TYPE_LEAD_STAGE,
                    'Lead moved to ' . $stage->name,
                    '"' . ($lead->company ? $lead->name . ' · ' . $lead->company : $lead->name) . '" is now in "' . $stage->name . '"'
                        . ($lead->project ? ' (' . $lead->project->name . ')' : '') . '.'
                        . ($stage->description ? "\n" . $stage->description : ''),
                    $byUserId, ['stage_id' => (int) $stage->id]);
            };
        }

        return $steps;
    }

    /**
     * When each lead entered the stage it is in now (its last move there, else its creation),
     * for the "too long in this stage" flag.
     *
     * @param  iterable<Lead>  $leads
     * @return array<int, string> ISO dates by lead id
     */
    public function enteredAt(iterable $leads): array
    {
        $leads = collect($leads);
        if ($leads->isEmpty()) {
            return [];
        }

        $moves = LeadStageLog::query()
            ->whereIn('lead_id', $leads->pluck('id'))
            ->selectRaw('lead_id, to_stage_id, max(created_at) as at')
            ->groupBy('lead_id', 'to_stage_id')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->lead_id . ':' . $r->to_stage_id => $r->at]);

        return $leads->mapWithKeys(fn (Lead $l) => [
            (int) $l->id => \Illuminate\Support\Carbon::parse($moves[$l->id . ':' . $l->stage_id] ?? $l->created_at)->toIso8601String(),
        ])->all();
    }
}
