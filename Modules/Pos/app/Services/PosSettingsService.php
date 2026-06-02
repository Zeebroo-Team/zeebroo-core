<?php

namespace Modules\Pos\Services;

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
            'default_deposit_account_id' => $accountId,
            'discount_field_enabled' => (bool) $business->getSetting(self::KEY_DISCOUNT_FIELD_ENABLED, false),
            'checkout_modal_enabled' => (bool) $business->getSetting(self::KEY_CHECKOUT_MODAL_ENABLED, false),
            'display_theme' => $theme,
            'receipt_header' => (string) $business->getSetting(self::KEY_RECEIPT_HEADER, ''),
            'receipt_footer' => (string) $business->getSetting(self::KEY_RECEIPT_FOOTER, 'Thank you for your purchase!'),
            'show_business_name' => (bool) $business->getSetting(self::KEY_SHOW_BUSINESS_NAME, true),
            'show_business_address' => (bool) $business->getSetting(self::KEY_SHOW_BUSINESS_ADDRESS, true),
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
        $business->setSetting(self::KEY_SHOW_BUSINESS_ADDRESS, filter_var($data['show_business_address'] ?? true, FILTER_VALIDATE_BOOLEAN));
    }
}
