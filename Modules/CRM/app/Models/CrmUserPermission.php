<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class CrmUserPermission extends Model
{
    protected $table = 'crm_user_permissions';

    protected $fillable = [
        'business_id',
        'user_id',
        'permissions',
        'stage_access',
    ];

    protected function casts(): array
    {
        return [
            'permissions'  => 'array',
            'stage_access' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
