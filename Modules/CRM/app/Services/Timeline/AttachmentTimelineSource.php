<?php

namespace Modules\CRM\Services\Timeline;

use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadAttachment;

final class AttachmentTimelineSource implements TimelineSource
{
    public function key(): string { return 'file'; }

    public function label(): string { return 'Files'; }

    public function entries(Lead $lead): iterable
    {
        foreach ($lead->attachments()->with(['file:id,original_filename,size_bytes', 'uploadedBy:id,name'])->get() as $a) {
            /** @var LeadAttachment $a */
            yield new TimelineEntry('file', $a->id, $a->created_at, 'Added ' . ($a->is_image ? 'an image' : 'a file'),
                $a->file?->original_filename, $a->uploaded_by, $a->uploadedBy?->name,
                $a->is_image ? 'fa-image' : 'fa-paperclip', '#16a34a', ['attachment_id' => $a->id, 'is_image' => (bool) $a->is_image]);
        }
    }
}
