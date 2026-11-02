<?php

namespace Modules\CRM\Services\Timeline;

use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadStageLog;

final class StageTimelineSource implements TimelineSource
{
    public function key(): string { return 'stage'; }

    public function label(): string { return 'Stage moves'; }

    public function entries(Lead $lead): iterable
    {
        $logs = LeadStageLog::query()->where('lead_id', $lead->id)->with(['fromStage:id,name,color', 'toStage:id,name,color', 'changedBy:id,name'])->get();

        foreach ($logs as $log) {
            $title = $log->from_stage_id === null
                ? 'Entered ' . ($log->toStage?->name ?? 'a stage')
                : 'Moved from ' . ($log->fromStage?->name ?? 'a deleted stage') . ' to ' . ($log->toStage?->name ?? 'a deleted stage');
            yield new TimelineEntry('stage', $log->id, $log->created_at, $title, $log->reason,
                $log->changed_by, $log->changedBy?->name, 'fa-arrow-right-arrow-left', $log->toStage?->color ?: '#64748b',
                ['from_stage_id' => $log->from_stage_id, 'to_stage_id' => $log->to_stage_id]);
        }
    }
}
