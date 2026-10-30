<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Department;

class Division extends Model
{
    protected $table = 'crm_divisions';

    protected $fillable = [
        'business_id',
        'department_id',
        'name',
        'head_user_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function headUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }
}
