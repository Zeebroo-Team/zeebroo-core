<?php

namespace Modules\CRM\Services\Timeline;

use Illuminate\Support\Str;
use Modules\CRM\Models\Lead;

final class MessageTimelineSource implements TimelineSource
{
    public function key(): string { return 'message'; }

    public function label(): string { return 'Messages'; }

    public function entries(Lead $lead): iterable
    {
        foreach ($lead->messages()->with('user:id,name')->get() as $m) {
            yield new TimelineEntry('message', $m->id, $m->created_at, $m->parent_id ? 'Replied' : 'Posted a message',
                Str::limit($m->body, 280), $m->user_id, $m->user?->name, 'fa-comment', '#7c3aed', ['parent_id' => $m->parent_id]);
        }
    }
}
