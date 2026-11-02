<?php

namespace Modules\CRM\Services\Timeline;

use Modules\CRM\Models\Lead;

/**
 * A lead's unified timeline: every registered TimelineSource merged and sorted newest first.
 * The CRM registers its own sources in CRMServiceProvider; other modules add theirs with extend().
 */
class TimelineService
{
    /** @var array<string, TimelineSource> */
    private array $sources = [];

    /** @param iterable<TimelineSource> $sources */
    public function __construct(iterable $sources = [])
    {
        foreach ($sources as $source) {
            $this->extend($source);
        }
    }

    /** Add a source (or replace the one with the same key). */
    public function extend(TimelineSource $source): static
    {
        $this->sources[$source->key()] = $source;

        return $this;
    }

    /** @return list<array{key:string, label:string}> the filters the client can offer */
    public function types(): array
    {
        return array_values(array_map(
            fn (TimelineSource $s) => ['key' => $s->key(), 'label' => $s->label()],
            $this->sources,
        ));
    }

    /**
     * @param  string[]|null  $only  source keys to include; null = all
     * @return list<array<string, mixed>> newest first
     */
    public function forLead(Lead $lead, ?array $only = null, int $limit = 300): array
    {
        $entries = [];
        foreach ($this->sources as $key => $source) {
            if ($only !== null && ! in_array($key, $only, true)) {
                continue;
            }
            foreach ($source->entries($lead) as $entry) {
                $entries[] = $entry;
            }
        }

        usort($entries, fn (TimelineEntry $a, TimelineEntry $b) =>
            [$b->at?->getTimestamp() ?? 0, $b->type, $b->id] <=> [$a->at?->getTimestamp() ?? 0, $a->type, $a->id]);

        return array_map(fn (TimelineEntry $e) => $e->toArray(), array_slice($entries, 0, $limit));
    }
}
