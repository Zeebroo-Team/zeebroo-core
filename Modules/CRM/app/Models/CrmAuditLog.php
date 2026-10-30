<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only row-level audit of CRM records, written by the Auditable trait. Rows are never
 * updated — see CrmAuditService.
 */
class CrmAuditLog extends Model
{
    protected $table = 'crm_audit_logs';

    const UPDATED_AT = null;

    const EVENT_CREATED = 'created';
    const EVENT_UPDATED = 'updated';
    const EVENT_DELETED = 'deleted';

    /** Audited models: short key used in filters and the API => [class, label]. */
    const SUBJECTS = [
        'lead'          => [Lead::class, 'Lead'],
        'lead_assignee' => [LeadAssignee::class, 'Lead assignee'],
    ];

    protected $fillable = [
        'business_id',
        'user_id',
        'subject_type',
        'subject_id',
        'event',
        'old',
        'new',
        'ip',
    ];

    protected $casts = [
        'old'        => 'array',
        'new'        => 'array',
        'created_at' => 'datetime',
    ];

    public static function events(): array
    {
        return [
            self::EVENT_CREATED => 'Created',
            self::EVENT_UPDATED => 'Updated',
            self::EVENT_DELETED => 'Deleted',
        ];
    }

    /** The SUBJECTS key for a stored subject_type, or null for a model not listed there. */
    public static function subjectKey(string $type): ?string
    {
        foreach (self::SUBJECTS as $key => [$class]) {
            if ($class === $type) {
                return $key;
            }
        }

        return null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
