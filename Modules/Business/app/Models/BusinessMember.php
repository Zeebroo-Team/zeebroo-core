<?php

namespace Modules\Business\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessMember extends Model
{
    protected $table = 'business_members';

    protected $fillable = [
        'business_id',
        'user_id',
        'role',
        'permissions',
        'status',
        'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
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

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin'   => 'Admin',
            'manager' => 'Manager',
            'staff'   => 'Staff',
            default   => ucfirst($this->role),
        };
    }

    public function roleBadgeColor(): string
    {
        return match ($this->role) {
            'admin'   => '#6366f1',
            'manager' => '#0ea5e9',
            'staff'   => '#64748b',
            default   => '#64748b',
        };
    }

    /** All available module permission keys. */
    public static function availablePermissions(): array
    {
        return [
            'account_management'   => 'Account Management',
            'bill_management'      => 'Bill Management',
            'human_resources'      => 'Human Resources',
            'point_of_sale'        => 'Point of Sale',
            'product_management'   => 'Product Management',
            'service_management'   => 'Service Management',
            'social_media_campaign'=> 'Social Media Campaign',
            'stock_management'     => 'Stock Management',
        ];
    }

    public function hasPermission(string $key): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        $perms = $this->permissions ?? [];

        return in_array($key, $perms, true);
    }
}
