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

    /**
     * @return array{
     *     default_deposit_account_id: ?int,
     *     discount_field_enabled: bool,
     *     display_theme: string,
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
            'display_theme' => $theme,
        ];
    }

    /**
     * @param  array{
     *     default_deposit_account_id?: int|string|null,
     *     discount_field_enabled?: bool|string|null,
     *     display_theme?: string|null,
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

        $theme = strtolower(trim((string) ($data['display_theme'] ?? 'light')));
        if (! in_array($theme, ['light', 'dark'], true)) {
            $theme = 'light';
        }
        $business->setSetting(self::KEY_DISPLAY_THEME, $theme);
    }
}
