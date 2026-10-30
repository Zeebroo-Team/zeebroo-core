<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HRManagement\Models\Department;

/** Append-only history of where a lead was sent and who works it. */
class LeadAssignmentLog extends Model
{
    protected $table = 'crm_lead_assignment_logs';

    const UPDATED_AT = null;

    const ACTION_FORWARDED  = 'forwarded';
    const ACTION_ASSIGNED   = 'assigned';
    const ACTION_REASSIGNED = 'reassigned';
    const ACTION_UNASSIGNED = 'unassigned';

    protected $fillable = [
        'business_id',
        'lead_id',
        'action',
        'from_department_id',
        'to_department_id',
        'from_division_id',
        'to_division_id',
        'user_ids',
        'by_user_id',
        'note',
    ];

    protected $casts = [
        'user_ids'   => 'array',
        'created_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function byUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'by_user_id');
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function fromDivision(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'from_division_id');
    }

    public function toDivision(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'to_division_id');
    }
}
