<?php

namespace Modules\CRM\Services\Timeline;

use Modules\CRM\Models\Lead;

/**
 * One kind of thing that shows on a lead's timeline (activities, stage moves, messages, …).
 * Register more with TimelineService::extend() — e.g. quotations or follow-ups from a module's
 * service provider:
 *
 *     $this->app->afterResolving(TimelineService::class, fn ($t) => $t->extend(new QuotationTimelineSource()));
 */
interface TimelineSource
{
    /** Short key the client filters on, e.g. "activity". */
    public function key(): string;

    /** Name of the filter, e.g. "Activities". */
    public function label(): string;

    /** @return iterable<TimelineEntry> */
    public function entries(Lead $lead): iterable;
}
