<?php

namespace Modules\CRM\Services\Timeline;

use Illuminate\Support\Carbon;

/** One row of a lead's timeline: what happened, when and who did it. */
final class TimelineEntry
{
    /**
     * @param  string       $type   the source key (TimelineSource::key)
     * @param  string       $icon   Font Awesome icon class, e.g. "fa-comment"
     * @param  array<string, mixed>  $meta  source-specific extras for the client
     */
    public function __construct(
        public readonly string $type,
        public readonly int|string $id,
        public readonly ?Carbon $at,
        public readonly string $title,
        public readonly ?string $body = null,
        public readonly ?int $userId = null,
        public readonly ?string $userName = null,
        public readonly string $icon = 'fa-circle',
        public readonly string $color = '#64748b',
        public readonly array $meta = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key'   => $this->type . ':' . $this->id,
            'type'  => $this->type,
            'id'    => $this->id,
            'at'    => $this->at?->toIso8601String(),
            'title' => $this->title,
            'body'  => $this->body,
            'user'  => $this->userId !== null || $this->userName !== null
                ? ['id' => $this->userId, 'name' => $this->userName ?? 'Deleted user']
                : null,
            'icon'  => $this->icon,
            'color' => $this->color,
            'meta'  => $this->meta ?: (object) [],
        ];
    }
}
