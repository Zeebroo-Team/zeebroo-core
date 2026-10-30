<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

/** CRM-wide settings of one business (see CrmSettingsService). */
class CrmSetting extends Model
{
    protected $table = 'crm_settings';

    protected $fillable = [
        'business_id',
        'phone_country_code',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
