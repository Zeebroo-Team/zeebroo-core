<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\FileManager\Models\FileManagerFile;

/** A file on a lead; the bytes live in the FileManager (see LeadAttachmentService). */
class LeadAttachment extends Model
{
    protected $table = 'crm_lead_attachments';

    protected $fillable = [
        'business_id',
        'lead_id',
        'message_id',
        'file_manager_file_id',
        'uploaded_by',
        'is_image',
    ];

    protected $casts = [
        'is_image' => 'boolean',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** The internal message the file was shared in, if any. */
    public function message(): BelongsTo
    {
        return $this->belongsTo(LeadMessage::class, 'message_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(FileManagerFile::class, 'file_manager_file_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }
}
