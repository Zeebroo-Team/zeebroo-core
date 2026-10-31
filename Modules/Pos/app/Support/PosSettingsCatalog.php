<?php

namespace Modules\Pos\Support;

use Modules\Pos\Services\PosSettingsService as Pos;
use Modules\Purchase\Services\GrnPaymentSettlementService;

/**
 * Every setting the POS apps use, for Admin → User → POS Settings.
 *
 * A business has one value per setting: there are no per-app settings on the server.
 * `surfaces` lists the apps a setting really affects (the client reads it, or the server
 * enforces it on that client's requests), which is what the admin screen groups by.
 * Settings kept on the device itself (printer, desktop layout...) never reach the server.
 *
 * `field` is the name used by PosSettingsService::forBusiness() / saveForBusiness();
 * `keys` are the rows it is stored in. `default` is what forBusiness() falls back to once
 * those rows are deleted — PosSettingsAdminTest keeps both in step with the service.
 */
final class PosSettingsCatalog
{
    public const SURFACES = [
        'desktop' => ['label' => 'POS Desktop', 'icon' => 'fa-desktop'],
        'mobile'  => ['label' => 'POS Mobile',  'icon' => 'fa-mobile-screen-button'],
        'lite'    => ['label' => 'POS Lite',    'icon' => 'fa-bolt'],
        'web'     => ['label' => 'POS Web',     'icon' => 'fa-globe'],
    ];

    private const ALL = ['desktop', 'mobile', 'lite', 'web'];

    private const CURRENCIES = [
        'LKR' => 'Sri Lankan Rupee', 'USD' => 'US Dollar', 'EUR' => 'Euro', 'GBP' => 'British Pound',
        'INR' => 'Indian Rupee', 'AUD' => 'Australian Dollar', 'CAD' => 'Canadian Dollar',
        'SGD' => 'Singapore Dollar', 'AED' => 'UAE Dirham', 'SAR' => 'Saudi Riyal', 'QAR' => 'Qatari Riyal',
        'MYR' => 'Malaysian Ringgit', 'THB' => 'Thai Baht', 'JPY' => 'Japanese Yen', 'CNY' => 'Chinese Yuan',
        'BDT' => 'Bangladeshi Taka', 'PKR' => 'Pakistani Rupee', 'NPR' => 'Nepalese Rupee',
        'MVR' => 'Maldivian Rufiyaa', 'ZAR' => 'South African Rand',
    ];

    private const DELIVERY_LABELS = [
        'dhl' => 'DHL Express', 'fedex' => 'FedEx', 'uber' => 'Uber', 'pickme' => 'PickMe',
        'koobiyo' => 'Koobiyo', 'pronto' => 'Pronto',
    ];

    private const COURIER_LABELS = [
        'courier' => 'Courier', 'domex' => 'Domex', 'koobiyo' => 'Koobiyo', 'pronto' => 'Pronto', 'citypak' => 'Citypak',
    ];

    /** @return array<string, array<string, mixed>> keyed by field, in display order */
    public static function definitions(): array
    {
        static $definitions = null;

        return $definitions ??= self::build();
    }

    /** @return array<string, mixed>|null */
    public static function find(string $field): ?array
    {
        return self::definitions()[$field] ?? null;
    }

    /** @return list<string> every settings row the catalog accounts for */
    public static function claimedKeys(): array
    {
        return array_merge(...array_values(array_map(fn ($d) => $d['keys'], self::definitions())));
    }

    private static function build(): array
    {
        $items = [
            // ── Business ────────────────────────────────────────────────────
            ['business_name', [], 'Business', 'Business name', 'text', self::ALL, null, [
                'max' => 255, 'required' => true,
                'help' => 'Stored on the business itself, so it can be changed but not deleted.',
            ]],
            ['currency', ['business.currency'], 'Business', 'Currency', 'currency', self::ALL, '', [
                'deletable' => false,
                'warning' => 'Only the label changes. Existing amounts are not converted.',
            ]],
            ['currency_position', [Pos::KEY_CURRENCY_POSITION], 'Business', 'Currency position', 'select', self::ALL, 'after', [
                'options' => self::options(['before' => 'Before the amount', 'after' => 'After the amount']),
            ]],
            ['timezone', ['business.timezone'], 'Business', 'Timezone', 'timezone', ['desktop', 'mobile', 'lite'], ''],
            ['business_logo_url', [Pos::KEY_BUSINESS_LOGO_URL], 'Business', 'Business logo', 'url', ['desktop', 'mobile', 'lite'], ''],

            // ── Branches & warehouses ───────────────────────────────────────
            ['multi_warehouse_branch', ['business.multi_warehouse_branch'], 'Branches & warehouses', 'Multi warehouse / branches', 'toggle', ['desktop', 'web'], false],
            ['branch_product_separate', ['business.branch_product_separate'], 'Branches & warehouses', 'Manage products per branch', 'toggle', self::ALL, false, [
                'warning' => 'Changes which products each branch can see and sell.',
            ]],
            ['branch_stock_separate', ['business.branch_stock_separate'], 'Branches & warehouses', 'Manage stock per branch', 'toggle', self::ALL, false, [
                'warning' => 'Changes which stock each branch can sell. Checkout can start refusing sales that have no stock in that branch.',
            ]],
            ['branch_pos_separate', ['business.branch_pos_separate'], 'Branches & warehouses', 'Run POS per branch', 'toggle', self::ALL, false, [
                'warning' => 'Changes what each branch sees in the POS.',
            ]],

            // ── Point of sale ───────────────────────────────────────────────
            ['display_theme', [Pos::KEY_DISPLAY_THEME], 'Point of sale', 'Display theme', 'select', ['desktop', 'web'], 'inherit', [
                'options' => self::options(['inherit' => 'System Default', 'light' => 'Light', 'dark' => 'Dark']),
            ]],
            ['discount_field_enabled', [Pos::KEY_DISCOUNT_FIELD_ENABLED], 'Point of sale', 'Show discount field', 'toggle', ['desktop', 'lite', 'web'], false],
            ['checkout_modal_enabled', [Pos::KEY_CHECKOUT_MODAL_ENABLED], 'Point of sale', 'Use checkout modal', 'toggle', ['desktop', 'lite', 'web'], false],
            ['featured_products_limit', [Pos::KEY_FEATURED_PRODUCTS_LIMIT], 'Point of sale', 'Featured products limit', 'number', ['desktop', 'lite', 'web'], 0, [
                'min' => 0, 'max' => 1000, 'integer' => true, 'help' => '0 means no limit.',
            ]],
            ['featured_categories_limit', [Pos::KEY_FEATURED_CATEGORIES_LIMIT], 'Point of sale', 'Featured categories limit', 'number', ['desktop', 'lite', 'web'], 0, [
                'min' => 0, 'max' => 1000, 'integer' => true, 'help' => '0 means no limit.',
            ]],
            ['show_service_bound_products', [Pos::KEY_SHOW_SERVICE_BOUND_PRODUCTS], 'Point of sale', 'Show service-bound products', 'toggle', ['desktop', 'web'], true],
            ['stock_selection_mode', [Pos::KEY_STOCK_SELECTION_MODE], 'Point of sale', 'Stock batch selection', 'select', ['desktop', 'lite'], 'fifo', [
                'options' => self::options([
                    'fifo' => 'Automatic — First In, First Out (FIFO)',
                    'choose' => 'Choose batch manually at checkout',
                    'last_price' => 'Use latest batch (last price update)',
                ]),
            ]],
            ['choose_price', [Pos::KEY_CHOOSE_PRICE], 'Point of sale', 'Allow price override at checkout', 'toggle', ['desktop', 'lite'], false],
            ['rental_enabled', [Pos::KEY_RENTAL_ENABLED], 'Point of sale', 'Enable product rentals', 'toggle', ['desktop'], true],

            // ── Payments ────────────────────────────────────────────────────
            ['default_deposit_account_id', [Pos::KEY_DEFAULT_DEPOSIT_ACCOUNT], 'Payments', 'Default deposit account', 'account', self::ALL, null, [
                'help' => 'Cash and card sales are paid into this account when none is chosen.',
            ]],
            ['payment_settlement_mode', [Pos::KEY_PAYMENT_SETTLEMENT_MODE], 'Payments', 'Payment settlement', 'select', ['desktop', 'lite', 'web'], 'immediate', [
                'options' => self::options(['immediate' => 'Immediate — settle per sale', 'end_of_day' => 'End of day — batch settlement']),
            ]],
            ['dont_settle_to_account', [Pos::KEY_DONT_SETTLE_TO_ACCOUNT], 'Payments', "Don't settle to account", 'toggle', ['desktop'], false],
            ['show_account_info', [Pos::KEY_SHOW_ACCOUNT_INFO], 'Payments', 'Show payment details', 'toggle', self::ALL, true],
            ['grn_payment_source', [GrnPaymentSettlementService::SETTING_GRN_PAYMENT_SOURCE], 'Payments', 'Goods receive: default pay from', 'select', ['desktop', 'lite'], null, [
                'options' => self::options(['account' => 'Bank / Cash Account', 'expense' => 'Business Expense (no account)']),
                'default_label' => 'Bank / Cash Account if the business has one, otherwise Business Expense',
            ]],

            // ── Receipt ─────────────────────────────────────────────────────
            ['receipt_mode', [Pos::KEY_RECEIPT_MODE], 'Receipt', 'After a sale, print', 'select', ['desktop', 'lite', 'web'], 'bill', [
                'options' => self::options([
                    'bill' => 'Bill Printing — thermal receipt after each sale',
                    'invoice' => 'Invoice — create & print a formal invoice after each sale',
                ]),
            ]],
            ['receipt_header', [Pos::KEY_RECEIPT_HEADER], 'Receipt', 'Receipt header', 'textarea', self::ALL, '', ['max' => 200]],
            ['receipt_footer', [Pos::KEY_RECEIPT_FOOTER], 'Receipt', 'Receipt footer', 'textarea', self::ALL, 'Thank you for your purchase!', ['max' => 200]],
            ['show_business_name', [Pos::KEY_SHOW_BUSINESS_NAME], 'Receipt', 'Show business name', 'toggle', self::ALL, true],
            ['show_business_address', [Pos::KEY_SHOW_BUSINESS_ADDRESS], 'Receipt', 'Show business address', 'toggle', self::ALL, false],
            ['receipt_address_line', [Pos::KEY_RECEIPT_ADDRESS], 'Receipt', 'Receipt address', 'textarea', self::ALL, '', ['max' => 300]],
            ['receipt_language', [Pos::KEY_RECEIPT_LANGUAGE], 'Receipt', 'Receipt language', 'select', ['desktop', 'lite', 'web'], 'en', [
                'options' => self::options(['en' => 'English', 'si' => 'Sinhala', 'ta' => 'Tamil']),
            ]],
            ['receipt_paper_width', [Pos::KEY_RECEIPT_PAPER_WIDTH], 'Receipt', 'Paper width', 'select', ['mobile', 'web'], '80', [
                'options' => self::options(['80' => '80 mm', '58' => '58 mm']),
                'help' => 'POS Desktop and POS Lite keep their own paper width on each device.',
            ]],
            ['receipt_logo_url', [Pos::KEY_RECEIPT_LOGO_URL], 'Receipt', 'Receipt logo', 'url', self::ALL, ''],
            ['receipt_logo_size', [Pos::KEY_RECEIPT_LOGO_SIZE], 'Receipt', 'Logo size', 'number', ['desktop', 'lite'], Pos::RECEIPT_LOGO_SIZE_DEFAULT, [
                'min' => Pos::RECEIPT_LOGO_SIZE_MIN, 'max' => Pos::RECEIPT_LOGO_SIZE_MAX, 'integer' => true, 'unit' => '%',
            ]],
            ['receipt_logo_gap', [Pos::KEY_RECEIPT_LOGO_GAP], 'Receipt', 'Gap below logo', 'number', ['desktop', 'lite', 'web'], Pos::RECEIPT_LOGO_GAP_DEFAULT_MM, [
                'min' => 0, 'max' => Pos::RECEIPT_MARGIN_MAX_MM, 'step' => 0.5, 'unit' => 'mm',
            ]],
            ...array_map(fn (string $side) => ['receipt_margin_'.$side, ['pos.receipt_margin_'.$side], 'Receipt', ucfirst($side).' margin', 'number', ['desktop', 'lite', 'web'], 0, [
                'min' => 0, 'max' => Pos::RECEIPT_MARGIN_MAX_MM, 'step' => 0.5, 'unit' => 'mm',
            ]], Pos::RECEIPT_MARGIN_SIDES),

            // ── Tax ─────────────────────────────────────────────────────────
            ['tax_enabled', [Pos::KEY_TAX_ENABLED], 'Tax', 'Enable tax', 'toggle', ['desktop'], false],
            ['tax_rules', [Pos::KEY_TAX_RULES], 'Tax', 'Tax rules', 'tax_rules', ['desktop', 'web'], []],

            // ── Invoices ────────────────────────────────────────────────────
            ['invoice_prefix', [Pos::KEY_INVOICE_PREFIX], 'Invoices', 'Invoice prefix', 'text', ['desktop', 'lite'], 'INV', ['max' => 20]],
            ['invoice_next_number', [Pos::KEY_INVOICE_NEXT_NUMBER], 'Invoices', 'Next invoice number', 'number', ['desktop'], 1, [
                'min' => 1, 'integer' => true,
                'help' => 'Shown in the desktop settings form. Invoice numbers are worked out from the last invoice, so this does not change them.',
            ]],

            // ── Delivery ────────────────────────────────────────────────────
            ['delivery_enabled', [Pos::KEY_DELIVERY_ENABLED], 'Delivery', 'Enable delivery', 'toggle', ['desktop', 'lite', 'web'], false],
            ['delivery_methods', [Pos::KEY_DELIVERY_METHODS], 'Delivery', 'Delivery partners', 'multiselect', ['desktop', 'lite', 'web'], [], [
                'options' => self::options(self::DELIVERY_LABELS),
            ]],
            ['courier_services', [Pos::KEY_COURIER_SERVICES], 'Delivery', 'Courier services', 'courier', ['desktop'], array_fill_keys(Pos::COURIER_SERVICE_KEYS, ['enabled' => false, 'charge' => 0]), [
                'options' => self::options(self::COURIER_LABELS),
                'help' => 'Tick a service to turn it on. The number beside it is its delivery charge.',
            ]],

            // ── Purchasing ──────────────────────────────────────────────────
            ['purchasing_mode', [Pos::KEY_PURCHASING_MODE, Pos::KEY_PURCHASE_ORDER_ENABLED], 'Purchasing', 'Purchasing workflow', 'select', ['desktop'], 'po_only', [
                'options' => self::options([
                    'po_only' => 'Purchase Order workflow — create a Purchase Order first, then Goods Receive',
                    'direct_only' => 'Direct Goods Receive — receive goods without a Purchase Order',
                    'both' => 'Both — use Purchase Orders or receive goods directly',
                ]),
            ]],

            // ── Customers & suppliers (enforced by the API each of these apps saves through) ──
            ['customer_require_phone', [Pos::KEY_CUSTOMER_REQUIRE_PHONE], 'Customers & suppliers', 'Customer phone required', 'toggle', ['desktop', 'mobile', 'lite'], false],
            ['customer_require_email', [Pos::KEY_CUSTOMER_REQUIRE_EMAIL], 'Customers & suppliers', 'Customer email required', 'toggle', ['desktop', 'mobile', 'lite'], false],
            ['customer_require_address', [Pos::KEY_CUSTOMER_REQUIRE_ADDRESS], 'Customers & suppliers', 'Customer address required', 'toggle', ['desktop', 'mobile', 'lite'], false],
            ['supplier_require_phone', [Pos::KEY_SUPPLIER_REQUIRE_PHONE], 'Customers & suppliers', 'Supplier phone required', 'toggle', ['desktop', 'mobile', 'lite'], false],
            ['supplier_require_email', [Pos::KEY_SUPPLIER_REQUIRE_EMAIL], 'Customers & suppliers', 'Supplier email required', 'toggle', ['desktop', 'mobile', 'lite'], false],
            ['supplier_require_address', [Pos::KEY_SUPPLIER_REQUIRE_ADDRESS], 'Customers & suppliers', 'Supplier address required', 'toggle', ['desktop', 'mobile', 'lite'], false],

            // ── Notifications ───────────────────────────────────────────────
            ['large_sale_threshold', ['pos_notifications.large_sale_threshold'], 'Notifications', 'Large sale alert above', 'number', self::ALL, null, [
                'min' => 0, 'step' => 0.01, 'nullable' => true,
                'help' => 'Sales above this amount send a notification. Leave empty for no alert.',
            ]],
        ];

        $definitions = [];
        foreach ($items as $item) {
            [$field, $keys, $section, $label, $type, $surfaces, $default] = $item;
            $definitions[$field] = ($item[7] ?? []) + [
                'field' => $field,
                'keys' => $keys,
                'section' => $section,
                'label' => $label,
                'type' => $type,
                'surfaces' => $surfaces,
                'default' => $default,
                // Deleting needs a row to remove; the business name has none.
                'deletable' => $keys !== [],
            ];
        }

        return $definitions;
    }

    /** @return list<array{value: string, label: string}> */
    public static function currencyOptions(string $current = ''): array
    {
        $labels = array_map(fn (string $name, string $code) => "{$code} — {$name}", self::CURRENCIES, array_keys(self::CURRENCIES));
        $options = self::options(array_combine(array_keys(self::CURRENCIES), $labels));

        // A business can already use a code outside this short list; it must stay selectable.
        if ($current !== '' && ! isset(self::CURRENCIES[$current])) {
            array_unshift($options, ['value' => $current, 'label' => $current]);
        }

        return $options;
    }

    /** @return list<array{value: string, label: string}> */
    private static function options(array $labels): array
    {
        return array_map(
            fn (string $value, string $label) => ['value' => (string) $value, 'label' => $label],
            array_map('strval', array_keys($labels)),
            array_values($labels),
        );
    }
}
