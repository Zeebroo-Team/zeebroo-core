<?php

namespace Modules\CRM\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Business;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Support\PhoneNormalizer;

/** CRM-wide settings of a business (crm_settings). */
class CrmSettingsService
{
    /** @var array<int, ?CrmSetting> */
    private array $cache = [];

    /** @var array<int, string> */
    private array $timezones = [];

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

    /** Inquiries a head may hand one user per day (CrmSetting::DEFAULT_DAILY_INQUIRY_LIMIT when unset). */
    public function dailyInquiryLimit(Business|int $business): int
    {
        $limit = $this->forBusiness($business)?->daily_inquiry_limit;

        return $limit !== null && $limit > 0 ? (int) $limit : CrmSetting::DEFAULT_DAILY_INQUIRY_LIMIT;
    }

    /**
     * The daily inquiry report settings: whether it runs, at what time (HH:MM, business
     * timezone), whether the head reviews it before it goes out, and who gets it.
     *
     * @return array{enabled: bool, time: string, review: bool, recipients: string[]}
     */
    public function inquiryReport(Business|int $business): array
    {
        $s = $this->forBusiness($business);

        return [
            'enabled'    => (bool) ($s?->inquiry_report_enabled ?? false),
            'time'       => preg_match('/^\d{2}:\d{2}$/', (string) $s?->inquiry_report_time) ? $s->inquiry_report_time : '18:00',
            'review'     => (bool) ($s?->inquiry_report_review ?? false),
            'recipients' => array_values(array_filter((array) ($s?->inquiry_report_recipients ?? []))),
        ];
    }

    /** The business's own timezone (POS setting business.timezone), or the app's when unset / invalid. */
    public function timezone(Business|int $business): string
    {
        $businessId = $business instanceof Business ? $business->id : $business;

        return $this->timezones[$businessId] ??= (function () use ($business, $businessId) {
            $model = $business instanceof Business ? $business : Business::find($businessId);
            $tz    = trim((string) ($model?->getSetting('business.timezone', '') ?? ''));
            try {
                return $tz !== '' ? (new \DateTimeZone($tz))->getName() : (string) config('app.timezone');
            } catch (\Throwable) {
                return (string) config('app.timezone');
            }
        })();
    }

    /**
     * Midnight that starts the business's current day, in the app's timezone (how dates are
     * stored) — so "today" / "overdue" follow the business's calendar, not the server's.
     */
    public function startOfToday(Business|int $business, ?CarbonInterface $now = null): Carbon
    {
        return Carbon::instance($now ?? now())->setTimezone($this->timezone($business))->startOfDay()->setTimezone(config('app.timezone'));
    }

    public function present(Business $business): array
    {
        $report = $this->inquiryReport($business);

        return [
            'phone_country_code'           => $this->forBusiness($business)?->phone_country_code,
            'effective_phone_country_code' => $this->phoneCountryCode($business),
            'default_phone_country_code'   => (string) config('crm.default_phone_country_code', '94'),
            'quotation_stock_check'        => $this->quotationStockCheck($business),
            'daily_inquiry_limit'          => $this->dailyInquiryLimit($business),
            'inquiry_report_enabled'       => $report['enabled'],
            'inquiry_report_time'          => $report['time'],
            'inquiry_report_review'        => $report['review'],
            'inquiry_report_recipients'    => $report['recipients'],
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

        if (array_key_exists('daily_inquiry_limit', $data)) {
            $limit = (int) $data['daily_inquiry_limit'];
            if ($limit < 1 || $limit > 500) {
                throw ValidationException::withMessages(['daily_inquiry_limit' => 'The daily limit must be between 1 and 500.']);
            }
            $values['daily_inquiry_limit'] = $limit;
        }

        foreach (['inquiry_report_enabled', 'inquiry_report_review'] as $key) {
            if (array_key_exists($key, $data)) {
                $values[$key] = (bool) $data[$key];
            }
        }

        if (array_key_exists('inquiry_report_time', $data)) {
            $time = trim((string) $data['inquiry_report_time']);
            if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
                throw ValidationException::withMessages(['inquiry_report_time' => 'Enter the time as HH:MM (24-hour).']);
            }
            $values['inquiry_report_time'] = $time;
        }

        if (array_key_exists('inquiry_report_recipients', $data)) {
            $list = is_array($data['inquiry_report_recipients'])
                ? $data['inquiry_report_recipients']
                : preg_split('/[\s,;]+/', (string) $data['inquiry_report_recipients']);
            $emails = array_values(array_unique(array_filter(array_map(fn ($e) => mb_strtolower(trim((string) $e)), $list))));
            foreach ($emails as $email) {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw ValidationException::withMessages(['inquiry_report_recipients' => "\"{$email}\" is not a valid email address."]);
                }
            }
            if (count($emails) > 30) {
                throw ValidationException::withMessages(['inquiry_report_recipients' => 'Add at most 30 recipients.']);
            }
            $values['inquiry_report_recipients'] = $emails;
        }

        $setting = CrmSetting::updateOrCreate(['business_id' => $business->id], $values);

        $this->cache[$business->id] = $setting;

        return $setting;
    }
}
