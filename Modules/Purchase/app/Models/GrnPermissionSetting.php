<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class GrnPermissionSetting extends Model
{
    protected $table = 'grn_permission_settings';

    protected $fillable = [
        'business_id',
        'approval_mode',
        'role_permissions',
    ];

    protected function casts(): array
    {
        return [
            'role_permissions' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** Find-or-create the settings row for a business. */
    public static function forBusiness(int $businessId): self
    {
        return static::firstOrCreate(
            ['business_id' => $businessId],
            ['approval_mode' => 'without_permission', 'role_permissions' => null],
        );
    }

    /** True when stock must not be applied immediately. */
    public function requiresApproval(): bool
    {
        return $this->approval_mode === 'approval_processing';
    }

    /** Whether a role slug may create GRNs. Defaults true (no restriction). */
    public function roleCanCreate(string $roleSlug): bool
    {
        $perms = $this->role_permissions ?? [];
        return (bool) ($perms[$roleSlug]['create'] ?? true);
    }

    /** Whether a role slug may read/view GRNs. Defaults true. */
    public function roleCanRead(string $roleSlug): bool
    {
        $perms = $this->role_permissions ?? [];
        return (bool) ($perms[$roleSlug]['read'] ?? true);
    }

    /** Whether a role slug may approve pending GRNs. Defaults false. */
    public function roleCanApprove(string $roleSlug): bool
    {
        $perms = $this->role_permissions ?? [];
        return (bool) ($perms[$roleSlug]['approval'] ?? false);
    }
}
