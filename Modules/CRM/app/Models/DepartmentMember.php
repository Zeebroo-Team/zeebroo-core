<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Department;

/**
 * A user's extra place in a department for the CRM, beside their HR employee record: a
 * member works the department's leads like its own staff, a head runs it like the HR head.
 */
class DepartmentMember extends Model
{
    protected $table = 'crm_department_members';

    const ROLE_MEMBER = 'member';
    const ROLE_HEAD   = 'head';

    const ROLES = [self::ROLE_MEMBER, self::ROLE_HEAD];

    protected $fillable = [
        'business_id',
        'department_id',
        'user_id',
        'role',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
