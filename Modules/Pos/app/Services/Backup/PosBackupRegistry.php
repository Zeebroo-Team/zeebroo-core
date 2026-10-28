<?php

namespace Modules\Pos\Services\Backup;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Models\Business;

/**
 * What a business backup can contain, grouped the way the user picks it in the
 * desktop "Database Backup & Import" modal.
 *
 * Every table is either a ROOT (owns a `business_id` column, or a custom owner
 * column) or a CHILD (owned through a foreign key to a parent table in the same
 * group). Ownership is always resolved as a SQL subquery so a backup can never
 * read — and an import can never touch — rows belonging to another business.
 *
 * Tables are listed parent-before-child; import applies them in this order.
 *
 * Deliberately excluded: users, business members, billing/payments, API keys,
 * mailbox & social credentials, notifications, sessions and AI chat history.
 */
class PosBackupRegistry
{
    public const FORMAT = 'zeebroo-business-backup';

    public const VERSION = 1;

    /** Disk + path prefix product/file-manager uploads live under (see FileManagerService). */
    public const FILES_DISK = 'public';

    public static function filesPrefix(int $businessId): string
    {
        return 'business-files/'.$businessId.'/';
    }

    /**
     * @return array<string, array{label: string, icon: string, description: string, tables: array<int, array<string, mixed>>, files?: bool}>
     */
    public function groups(): array
    {
        return [
            'settings' => [
                'label' => 'Business Settings',
                'icon' => 'fa-gear',
                'description' => 'Settings, branches, counters, feature switches',
                'tables' => [
                    ['table' => 'settings', 'owner' => ['scope_id', ['scope_type' => Business::class]], 'key' => ['scope_type', 'scope_id', 'key'], 'drop_id' => true],
                    ['table' => 'branches'],
                    ['table' => 'pos_counters'],
                    ['table' => 'business_feature_overrides', 'key' => ['business_id', 'feature_key'], 'drop_id' => true],
                    ['table' => 'grn_permission_settings'],
                ],
            ],
            'media' => [
                'label' => 'Images & Files',
                'icon' => 'fa-images',
                'description' => 'Product images and every file in the File Manager',
                'files' => true,
                'tables' => [
                    ['table' => 'file_manager_folders'],
                    ['table' => 'file_manager_files'],
                ],
            ],
            'products' => [
                'label' => 'Products & Inventory',
                'icon' => 'fa-boxes-stacked',
                'description' => 'Products, categories, brands, units, stock layers, discounts',
                'requires' => ['media'],
                'tables' => [
                    ['table' => 'product_units'],
                    ['table' => 'product_categories'],
                    ['table' => 'product_brands'],
                    ['table' => 'products'],
                    ['table' => 'product_selling_units'],
                    ['table' => 'product_stock_layers'],
                    ['table' => 'product_discounts'],
                    ['table' => 'product_barcode_sheets'],
                    ['table' => 'product_images', 'parent' => ['product_id', 'products']],
                    ['table' => 'product_product_brand', 'parent' => ['product_id', 'products']],
                    ['table' => 'product_product_category', 'parent' => ['product_id', 'products']],
                    ['table' => 'product_bundle_items', 'parent' => ['bundle_product_id', 'products']],
                ],
            ],
            'customers' => [
                'label' => 'Customers',
                'icon' => 'fa-users',
                'description' => 'Customers, customer categories, subscriptions',
                'tables' => [
                    ['table' => 'pos_customer_categories'],
                    ['table' => 'pos_customers'],
                    ['table' => 'customer_subscriptions'],
                ],
            ],
            'suppliers' => [
                'label' => 'Suppliers',
                'icon' => 'fa-truck',
                'description' => 'Suppliers and supplier categories',
                'tables' => [
                    ['table' => 'supplier_categories'],
                    ['table' => 'suppliers'],
                ],
            ],
            'sales' => [
                'label' => 'Sales & Orders',
                'icon' => 'fa-cart-shopping',
                'description' => 'POS sales, returns, invoices, quotations, sales orders, coupons, gift cards',
                'tables' => [
                    ['table' => 'pos_sales'],
                    ['table' => 'pos_sale_items', 'parent' => ['pos_sale_id', 'pos_sales']],
                    ['table' => 'pos_sale_returns'],
                    ['table' => 'pos_sale_return_items', 'parent' => ['pos_sale_return_id', 'pos_sale_returns']],
                    ['table' => 'pos_sale_reverts'],
                    ['table' => 'pos_sale_cheques'],
                    ['table' => 'invoices'],
                    ['table' => 'invoice_items', 'parent' => ['invoice_id', 'invoices']],
                    ['table' => 'quotations'],
                    ['table' => 'quotation_items', 'parent' => ['quotation_id', 'quotations']],
                    ['table' => 'sales_orders'],
                    ['table' => 'sales_order_items', 'parent' => ['sales_order_id', 'sales_orders']],
                    ['table' => 'pos_coupons'],
                    ['table' => 'pos_coupon_redemptions', 'parent' => ['pos_coupon_id', 'pos_coupons']],
                    ['table' => 'pos_gift_card_groups'],
                    ['table' => 'pos_gift_cards'],
                    ['table' => 'pos_gift_card_transactions', 'parent' => ['pos_gift_card_id', 'pos_gift_cards']],
                    ['table' => 'sale_campaigns'],
                    ['table' => 'sale_campaign_items', 'parent' => ['sale_campaign_id', 'sale_campaigns']],
                    ['table' => 'pos_cash_openings'],
                    ['table' => 'pos_cash_withdrawals'],
                    ['table' => 'pos_product_rentals'],
                ],
            ],
            'purchases' => [
                'label' => 'Purchases & Stock',
                'icon' => 'fa-truck-ramp-box',
                'description' => 'Purchase orders, GRNs, cheques, stock audits, stock transfers',
                'tables' => [
                    ['table' => 'purchases'],
                    ['table' => 'purchase_items', 'parent' => ['purchase_id', 'purchases']],
                    ['table' => 'goods_receive_notes'],
                    ['table' => 'goods_receive_note_items', 'parent' => ['goods_receive_note_id', 'goods_receive_notes']],
                    ['table' => 'grn_expenses'],
                    ['table' => 'cheque_payments'],
                    ['table' => 'stock_audits'],
                    ['table' => 'stock_audit_lines', 'parent' => ['stock_audit_id', 'stock_audits']],
                    ['table' => 'stock_transfers'],
                    ['table' => 'stock_transfer_lines', 'parent' => ['stock_transfer_id', 'stock_transfers']],
                ],
            ],
            'finance' => [
                'label' => 'Finance & Expenses',
                'icon' => 'fa-file-invoice-dollar',
                'description' => 'Accounts, ledger, bills, budgets, loans, investments, properties, rentals',
                'tables' => [
                    ['table' => 'accounts'],
                    ['table' => 'ledger_transactions'],
                    ['table' => 'bills'],
                    ['table' => 'budgets'],
                    ['table' => 'budget_items', 'parent' => ['budget_id', 'budgets']],
                    ['table' => 'budget_actual_entries', 'parent' => ['budget_id', 'budgets']],
                    ['table' => 'investments'],
                    ['table' => 'loans'],
                    ['table' => 'loan_external_installment_marks', 'parent' => ['loan_id', 'loans']],
                    ['table' => 'loan_deduction_transactions'],
                    ['table' => 'properties'],
                    ['table' => 'rentals'],
                    ['table' => 'rental_external_billing_marks', 'parent' => ['rental_id', 'rentals']],
                    ['table' => 'modifications'],
                ],
            ],
            'hr' => [
                'label' => 'HR & Payroll',
                'icon' => 'fa-user-tie',
                'description' => 'Employees, departments, attendance, leave, payroll',
                'tables' => [
                    ['table' => 'hr_departments'],
                    ['table' => 'hr_job_titles'],
                    ['table' => 'hr_allowance_types'],
                    ['table' => 'hr_employees'],
                    ['table' => 'hr_employee_allowances', 'parent' => ['employee_id', 'hr_employees']],
                    ['table' => 'hr_employee_documents'],
                    ['table' => 'hr_employee_biometric_mappings'],
                    ['table' => 'hr_attendance_records'],
                    ['table' => 'hr_attendance_device_logs'],
                    ['table' => 'hr_business_holidays'],
                    ['table' => 'hr_leave_requests'],
                    ['table' => 'hr_complaints'],
                    ['table' => 'hr_payroll_rule_sets'],
                    ['table' => 'hr_payroll_rules', 'parent' => ['rule_set_id', 'hr_payroll_rule_sets']],
                    ['table' => 'hr_payroll_custom_templates'],
                    ['table' => 'hr_payroll_cycles'],
                    ['table' => 'hr_payroll_items', 'parent' => ['payroll_cycle_id', 'hr_payroll_cycles']],
                    ['table' => 'hr_payroll_item_components', 'parent' => ['payroll_item_id', 'hr_payroll_items']],
                ],
            ],
            'crm' => [
                'label' => 'CRM',
                'icon' => 'fa-handshake',
                'description' => 'CRM projects, pipelines, leads, activities, tasks',
                'tables' => [
                    ['table' => 'crm_projects'],
                    ['table' => 'crm_lead_stages', 'parent' => ['project_id', 'crm_projects']],
                    ['table' => 'crm_lead_custom_fields', 'parent' => ['project_id', 'crm_projects']],
                    ['table' => 'crm_lead_forms', 'parent' => ['project_id', 'crm_projects']],
                    ['table' => 'crm_lead_stage_mail_templates', 'parent' => ['project_id', 'crm_projects']],
                    ['table' => 'crm_leads'],
                    ['table' => 'crm_lead_custom_field_values', 'parent' => ['lead_id', 'crm_leads']],
                    ['table' => 'crm_lead_stage_logs', 'parent' => ['lead_id', 'crm_leads']],
                    ['table' => 'crm_activities'],
                    ['table' => 'crm_tasks'],
                ],
            ],
            'services' => [
                'label' => 'Services',
                'icon' => 'fa-screwdriver-wrench',
                'description' => 'Service items, categories, bundles, discounts, requests',
                'tables' => [
                    ['table' => 'service_categories'],
                    ['table' => 'service_items'],
                    ['table' => 'service_item_employee', 'parent' => ['service_item_id', 'service_items']],
                    ['table' => 'service_item_product', 'parent' => ['service_item_id', 'service_items']],
                    ['table' => 'service_item_service_category', 'parent' => ['service_item_id', 'service_items']],
                    ['table' => 'service_bundles'],
                    ['table' => 'service_bundle_items', 'parent' => ['service_bundle_id', 'service_bundles']],
                    ['table' => 'service_item_discounts'],
                    ['table' => 'service_requests'],
                ],
            ],
            'restaurant' => [
                'label' => 'Restaurant',
                'icon' => 'fa-utensils',
                'description' => 'Menu, tables, orders, reservations, ingredients',
                'tables' => [
                    ['table' => 'restaurant_menu_categories'],
                    ['table' => 'restaurant_menu_items'],
                    ['table' => 'restaurant_menu_item_categories', 'parent' => ['menu_item_id', 'restaurant_menu_items']],
                    ['table' => 'restaurant_ingredients'],
                    ['table' => 'restaurant_recipe_ingredients', 'parent' => ['menu_item_id', 'restaurant_menu_items']],
                    ['table' => 'restaurant_tables'],
                    ['table' => 'restaurant_orders'],
                    ['table' => 'restaurant_order_items', 'parent' => ['order_id', 'restaurant_orders']],
                    ['table' => 'restaurant_reservations'],
                    ['table' => 'restaurant_ingredient_purchase_orders'],
                    ['table' => 'restaurant_ingredient_purchase_order_items', 'parent' => ['purchase_order_id', 'restaurant_ingredient_purchase_orders']],
                    ['table' => 'restaurant_ingredient_grns'],
                    ['table' => 'restaurant_ingredient_grn_items', 'parent' => ['grn_id', 'restaurant_ingredient_grns']],
                    ['table' => 'restaurant_stock_transactions'],
                ],
            ],
        ];
    }

    /** @return array<int, string> */
    public function groupKeys(): array
    {
        return array_keys($this->groups());
    }

    /**
     * Expand a selection with required groups (e.g. products → media, so product
     * images travel with their products) and return it in registry order.
     *
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    public function resolveSelection(array $keys): array
    {
        $groups = $this->groups();
        $wanted = array_flip(array_intersect($keys, array_keys($groups)));
        foreach (array_keys($wanted) as $key) {
            foreach ($groups[$key]['requires'] ?? [] as $req) {
                $wanted[$req] = true;
            }
        }

        return array_values(array_filter(array_keys($groups), fn ($k) => isset($wanted[$k])));
    }

    /**
     * Table specs for the given groups, in import order, skipping tables that do
     * not exist on this server (optional modules).
     *
     * @param  array<int, string>  $groupKeys
     * @return array<string, array<string, mixed>> keyed by table name
     */
    public function tablesFor(array $groupKeys): array
    {
        $groups = $this->groups();
        $out = [];
        foreach ($groupKeys as $key) {
            foreach ($groups[$key]['tables'] ?? [] as $spec) {
                if (! Schema::hasTable($spec['table'])) {
                    continue;
                }
                $out[$spec['table']] = $spec + ['group' => $key];
            }
        }

        return $out;
    }

    /** Every table spec across all groups, keyed by table. */
    public function allTables(): array
    {
        return $this->tablesFor($this->groupKeys());
    }

    /**
     * Base query for the rows of $table owned by $businessId. Child tables are
     * resolved through their parent with a nested `whereIn (subquery)` chain.
     */
    public function ownedQuery(string $table, int $businessId, ?array $specs = null): Builder
    {
        $specs ??= $this->allTables();
        $spec = $specs[$table] ?? ['table' => $table];

        $query = DB::table($table);

        if (isset($spec['parent'])) {
            [$fk, $parentTable] = $spec['parent'];

            return $query->whereIn($fk, $this->ownedQuery($parentTable, $businessId, $specs)->select('id'));
        }

        [$ownerCol, $extra] = $this->ownerColumn($spec);
        $query->where($ownerCol, $businessId);
        foreach ($extra as $col => $val) {
            $query->where($col, $val);
        }

        return $query;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>} owner column + extra fixed column values
     */
    public function ownerColumn(array $spec): array
    {
        if (isset($spec['owner'])) {
            return [$spec['owner'][0], $spec['owner'][1] ?? []];
        }

        return ['business_id', []];
    }

    /**
     * Any table outside the registry that still carries `business_id` is
     * business-scoped too — used when validating foreign keys on import.
     */
    public function isBusinessScoped(string $table): bool
    {
        static $cache = [];
        if (! array_key_exists($table, $cache)) {
            $cache[$table] = isset($this->allTables()[$table])
                || (Schema::hasTable($table) && Schema::hasColumn($table, 'business_id'));
        }

        return $cache[$table];
    }
}
