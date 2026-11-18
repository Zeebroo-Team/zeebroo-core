<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

/** CRM-wide settings of one business (see CrmSettingsService). */
class CrmSetting extends Model
{
    protected $table = 'crm_settings';

    /** What a lead quotation does when a line asks for more than is in stock. */
    public const STOCK_CHECK_WARN  = 'warn';
    public const STOCK_CHECK_BLOCK = 'block';
    public const STOCK_CHECK_OFF   = 'off';
    public const STOCK_CHECKS      = [self::STOCK_CHECK_WARN, self::STOCK_CHECK_BLOCK, self::STOCK_CHECK_OFF];

    protected $fillable = [
        'business_id',
        'phone_country_code',
        'quotation_stock_check',
        'daily_inquiry_limit',
        'inquiry_report_enabled',
        'inquiry_report_time',
        'inquiry_report_review',
        'inquiry_report_recipients',
        'inquiry_report_last_run',
    ];

    /** Inquiries a head may hand one user per day when nothing is saved. */
    public const DEFAULT_DAILY_INQUIRY_LIMIT = 10;

    protected function casts(): array
    {
        return [
            'daily_inquiry_limit'       => 'integer',
            'inquiry_report_enabled'    => 'boolean',
            'inquiry_report_review'     => 'boolean',
            'inquiry_report_recipients' => 'array',
            'inquiry_report_last_run'   => 'date',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
