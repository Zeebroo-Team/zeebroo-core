<?php

namespace Modules\CRM\Services;

use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Support\PhoneNormalizer;

/** CRM-wide settings of a business (crm_settings). */
class CrmSettingsService
{
    /** @var array<int, ?CrmSetting> */
    private array $cache = [];

    public function forBusiness(Business|int $business): ?CrmSetting
    {
        $businessId = $business instanceof Business ? $business->id : $business;

        if (! array_key_exists($businessId, $this->cache)) {
            $this->cache[$businessId] = CrmSetting::query()->where('business_id', $businessId)->first();
        }

        return $this->cache[$businessId];
    }

    /** The business's country calling code, or the app default. Digits only. */
    public function phoneCountryCode(Business|int $business): string
    {
        $code = $this->forBusiness($business)?->phone_country_code;

        return preg_replace('/\D+/', '', (string) ($code ?: config('crm.default_phone_country_code', '94')));
    }

    public function normalizePhone(Business|int $business, ?string $phone): ?string
    {
        return PhoneNormalizer::normalize($phone, $this->phoneCountryCode($business));
    }

    /** warn | block | off — see CrmSetting::STOCK_CHECKS. */
    public function quotationStockCheck(Business|int $business): string
    {
        $mode = $this->forBusiness($business)?->quotation_stock_check;

        return in_array($mode, CrmSetting::STOCK_CHECKS, true) ? $mode : CrmSetting::STOCK_CHECK_WARN;
    }

    /** @return array{phone_country_code: ?string, effective_phone_country_code: string, default_phone_country_code: string, quotation_stock_check: string} */
    public function present(Business $business): array
    {
        return [
            'phone_country_code'           => $this->forBusiness($business)?->phone_country_code,
            'effective_phone_country_code' => $this->phoneCountryCode($business),
            'default_phone_country_code'   => (string) config('crm.default_phone_country_code', '94'),
            'quotation_stock_check'        => $this->quotationStockCheck($business),
        ];
    }

    /** Saves the settings given; one left out keeps its value. */
    public function save(Business $business, array $data): CrmSetting
    {
        $values = [];

        if (array_key_exists('phone_country_code', $data)) {
            $code = preg_replace('/\D+/', '', (string) ($data['phone_country_code'] ?? ''));
            if (strlen($code) > 4) {
                throw ValidationException::withMessages(['phone_country_code' => 'A country code has at most 4 digits.']);
            }
            $values['phone_country_code'] = $code === '' ? null : $code;
        }

        if (array_key_exists('quotation_stock_check', $data)) {
            if (! in_array($data['quotation_stock_check'], CrmSetting::STOCK_CHECKS, true)) {
                throw ValidationException::withMessages(['quotation_stock_check' => 'Choose warn, block or off.']);
            }
            $values['quotation_stock_check'] = $data['quotation_stock_check'];
        }

        $setting = CrmSetting::updateOrCreate(['business_id' => $business->id], $values);

        $this->cache[$business->id] = $setting;

        return $setting;
    }
}
