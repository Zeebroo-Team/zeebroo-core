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

    /** Grouped permission definition — used by the API and the desktop UI. */
    public static function availablePermissions(): array
    {
        return [
            [
                'key'   => 'point_of_sale',
                'label' => 'POS & Sales',
                'icon'  => 'fa-cash-register',
                'color' => '#6366f1',
                'items' => [
                    ['key' => 'pos_session',    'label' => 'Open / Close Session',   'desc' => 'Start and end cash register sessions'],
                    ['key' => 'pos_checkout',   'label' => 'Checkout & New Sales',   'desc' => 'Process sales, apply discounts, accept payment'],
                    ['key' => 'pos_returns',    'label' => 'Returns & Refunds',      'desc' => 'Process customer returns and issue refunds'],
                    ['key' => 'pos_customers',  'label' => 'Customer Management',    'desc' => 'View and manage customer records at POS'],
                    ['key' => 'pos_eod',        'label' => 'End-of-Day Settlement',  'desc' => 'Run daily cash-up and closing reports'],
                    ['key' => 'pos_quotations', 'label' => 'Quotations',             'desc' => 'Create and send price quotations to customers'],
                ],
            ],
            [
                'key'   => 'inventory',
                'label' => 'Inventory',
                'icon'  => 'fa-boxes-stacked',
                'color' => '#8b5cf6',
                'items' => [
                    ['key' => 'inv_products',   'label' => 'Products & Categories',   'desc' => 'Add, edit and organise products and categories'],
                    ['key' => 'inv_audit',      'label' => 'Stock Audit',             'desc' => 'Perform stock counts and adjust inventory levels'],
                    ['key' => 'inv_discounts',  'label' => 'Brands & Discounts',      'desc' => 'Manage product brands and discount schemes'],
                    ['key' => 'inv_purchasing', 'label' => 'Purchase Orders & GRN',   'desc' => 'Raise purchase orders and receive goods (GRN)'],
                    ['key' => 'inv_suppliers',  'label' => 'Suppliers',               'desc' => 'View and manage supplier records'],
                    ['key' => 'inv_barcodes',   'label' => 'Barcode Printing',        'desc' => 'Generate and print product barcodes and labels'],
                ],
            ],
            [
                'key'   => 'finance',
                'label' => 'Finance & Accounts',
                'icon'  => 'fa-file-invoice-dollar',
                'color' => '#22c55e',
                'items' => [
                    ['key' => 'fin_bills',   'label' => 'Bills & Loans',          'desc' => 'Manage recurring bills, loan records and repayments'],
                    ['key' => 'fin_assets',  'label' => 'Assets & Liabilities',   'desc' => 'Track rentals, properties and business assets'],
                    ['key' => 'fin_reports', 'label' => 'Financial Reports',      'desc' => 'View cash flow, income statements and account ledgers'],
                ],
            ],
            [
                'key'   => 'hr',
                'label' => 'HR & Payroll',
                'icon'  => 'fa-people-group',
                'color' => '#f59e0b',
                'items' => [
                    ['key' => 'hr_employees',   'label' => 'Employee Records',       'desc' => 'Add and manage employee profiles and documents'],
                    ['key' => 'hr_departments', 'label' => 'Departments',            'desc' => 'Create and organise company departments'],
                    ['key' => 'hr_payroll',     'label' => 'Payroll & Compensation', 'desc' => 'Run payroll cycles, view salaries and pay slips'],
                ],
            ],
            [
                'key'   => 'services',
                'label' => 'Services',
                'icon'  => 'fa-screwdriver-wrench',
                'color' => '#f97316',
                'items' => [
                    ['key' => 'svc_requests',   'label' => 'Service Requests',  'desc' => 'View, assign and update customer service requests'],
                    ['key' => 'svc_catalog',    'label' => 'Service Catalog',   'desc' => 'Add and manage services offered to customers'],
                    ['key' => 'svc_categories', 'label' => 'Categories',        'desc' => 'Organise services into categories'],
                ],
            ],
            [
                'key'   => 'design',
                'label' => 'Design & Marketing',
                'icon'  => 'fa-palette',
                'color' => '#ec4899',
                'items' => [
                    ['key' => 'design_all', 'label' => 'Design Studio', 'desc' => 'Create and manage social media, letterhead and marketing designs'],
                ],
            ],
            [
                'key'   => 'restaurant',
                'label' => 'Restaurant',
                'icon'  => 'fa-utensils',
                'color' => '#ef4444',
                'items' => [
                    ['key' => 'rst_pos',         'label' => 'Restaurant POS',       'desc' => 'Take orders and process payments at the table or counter'],
                    ['key' => 'rst_orders',      'label' => 'Orders',               'desc' => 'View, manage and update dine-in, takeaway and delivery orders'],
                    ['key' => 'rst_floor',       'label' => 'Floor Plan & Tables',  'desc' => 'Manage table layout, reservations and seating'],
                    ['key' => 'rst_menu',        'label' => 'Menu Management',      'desc' => 'Add and edit menu items, prices and categories'],
                    ['key' => 'rst_ingredients', 'label' => 'Ingredients & Stock',  'desc' => 'Track ingredient inventory and receive stock'],
                    ['key' => 'rst_kitchen',     'label' => 'Kitchen Display',      'desc' => 'View and manage the kitchen order display (KDS)'],
                ],
            ],
        ];
    }

    /** Flat list of all valid permission keys — use for validation. */
    public static function permissionKeys(): array
    {
        $keys = [];
        foreach (static::availablePermissions() as $group) {
            foreach ($group['items'] as $item) {
                $keys[] = $item['key'];
            }
        }
        return $keys;
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
