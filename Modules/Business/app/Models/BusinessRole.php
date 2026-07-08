<?php

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessRole extends Model
{
    protected $table = 'business_roles';

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'color',
        'description',
        'permissions',
        'is_system',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_system'   => 'boolean',
            'sort_order'  => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** Seed the three built-in roles for a business (idempotent). */
    public static function seedForBusiness(int $businessId): void
    {
        $defaults = [
            [
                'slug'        => 'admin',
                'name'        => 'Admin',
                'color'       => '#0ea5e9',
                'description' => 'Full access to all modules.',
                'permissions' => null,   // null = all permissions granted
                'is_system'   => true,
                'sort_order'  => 1,
            ],
            [
                'slug'        => 'manager',
                'name'        => 'Manager',
                'color'       => '#f59e0b',
                'description' => 'Full POS, inventory, and basic finance access.',
                'permissions' => [
                    'pos_session','pos_checkout','pos_returns','pos_customers','pos_eod','pos_quotations',
                    'inv_products','inv_audit','inv_discounts','inv_purchasing','inv_suppliers','inv_barcodes',
                    'fin_bills','fin_reports',
                    'hr_employees',
                    'svc_requests','svc_catalog','svc_categories',
                    'rst_pos','rst_orders','rst_floor','rst_menu','rst_ingredients','rst_kitchen',
                ],
                'is_system'   => true,
                'sort_order'  => 2,
            ],
            [
                'slug'        => 'staff',
                'name'        => 'Staff',
                'color'       => '#64748b',
                'description' => 'Basic POS access only.',
                'permissions' => ['pos_session', 'pos_checkout'],
                'is_system'   => true,
                'sort_order'  => 3,
            ],
        ];

        foreach ($defaults as $data) {
            static::query()->firstOrCreate(
                ['business_id' => $businessId, 'slug' => $data['slug']],
                array_merge($data, ['business_id' => $businessId])
            );
        }
    }

    /** All available module permission keys (mirrors BusinessMember::availablePermissions). */
    public static function availablePermissions(): array
    {
        return BusinessMember::availablePermissions();
    }

    public function hasPermission(string $key): bool
    {
        if ($this->permissions === null) {
            return true; // null = full access
        }

        return in_array($key, $this->permissions, true);
    }
}
