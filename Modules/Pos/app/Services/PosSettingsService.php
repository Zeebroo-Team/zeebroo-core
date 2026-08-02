<?php

namespace Modules\Pos\Services;

use Illuminate\Support\Str;
use Modules\Account\Models\Account;
use Modules\Business\Models\Business;

class PosSettingsService
{
    public const KEY_DEFAULT_DEPOSIT_ACCOUNT = 'pos.default_deposit_account_id';

    public const KEY_DISCOUNT_FIELD_ENABLED = 'pos.discount_field_enabled';

    /** @var string `inherit` | `light` | `dark` */
    public const KEY_DISPLAY_THEME = 'pos.display_theme';

    public const KEY_RECEIPT_HEADER = 'pos.receipt_header';

    public const KEY_RECEIPT_FOOTER = 'pos.receipt_footer';

    public const KEY_SHOW_BUSINESS_NAME = 'pos.show_business_name';

    public const KEY_SHOW_BUSINESS_ADDRESS = 'pos.show_business_address';

    public const KEY_CHECKOUT_MODAL_ENABLED = 'pos.checkout_modal_enabled';

    public const KEY_SHOW_ACCOUNT_INFO = 'pos.show_account_info';

    /** @var string `immediate` | `end_of_day` */
    public const KEY_PAYMENT_SETTLEMENT_MODE = 'pos.payment_settlement_mode';

    public const KEY_FEATURED_PRODUCTS_LIMIT = 'pos.featured_products_limit';

    public const KEY_FEATURED_CATEGORIES_LIMIT = 'pos.featured_categories_limit';

    public const KEY_SHOW_SERVICE_BOUND_PRODUCTS = 'pos.show_service_bound_products';

    public const KEY_DONT_SETTLE_TO_ACCOUNT = 'pos.dont_settle_to_account';

    /** @var string `fifo` | `choose` | `last_price` */
    public const KEY_STOCK_SELECTION_MODE = 'pos.stock_selection_mode';

    public const KEY_CHOOSE_PRICE = 'pos.choose_price';

    /** @var string `bill` | `invoice` */
    public const KEY_RECEIPT_MODE = 'pos.receipt_mode';

    public const KEY_RECEIPT_LOGO_URL = 'pos.receipt_logo_url';

    public const KEY_RECEIPT_ADDRESS = 'pos.receipt_address';

    /** @var string `en` | `si` | `ta` */
    public const KEY_RECEIPT_LANGUAGE = 'pos.receipt_language';

    public const KEY_TAX_ENABLED = 'tax.enabled';

    public const KEY_TAX_RATE = 'tax.rate';

    public const KEY_TAX_RULES = 'tax.rules';

    public const KEY_INVOICE_PREFIX = 'invoice.prefix';

    public const KEY_INVOICE_NEXT_NUMBER = 'invoice.next_number';

    public const KEY_DELIVERY_ENABLED = 'delivery.enabled';

    public const KEY_DELIVERY_METHODS = 'delivery.methods';

    public const DELIVERY_METHOD_KEYS = ['dhl', 'fedex', 'uber', 'pickme', 'koobiyo', 'pronto'];

    /**
     * @return array{
     *     default_deposit_account_id: ?int,
     *     discount_field_enabled: bool,
     *     checkout_modal_enabled: bool,
     *     display_theme: string,
     *     receipt_header: string,
     *     receipt_footer: string,
     *     show_business_name: bool,
     *     show_business_address: bool,
     *     show_account_info: bool,
     *     payment_settlement_mode: string,
     *     featured_products_limit: int,
     *     featured_categories_limit: int,
     *     show_service_bound_products: bool,
     * }
     */
    public function forBusiness(Business $business): array
    {
        $accountId = $business->getSetting(self::KEY_DEFAULT_DEPOSIT_ACCOUNT, null);
        $accountId = $accountId !== null && $accountId !== '' ? (int) $accountId : null;

        $theme = (string) $business->getSetting(self::KEY_DISPLAY_THEME, 'inherit');
        if (! in_array($theme, ['inherit', 'light', 'dark'], true)) {
            $theme = 'inherit';
        }

        return [
            // Business profile
            'business_name'    => (string) $business->name,
            'slug'             => Str::slug($business->name),
            'currency'         => (string) ($business->getSetting('business.currency', '') ?: ''),
            'timezone'         => (string) ($business->getSetting('business.timezone', '') ?: ''),
            // POS
            'default_deposit_account_id' => $accountId,
            'discount_field_enabled' => (bool) $business->getSetting(self::KEY_DISCOUNT_FIELD_ENABLED, false),
            'checkout_modal_enabled' => (bool) $business->getSetting(self::KEY_CHECKOUT_MODAL_ENABLED, false),
            'display_theme' => $theme,
            'receipt_header' => (string) $business->getSetting(self::KEY_RECEIPT_HEADER, ''),
            'receipt_footer' => (string) $business->getSetting(self::KEY_RECEIPT_FOOTER, 'Thank you for your purchase!'),
            'show_business_name' => (bool) $business->getSetting(self::KEY_SHOW_BUSINESS_NAME, true),
            'show_business_address' => (bool) $business->getSetting(self::KEY_SHOW_BUSINESS_ADDRESS, false),
            'receipt_address_line' => (string) ($business->getSetting(self::KEY_RECEIPT_ADDRESS, '') ?: ''),
            'receipt_language'    => (function () use ($business) {
                $v = strtolower(trim((string) ($business->getSetting(self::KEY_RECEIPT_LANGUAGE, 'en') ?: 'en')));
                return in_array($v, ['en', 'si', 'ta'], true) ? $v : 'en';
            })(),
            'show_account_info' => (bool) $business->getSetting(self::KEY_SHOW_ACCOUNT_INFO, true),
            'payment_settlement_mode' => (string) $business->getSetting(self::KEY_PAYMENT_SETTLEMENT_MODE, 'immediate'),
            'featured_products_limit' => max(0, (int) $business->getSetting(self::KEY_FEATURED_PRODUCTS_LIMIT, 0)),
            'featured_categories_limit' => max(0, (int) $business->getSetting(self::KEY_FEATURED_CATEGORIES_LIMIT, 0)),
            'show_service_bound_products' => (bool) $business->getSetting(self::KEY_SHOW_SERVICE_BOUND_PRODUCTS, true),
            'dont_settle_to_account'     => (bool) $business->getSetting(self::KEY_DONT_SETTLE_TO_ACCOUNT, false),
            'stock_selection_mode'       => (function () use ($business) {
                $v = strtolower(trim((string) ($business->getSetting(self::KEY_STOCK_SELECTION_MODE, 'fifo') ?: 'fifo')));
                return in_array($v, ['fifo', 'choose', 'last_price'], true) ? $v : 'fifo';
            })(),
            'choose_price'               => (bool) $business->getSetting(self::KEY_CHOOSE_PRICE, false),
            'receipt_logo_url'           => (string) ($business->getSetting(self::KEY_RECEIPT_LOGO_URL, '') ?: ''),
            'receipt_mode'               => (function () use ($business) {
                $v = strtolower(trim((string) ($business->getSetting(self::KEY_RECEIPT_MODE, 'bill') ?: 'bill')));
                return in_array($v, ['bill', 'invoice'], true) ? $v : 'bill';
            })(),
            // Branch / warehouse
            'multi_warehouse_branch'   => (bool) $business->getSetting('business.multi_warehouse_branch', false),
            'branch_product_separate'  => (bool) $business->getSetting('business.branch_product_separate', false),
            'branch_stock_separate'    => (bool) $business->getSetting('business.branch_stock_separate', false),
            'branch_pos_separate'      => (bool) $business->getSetting('business.branch_pos_separate', false),
            // Tax
            'tax_enabled'   => (bool) $business->getSetting(self::KEY_TAX_ENABLED, false),
            'tax_rules'     => (function () use ($business) {
                $raw   = $business->getSetting(self::KEY_TAX_RULES, []);
                $rules = is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?? []) : []);
                return array_values(array_filter($rules, fn ($r) => is_array($r) && isset($r['name'], $r['type'], $r['value'])));
            })(),
            // Invoice
            'invoice_prefix'      => (string) ($business->getSetting(self::KEY_INVOICE_PREFIX, 'INV') ?: 'INV'),
            'invoice_next_number' => max(1, (int) $business->getSetting(self::KEY_INVOICE_NEXT_NUMBER, 1)),
            // Delivery
            'delivery_enabled' => (bool) $business->getSetting(self::KEY_DELIVERY_ENABLED, false),
            'delivery_methods' => (function () use ($business): array {
                $raw = $business->getSetting(self::KEY_DELIVERY_METHODS, []);
                $methods = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) ?? [] : []);
                return array_values(array_intersect($methods, self::DELIVERY_METHOD_KEYS));
            })(),
        ];
    }

    /**
     * @param  array{
     *     default_deposit_account_id?: int|string|null,
     *     discount_field_enabled?: bool|string|null,
     *     display_theme?: string|null,
     *     receipt_header?: string|null,
     *     receipt_footer?: string|null,
     *     show_business_name?: bool|string|null,
     *     show_business_address?: bool|string|null,
     *     show_account_info?: bool|string|null,
     * }  $data
     */
    public function saveForBusiness(Business $business, array $data): void
    {
        $rawAccount = $data['default_deposit_account_id'] ?? null;
        if ($rawAccount === null || $rawAccount === '') {
            $business->setSetting(self::KEY_DEFAULT_DEPOSIT_ACCOUNT, null);
        } else {
            $accountId = (int) $rawAccount;
            $exists = Account::query()
                ->whereKey($accountId)
                ->where('business_id', $business->id)
                ->exists();
            if ($exists) {
                $business->setSetting(self::KEY_DEFAULT_DEPOSIT_ACCOUNT, $accountId);
            }
        }

        $business->setSetting(
            self::KEY_DISCOUNT_FIELD_ENABLED,
            filter_var($data['discount_field_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );

        $business->setSetting(
            self::KEY_CHECKOUT_MODAL_ENABLED,
            filter_var($data['checkout_modal_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );

        $theme = strtolower(trim((string) ($data['display_theme'] ?? 'light')));
        if (! in_array($theme, ['light', 'dark'], true)) {
            $theme = 'light';
        }
        $business->setSetting(self::KEY_DISPLAY_THEME, $theme);

        $business->setSetting(self::KEY_RECEIPT_HEADER, substr(trim((string) ($data['receipt_header'] ?? '')), 0, 200));
        $business->setSetting(self::KEY_RECEIPT_FOOTER, substr(trim((string) ($data['receipt_footer'] ?? '')), 0, 200));
        $business->setSetting(self::KEY_SHOW_BUSINESS_NAME, filter_var($data['show_business_name'] ?? true, FILTER_VALIDATE_BOOLEAN));
        $business->setSetting(self::KEY_SHOW_BUSINESS_ADDRESS, filter_var($data['show_business_address'] ?? false, FILTER_VALIDATE_BOOLEAN));
        $business->setSetting(self::KEY_SHOW_ACCOUNT_INFO, filter_var($data['show_account_info'] ?? true, FILTER_VALIDATE_BOOLEAN));
        if (array_key_exists('receipt_address_line', $data)) {
            $business->setSetting(self::KEY_RECEIPT_ADDRESS, substr(trim((string) ($data['receipt_address_line'] ?? '')), 0, 300));
        }
        if (array_key_exists('receipt_language', $data)) {
            $lang = strtolower(trim((string) ($data['receipt_language'] ?? 'en')));
            $business->setSetting(self::KEY_RECEIPT_LANGUAGE, in_array($lang, ['en', 'si', 'ta'], true) ? $lang : 'en');
        }

        $mode = strtolower(trim((string) ($data['payment_settlement_mode'] ?? 'immediate')));
        if (!in_array($mode, ['immediate', 'end_of_day'], true)) {
            $mode = 'immediate';
        }
        $business->setSetting(self::KEY_PAYMENT_SETTLEMENT_MODE, $mode);

        $productsLimit = max(0, (int) ($data['featured_products_limit'] ?? 0));
        $business->setSetting(self::KEY_FEATURED_PRODUCTS_LIMIT, $productsLimit > 0 ? $productsLimit : null);

        $categoriesLimit = max(0, (int) ($data['featured_categories_limit'] ?? 0));
        $business->setSetting(self::KEY_FEATURED_CATEGORIES_LIMIT, $categoriesLimit > 0 ? $categoriesLimit : null);

        $business->setSetting(
            self::KEY_SHOW_SERVICE_BOUND_PRODUCTS,
            filter_var($data['show_service_bound_products'] ?? true, FILTER_VALIDATE_BOOLEAN),
        );

        if (array_key_exists('dont_settle_to_account', $data)) {
            $business->setSetting(
                self::KEY_DONT_SETTLE_TO_ACCOUNT,
                filter_var($data['dont_settle_to_account'] ?? false, FILTER_VALIDATE_BOOLEAN),
            );
        }

        if (array_key_exists('stock_selection_mode', $data)) {
            $mode = strtolower(trim((string) ($data['stock_selection_mode'] ?? 'fifo')));
            if (!in_array($mode, ['fifo', 'choose', 'last_price'], true)) {
                $mode = 'fifo';
            }
            $business->setSetting(self::KEY_STOCK_SELECTION_MODE, $mode);
        }

        if (array_key_exists('choose_price', $data)) {
            $business->setSetting(
                self::KEY_CHOOSE_PRICE,
                filter_var($data['choose_price'] ?? false, FILTER_VALIDATE_BOOLEAN),
            );
        }

        if (array_key_exists('receipt_mode', $data)) {
            $receiptMode = strtolower(trim((string) ($data['receipt_mode'] ?? 'bill')));
            $business->setSetting(self::KEY_RECEIPT_MODE, in_array($receiptMode, ['bill', 'invoice'], true) ? $receiptMode : 'bill');
        }

        if (array_key_exists('receipt_logo_url', $data)) {
            $business->setSetting(self::KEY_RECEIPT_LOGO_URL, $data['receipt_logo_url'] ? trim((string) $data['receipt_logo_url']) : null);
        }

        // Branch / warehouse
        if (array_key_exists('multi_warehouse_branch', $data)) {
            $business->setSetting('business.multi_warehouse_branch', filter_var($data['multi_warehouse_branch'], FILTER_VALIDATE_BOOLEAN));
        }
        if (array_key_exists('branch_product_separate', $data)) {
            $business->setSetting('business.branch_product_separate', filter_var($data['branch_product_separate'], FILTER_VALIDATE_BOOLEAN));
        }
        if (array_key_exists('branch_stock_separate', $data)) {
            $business->setSetting('business.branch_stock_separate', filter_var($data['branch_stock_separate'], FILTER_VALIDATE_BOOLEAN));
        }
        if (array_key_exists('branch_pos_separate', $data)) {
            $business->setSetting('business.branch_pos_separate', filter_var($data['branch_pos_separate'], FILTER_VALIDATE_BOOLEAN));
        }

        // Tax
        if (array_key_exists('tax_enabled', $data)) {
            $business->setSetting(self::KEY_TAX_ENABLED, filter_var($data['tax_enabled'], FILTER_VALIDATE_BOOLEAN));
        }
        if (array_key_exists('tax_rules', $data)) {
            $rules = is_array($data['tax_rules']) ? $data['tax_rules'] : [];
            $sanitized = [];
            foreach ($rules as $r) {
                if (! is_array($r)) { continue; }
                $name  = substr(trim((string) ($r['name'] ?? '')), 0, 50);
                $type  = in_array($r['type'] ?? '', ['percentage', 'flat'], true) ? $r['type'] : 'percentage';
                $value = max(0, (float) ($r['value'] ?? 0));
                $id    = isset($r['id']) ? substr((string) $r['id'], 0, 36) : (string) \Illuminate\Support\Str::uuid();
                if ($name !== '') {
                    $sanitized[] = ['id' => $id, 'name' => $name, 'type' => $type, 'value' => $value];
                }
            }
            $business->setSetting(self::KEY_TAX_RULES, $sanitized);
        }

        // Invoice
        if (array_key_exists('invoice_prefix', $data)) {
            $prefix = strtoupper(trim((string) ($data['invoice_prefix'] ?? 'INV')));
            $business->setSetting(self::KEY_INVOICE_PREFIX, $prefix !== '' ? $prefix : 'INV');
        }
        if (array_key_exists('invoice_next_number', $data)) {
            $next = max(1, (int) ($data['invoice_next_number'] ?? 1));
            $business->setSetting(self::KEY_INVOICE_NEXT_NUMBER, $next);
        }

        // Delivery
        if (array_key_exists('delivery_enabled', $data)) {
            $business->setSetting(self::KEY_DELIVERY_ENABLED, filter_var($data['delivery_enabled'], FILTER_VALIDATE_BOOLEAN));
        }
        if (array_key_exists('delivery_methods', $data)) {
            $methods = is_array($data['delivery_methods']) ? $data['delivery_methods'] : [];
            $business->setSetting(
                self::KEY_DELIVERY_METHODS,
                array_values(array_intersect($methods, self::DELIVERY_METHOD_KEYS)),
            );
        }

        // Business profile
        if (isset($data['business_name']) && trim($data['business_name']) !== '') {
            $business->name = trim($data['business_name']);
            $business->save();
        }
        if (array_key_exists('currency', $data)) {
            $business->setSetting('business.currency', strtoupper(trim((string) ($data['currency'] ?? ''))));
        }
        if (array_key_exists('timezone', $data)) {
            $business->setSetting('business.timezone', trim((string) ($data['timezone'] ?? '')));
        }
    }
}
