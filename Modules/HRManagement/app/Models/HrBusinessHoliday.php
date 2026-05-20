<?php

namespace Modules\HRManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class HrBusinessHoliday extends Model
{
    protected $table = 'hr_business_holidays';

    protected $fillable = [
        'name',
        'holiday_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
