<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HRManagement\Models\Department;

/**
 * One lead handed to one user on one day (the business's calendar day) — the daily allocation
 * record the per-user limit counts. Written by InquiryAllocationService from
 * LeadAssignmentService; a same-day un-assignment sets released_at and frees the slot.
 */
class InquiryAllocation extends Model
{
    protected $table = 'crm_inquiry_allocations';

    protected $fillable = [
        'business_id',
        'department_id',
        'lead_id',
        'user_id',
        'allocation_date',
        'assigned_by',
        'over_limit',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'allocation_date' => 'date',
            'over_limit'      => 'boolean',
            'released_at'     => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('released_at');
    }
}
