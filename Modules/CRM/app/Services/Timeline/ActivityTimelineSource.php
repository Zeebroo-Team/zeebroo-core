<?php

namespace Modules\CRM\Services\Timeline;

use Modules\CRM\Models\Activity;
use Modules\CRM\Models\Lead;

final class ActivityTimelineSource implements TimelineSource
{
    private const ICONS = [
        Activity::TYPE_NOTE    => ['fa-note-sticky', '#64748b'],
        Activity::TYPE_CALL    => ['fa-phone', '#14b8a6'],
        Activity::TYPE_EMAIL   => ['fa-envelope', '#0ea5e9'],
        Activity::TYPE_MEETING => ['fa-handshake', '#8b5cf6'],
        Activity::TYPE_ENQUIRY => ['fa-inbox', '#f59e0b'],
    ];

    public function key(): string { return 'activity'; }

    public function label(): string { return 'Activities'; }

    public function entries(Lead $lead): iterable
    {
        foreach ($lead->activities()->with('createdBy:id,name')->get() as $a) {
            [$icon, $color] = self::ICONS[$a->type] ?? ['fa-circle', '#64748b'];
            yield new TimelineEntry('activity', $a->id, $a->occurred_at ?? $a->created_at, $a->typeLabel(), $a->body,
                $a->created_by, $a->createdBy?->name, $icon, $color, ['activity_type' => $a->type]);
        }
    }
}
