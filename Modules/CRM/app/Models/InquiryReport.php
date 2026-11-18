<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HRManagement\Models\Department;

/**
 * A department's daily inquiry status report (InquiryReportService): per-user and department
 * totals for one day. Draft while it waits for the head's review, then sent to management.
 */
class InquiryReport extends Model
{
    protected $table = 'crm_inquiry_reports';

    const STATUS_DRAFT  = 'draft';
    const STATUS_SENT   = 'sent';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'business_id',
        'department_id',
        'report_date',
        'status',
        'data',
        'recipients',
        'generated_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'sent_by',
        'sent_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'report_date'  => 'date',
            'data'         => 'array',
            'recipients'   => 'array',
            'generated_at' => 'datetime',
            'reviewed_at'  => 'datetime',
            'sent_at'      => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
