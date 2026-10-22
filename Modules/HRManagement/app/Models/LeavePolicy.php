<?php

namespace Modules\HRManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

/**
 * A named leave rule ("Leave Rules" tab in the desktop HR panel): yearly annual / casual / sick
 * entitlements plus optional short-leave allowance, half-day, carry-forward and probation settings.
 */
class LeavePolicy extends Model
{
    protected $table = 'hr_leave_policies';

    protected $fillable = [
        'business_id',
        'name',
        'annual_leave_days',
        'casual_leave_days',
        'sick_leave_days',
        'short_leave_enabled',
        'short_leaves_per_month',
        'short_leave_max_minutes',
        'half_day_allowed',
        'carry_forward_max_days',
        'eligible_after_months',
        'is_default',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'annual_leave_days'       => 'integer',
            'casual_leave_days'       => 'integer',
            'sick_leave_days'         => 'integer',
            'short_leave_enabled'     => 'boolean',
            'short_leaves_per_month'  => 'integer',
            'short_leave_max_minutes' => 'integer',
            'half_day_allowed'        => 'boolean',
            'carry_forward_max_days'  => 'integer',
            'eligible_after_months'   => 'integer',
            'is_default'              => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
