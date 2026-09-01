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
                'key'   => 'home',
                'label' => 'Home',
                'icon'  => 'fa-house',
                'color' => '#3b82f6',
                'items' => [
                    // ── Ribbon buttons ──────────────────────────────────────────
                    ['key' => 'home_btn_pos',            'label' => 'Open POS',              'desc' => 'Ribbon Quick Actions: Open POS register button'],
                    ['key' => 'home_btn_new_sale',       'label' => 'New Sale',               'desc' => 'Ribbon Quick Actions: New Sale shortcut button'],
                    ['key' => 'home_btn_daily_summary',  'label' => "Today's Summary",        'desc' => "Ribbon Quick Actions: Today's Summary shortcut"],
                    ['key' => 'home_btn_dashboard',      'label' => 'Dashboard',              'desc' => 'Ribbon Overview: Dashboard navigation button'],
                    ['key' => 'home_btn_analytics',      'label' => 'Analytics',              'desc' => 'Ribbon Overview: Analytics navigation button'],
                    ['key' => 'home_btn_orders',         'label' => 'Orders',                 'desc' => 'Ribbon Operations: Orders navigation button'],
                    ['key' => 'home_btn_customers',      'label' => 'Customers',              'desc' => 'Ribbon Operations: Customers navigation button'],
                    ['key' => 'home_btn_suppliers',      'label' => 'Suppliers',              'desc' => 'Ribbon Operations: Suppliers navigation button'],
                    ['key' => 'home_btn_expenses',       'label' => 'Expenses',               'desc' => 'Ribbon Finance: Expenses navigation button'],
                    ['key' => 'home_btn_profit',         'label' => 'Profit Report',          'desc' => 'Ribbon Finance: Profit Report navigation button'],
                    ['key' => 'home_btn_payroll',        'label' => 'Payroll',                'desc' => 'Ribbon Finance: Payroll navigation button'],
                    ['key' => 'home_btn_settings',       'label' => 'Settings',               'desc' => 'Ribbon Tools: Settings panel button'],
                    ['key' => 'home_btn_help',           'label' => 'Help',                   'desc' => 'Ribbon Tools: Help & Guide button'],
                    // ── KPI pills ────────────────────────────────────────────────
                    ['key' => 'home_kpi_sales',          'label' => 'KPI: Sales count',       'desc' => 'Dashboard top bar: Sales count pill'],
                    ['key' => 'home_kpi_revenue',        'label' => 'KPI: Revenue',           'desc' => 'Dashboard top bar: Revenue amount pill'],
                    ['key' => 'home_kpi_products',       'label' => 'KPI: Products',          'desc' => 'Dashboard top bar: Products count pill'],
                    ['key' => 'home_kpi_customers',      'label' => 'KPI: Customers',         'desc' => 'Dashboard top bar: Customers count pill'],
                    // ── Sub-nav tabs ─────────────────────────────────────────────
                    ['key' => 'home_tab_flow',           'label' => 'Tab: Business Flow',     'desc' => 'Dashboard: Business Flow diagram tab'],
                    ['key' => 'home_tab_today',          'label' => 'Tab: Today',             'desc' => "Dashboard: Today's Summary tab"],
                    ['key' => 'home_tab_activity',       'label' => 'Tab: Recent Activity',   'desc' => 'Dashboard: Recent transactions tab'],
                    ['key' => 'home_tab_analytics',      'label' => 'Tab: Analytics',         'desc' => 'Dashboard: Analytics overview tab'],
                    ['key' => 'home_tab_expenses',       'label' => 'Tab: Expenses',          'desc' => 'Dashboard: Expenses view tab'],
                    ['key' => 'home_tab_profit',         'label' => 'Tab: Profit Report',     'desc' => 'Dashboard: Profit Report tab'],
                    ['key' => 'home_tab_payroll',        'label' => 'Tab: Payroll',           'desc' => 'Dashboard: Payroll summary tab'],
                    ['key' => 'home_tab_orders',         'label' => 'Tab: Orders',            'desc' => 'Dashboard: Orders history tab'],
                    // ── Right panel ──────────────────────────────────────────────
                    ['key' => 'home_rp_today',           'label' => 'Panel: Today Summary',   'desc' => "Right panel: Today's sales figures section"],
                    ['key' => 'home_rp_bills',           'label' => 'Panel: Upcoming Bills',  'desc' => 'Right panel: Upcoming bills section'],
                    ['key' => 'home_rp_qa_new_sale',     'label' => 'Quick: New Sale',        'desc' => 'Right panel quick action: New Sale button'],
                    ['key' => 'home_rp_qa_add_product',  'label' => 'Quick: Add Product',     'desc' => 'Right panel quick action: Add Product button'],
                    ['key' => 'home_rp_qa_new_bill',     'label' => 'Quick: New Bill',        'desc' => 'Right panel quick action: New Bill button'],
                    ['key' => 'home_rp_qa_orders',       'label' => 'Quick: Purchase Orders', 'desc' => 'Right panel quick action: Purchase Orders button'],
                    ['key' => 'home_rp_qa_barcodes',     'label' => 'Quick: Print Barcodes',  'desc' => 'Right panel quick action: Print Barcodes button'],
                ],
            ],
            [
                'key'   => 'pos_ribbon',
                'label' => 'POS · Ribbon',
                'icon'  => 'fa-cash-register',
                'color' => '#6366f1',
                'items' => [
                    ['key' => 'pos_btn_new_session',    'label' => 'New Session',         'desc' => 'Ribbon Session: Open a new POS session'],
                    ['key' => 'pos_btn_close_session',  'label' => 'Close Session',       'desc' => 'Ribbon Session: Close current session'],
                    ['key' => 'pos_btn_lock_register',  'label' => 'Close Register',      'desc' => 'Ribbon Session: Lock/close the register'],
                    ['key' => 'pos_btn_counter',        'label' => 'Counter selector',    'desc' => 'Ribbon Session: Counter/till selector dropdown'],
                    ['key' => 'pos_btn_checkout',       'label' => 'Checkout',            'desc' => 'Ribbon Sales: Checkout button (process payment)'],
                    ['key' => 'pos_btn_return',         'label' => 'Return / Refund',     'desc' => 'Ribbon Sales: Return & refund button'],
                    ['key' => 'pos_btn_clear_cart',     'label' => 'Clear Cart',          'desc' => 'Ribbon Sales: Clear all items from cart'],
                    ['key' => 'pos_btn_search',         'label' => 'Search Products',     'desc' => 'Ribbon Find: Product search button'],
                    ['key' => 'pos_btn_barcode',        'label' => 'Scan Barcode',        'desc' => 'Ribbon Find: Barcode scanner button'],
                    ['key' => 'pos_btn_add_product',    'label' => 'Add Product',         'desc' => 'Ribbon Find: Add product button'],
                    ['key' => 'pos_btn_customers',      'label' => 'Customers',           'desc' => 'Ribbon Customers: Customers list button'],
                    ['key' => 'pos_btn_accounts',       'label' => 'Accounts',            'desc' => 'Ribbon Customers: Accounts/wallet button'],
                    ['key' => 'pos_btn_settings',       'label' => 'POS Settings',        'desc' => 'Ribbon Configure: POS Settings button'],
                    ['key' => 'pos_btn_receipt_editor', 'label' => 'Receipt Editor',      'desc' => 'Ribbon Configure: Receipt Editor button'],
                    ['key' => 'pos_btn_ribbon_stats',   'label' => "Today's Stats",       'desc' => 'Ribbon: Today\'s sales stats display'],
                ],
            ],
            [
                'key'   => 'pos_panel',
                'label' => 'POS · Panel',
                'icon'  => 'fa-cart-shopping',
                'color' => '#818cf8',
                'items' => [
                    ['key' => 'pos_panel_tab_add',         'label' => 'Add Session (+)',      'desc' => 'Panel: Add new POS session tab button'],
                    ['key' => 'pos_panel_mode_products',   'label' => 'Products mode',        'desc' => 'Panel: Products mode switcher button'],
                    ['key' => 'pos_panel_mode_services',   'label' => 'Services mode',        'desc' => 'Panel: Services mode switcher button'],
                    ['key' => 'pos_panel_search',          'label' => 'Product search bar',   'desc' => 'Panel: Product search bar'],
                    ['key' => 'pos_panel_categories',      'label' => 'Category filter',      'desc' => 'Panel: Category filter chips'],
                    ['key' => 'pos_panel_product_grid',    'label' => 'Product grid',         'desc' => 'Panel: Product cards grid area'],
                    ['key' => 'pos_cart_park',             'label' => 'Park / Hold sale',     'desc' => 'Cart: Park (hold) current sale button'],
                    ['key' => 'pos_cart_recall',           'label' => 'Recall held sale',     'desc' => 'Cart: Recall parked sale button'],
                    ['key' => 'pos_cart_customer',         'label' => 'Assign customer',      'desc' => 'Cart: Assign customer to sale button'],
                    ['key' => 'pos_cart_checkout',         'label' => 'Cart Checkout (F12)',  'desc' => 'Cart: Checkout / process payment button'],
                    ['key' => 'pos_cart_to_invoice',       'label' => 'Create Invoice',       'desc' => 'Cart: Convert cart to invoice button'],
                    ['key' => 'pos_cart_to_quote',         'label' => 'Create Quotation',     'desc' => 'Cart: Convert cart to quotation button'],
                ],
            ],
            [
                'key'   => 'sal_ribbon',
                'label' => 'Sales · Ribbon',
                'icon'  => 'fa-file-invoice',
                'color' => '#f59e0b',
                'items' => [
                    ['key' => 'sal_btn_new_invoice',   'label' => 'New Invoice',       'desc' => 'Ribbon Create: New Invoice button'],
                    ['key' => 'sal_btn_new_quotation', 'label' => 'New Quotation',     'desc' => 'Ribbon Create: New Quotation button'],
                    ['key' => 'sal_btn_refresh',       'label' => 'Refresh',           'desc' => 'Ribbon Transactions: Refresh sales list'],
                    ['key' => 'sal_btn_all_sales',     'label' => 'All Sales',         'desc' => 'Ribbon Transactions: All Sales filter'],
                    ['key' => 'sal_btn_pos_sales',     'label' => 'POS Sales',         'desc' => 'Ribbon Transactions: POS Sales filter'],
                    ['key' => 'sal_btn_returns',       'label' => 'Returns',           'desc' => 'Ribbon Returns: Process returns button'],
                    ['key' => 'sal_btn_eod',           'label' => 'End of Day',        'desc' => 'Ribbon Settlement: End of Day button'],
                    ['key' => 'sal_btn_qt_new',        'label' => 'New Quote',         'desc' => 'Ribbon Quotations: New Quotation button'],
                    ['key' => 'sal_btn_qt_refresh',    'label' => 'Quotes Refresh',    'desc' => 'Ribbon Quotations: Refresh quotes list'],
                ],
            ],
            [
                'key'   => 'sal_panel',
                'label' => 'Sales · Panel',
                'icon'  => 'fa-receipt',
                'color' => '#fbbf24',
                'items' => [
                    ['key' => 'sal_tab_transactions', 'label' => 'Tab: Transactions', 'desc' => 'Sales panel: Transactions sub-nav tab'],
                    ['key' => 'sal_tab_history',      'label' => 'Tab: History',      'desc' => 'Sales panel: History sub-nav tab'],
                    ['key' => 'sal_tab_quotes',       'label' => 'Tab: Quotations',   'desc' => 'Sales panel: Quotations sub-nav tab'],
                    ['key' => 'sal_tab_invoices',     'label' => 'Tab: Invoices',     'desc' => 'Sales panel: Invoices sub-nav tab'],
                ],
            ],
            [
                'key'   => 'inv_ribbon',
                'label' => 'Inventory · Ribbon',
                'icon'  => 'fa-boxes-stacked',
                'color' => '#8b5cf6',
                'items' => [
                    ['key' => 'inv_btn_products',    'label' => 'Products',          'desc' => 'Ribbon Catalog: Products list button'],
                    ['key' => 'inv_btn_refresh',     'label' => 'Refresh',           'desc' => 'Ribbon Catalog: Refresh products list'],
                    ['key' => 'inv_btn_clear',       'label' => 'Clear Filters',     'desc' => 'Ribbon Catalog: Clear filters button'],
                    ['key' => 'inv_btn_categories',  'label' => 'Categories',        'desc' => 'Ribbon Catalog: Categories button'],
                    ['key' => 'inv_btn_units',       'label' => 'Units',             'desc' => 'Ribbon Catalog: Units of measure button'],
                    ['key' => 'inv_btn_audit',       'label' => 'Stock Audit',       'desc' => 'Ribbon Stock: Stock Audit button'],
                    ['key' => 'inv_btn_transfer',    'label' => 'Stock Transfer',    'desc' => 'Ribbon Stock: Stock Transfer button'],
                    ['key' => 'inv_btn_brands',      'label' => 'Brands',            'desc' => 'Ribbon Stock: Brands button'],
                    ['key' => 'inv_btn_discounts',   'label' => 'Discounts',         'desc' => 'Ribbon Stock: Discounts button'],
                    ['key' => 'inv_btn_orders',      'label' => 'Purchase Orders',   'desc' => 'Ribbon Purchasing: Purchase Orders button'],
                    ['key' => 'inv_btn_grn',         'label' => 'Goods Receive',     'desc' => 'Ribbon Purchasing: Goods Receive (GRN) button'],
                    ['key' => 'inv_btn_cheques',     'label' => 'Cheques',           'desc' => 'Ribbon Purchasing: Cheques button'],
                    ['key' => 'inv_btn_suppliers',   'label' => 'Suppliers',         'desc' => 'Ribbon Suppliers: Suppliers list button'],
                    ['key' => 'inv_btn_add_supplier','label' => 'Add Supplier',      'desc' => 'Ribbon Suppliers: Add new supplier button'],
                    ['key' => 'inv_btn_barcodes',    'label' => 'Barcode Sheets',    'desc' => 'Ribbon Print: Barcode sheets button'],
                ],
            ],
            [
                'key'   => 'inv_panel',
                'label' => 'Inventory · Panel',
                'icon'  => 'fa-layer-group',
                'color' => '#a78bfa',
                'items' => [
                    ['key' => 'inv_tab_products',   'label' => 'Tab: Products',        'desc' => 'Inventory panel: Products sub-nav tab'],
                    ['key' => 'inv_tab_suppliers',  'label' => 'Tab: Suppliers',       'desc' => 'Inventory panel: Suppliers sub-nav tab'],
                    ['key' => 'inv_tab_po',         'label' => 'Tab: Purchase Orders', 'desc' => 'Inventory panel: Purchase Orders sub-nav tab'],
                    ['key' => 'inv_tab_grn',        'label' => 'Tab: Goods Receive',   'desc' => 'Inventory panel: Goods Receive sub-nav tab'],
                    ['key' => 'inv_tab_cheques',    'label' => 'Tab: Cheques',         'desc' => 'Inventory panel: Cheques sub-nav tab'],
                    ['key' => 'inv_tab_audit',      'label' => 'Tab: Stock Audit',     'desc' => 'Inventory panel: Stock Audit sub-nav tab'],
                    ['key' => 'inv_tab_transfer',   'label' => 'Tab: Stock Transfer',  'desc' => 'Inventory panel: Stock Transfer sub-nav tab'],
                    ['key' => 'inv_tab_categories', 'label' => 'Tab: Categories',      'desc' => 'Inventory panel: Categories sub-nav tab'],
                    ['key' => 'inv_tab_units',      'label' => 'Tab: Units',           'desc' => 'Inventory panel: Units sub-nav tab'],
                    ['key' => 'inv_tab_discounts',  'label' => 'Tab: Discounts',       'desc' => 'Inventory panel: Discounts sub-nav tab'],
                    ['key' => 'inv_tab_brands',     'label' => 'Tab: Brands',          'desc' => 'Inventory panel: Brands sub-nav tab'],
                    ['key' => 'inv_tab_barcodes',   'label' => 'Tab: Barcodes',        'desc' => 'Inventory panel: Barcodes sub-nav tab'],
                ],
            ],
            [
                'key'   => 'fin_ribbon',
                'label' => 'Finance · Ribbon',
                'icon'  => 'fa-file-invoice-dollar',
                'color' => '#22c55e',
                'items' => [
                    ['key' => 'fin_btn_create_bill', 'label' => 'Create Bill',      'desc' => 'Ribbon Bills & Loans: Create Bill button'],
                    ['key' => 'fin_btn_bills_list',  'label' => 'View Bills',       'desc' => 'Ribbon Bills & Loans: View Bills button'],
                    ['key' => 'fin_btn_loans',       'label' => 'Loans',            'desc' => 'Ribbon Bills & Loans: Loans button'],
                    ['key' => 'fin_btn_rentals',     'label' => 'Rentals',          'desc' => 'Ribbon Assets: Rentals button'],
                    ['key' => 'fin_btn_properties',  'label' => 'Properties',       'desc' => 'Ribbon Assets: Properties button'],
                    ['key' => 'fin_btn_profit',      'label' => 'Profit Analytics', 'desc' => 'Ribbon Reports: Profit Analytics button'],
                    ['key' => 'fin_btn_sales',       'label' => 'Sales Reports',    'desc' => 'Ribbon Reports: Sales Reports button'],
                ],
            ],
            [
                'key'   => 'fin_panel',
                'label' => 'Finance · Panel',
                'icon'  => 'fa-coins',
                'color' => '#4ade80',
                'items' => [
                    ['key' => 'fin_tab_flow',          'label' => 'Tab: Overview',      'desc' => 'Finance panel: Overview sub-nav tab'],
                    ['key' => 'fin_tab_bills',         'label' => 'Tab: Bills',         'desc' => 'Finance panel: Bills sub-nav tab'],
                    ['key' => 'fin_tab_loans',         'label' => 'Tab: Loans',         'desc' => 'Finance panel: Loans sub-nav tab'],
                    ['key' => 'fin_tab_rentals',       'label' => 'Tab: Rentals',       'desc' => 'Finance panel: Rentals sub-nav tab'],
                    ['key' => 'fin_tab_properties',    'label' => 'Tab: Properties',    'desc' => 'Finance panel: Properties sub-nav tab'],
                    ['key' => 'fin_tab_modifications', 'label' => 'Tab: Modifications', 'desc' => 'Finance panel: Modifications sub-nav tab'],
                ],
            ],
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
                    ['key' => 'inv_transfer',   'label' => 'Stock Transfer',          'desc' => 'Move stock between branches and receive incoming transfers'],
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
            [
                'key'   => 'mail',
                'label' => 'Mail',
                'icon'  => 'fa-envelope',
                'color' => '#0ea5e9',
                'items' => [
                    ['key' => 'mail_inbox',     'label' => 'Inbox & Conversations', 'desc' => 'Read, reply to and manage incoming email conversations'],
                    ['key' => 'mail_compose',   'label' => 'Compose & Send',        'desc' => 'Write and send new emails to customers and contacts'],
                    ['key' => 'mail_templates', 'label' => 'Templates & Filters',   'desc' => 'Create email templates, inbox filters, and manage scheduled messages'],
                ],
            ],
            [
                'key'   => 'crm',
                'label' => 'CRM',
                'icon'  => 'fa-handshake',
                'color' => '#8b5cf6',
                'items' => [
                    ['key' => 'crm_pipeline', 'label' => 'Pipeline & Leads',   'desc' => 'View and manage the sales pipeline and lead records'],
                    ['key' => 'crm_contacts', 'label' => 'Contacts',           'desc' => 'View and manage CRM contact records'],
                    ['key' => 'crm_tasks',    'label' => 'Tasks',              'desc' => 'Create and manage CRM tasks and follow-ups'],
                    ['key' => 'crm_forms',    'label' => 'Lead Capture Forms', 'desc' => 'Design and manage web forms for capturing leads from your website'],
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
