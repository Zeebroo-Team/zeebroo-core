<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A bulk lead upload: the stored file, its column mapping and what the import did. */
class LeadImport extends Model
{
    protected $table = 'crm_lead_imports';

    /** Where the file came from: key => [label, default lead source]. */
    public const FORMATS = [
        'template'    => ['CSV template',        'csv'],
        'google_form' => ['Google Forms export', 'google-form'],
        'facebook'    => ['Facebook Lead Ads export', 'facebook'],
    ];

    public const STATUS_UPLOADED   = 'uploaded';
    public const STATUS_PREVIEWED  = 'previewed';
    public const STATUS_QUEUED     = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    /** Not run yet: the mapping can still change and the import can be discarded. */
    public const PENDING_STATUSES = [self::STATUS_UPLOADED, self::STATUS_PREVIEWED];

    protected $fillable = [
        'business_id',
        'user_id',
        'project_id',
        'file_name',
        'file_path',
        'format',
        'source',
        'mapping',
        'options',
        'status',
        'total_rows',
        'processed_rows',
        'created_count',
        'duplicate_count',
        'skipped_count',
        'failed_count',
        'error_rows',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'mapping'     => 'array',
        'options'     => 'array',
        'error_rows'  => 'array',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, self::PENDING_STATUSES, true);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_PROCESSING], true);
    }

    public static function formatLabel(?string $format): string
    {
        return self::FORMATS[$format][0] ?? 'CSV';
    }
}
