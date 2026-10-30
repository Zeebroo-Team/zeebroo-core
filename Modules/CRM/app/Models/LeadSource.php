<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

/** A channel leads arrive through (manual, web form, CSV, Google Form, Facebook, WhatsApp, …). */
class LeadSource extends Model
{
    protected $table = 'crm_lead_sources';

    /** Seeded for every business: key => label. */
    public const DEFAULTS = [
        'manual'      => 'Manual entry',
        'web_form'    => 'Web form',
        'csv'         => 'CSV import',
        'google_form' => 'Google Form',
        'facebook'    => 'Facebook',
        'whatsapp'    => 'WhatsApp',
    ];

    /**
     * Source keys whose crm_leads.source value predates this table and is spelled
     * differently: source key => Lead::SOURCES key.
     */
    public const LEAD_SOURCE_ALIASES = [
        'web_form'    => 'public-form',
        'google_form' => 'google-form',
    ];

    protected $fillable = [
        'business_id',
        'key',
        'label',
        'is_active',
        'config',
    ];

    protected $hidden = ['config'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config'    => 'encrypted:array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** The crm_leads.source value for this source. */
    public function leadSourceKey(): string
    {
        return self::LEAD_SOURCE_ALIASES[$this->key] ?? $this->key;
    }

    /** The source key a crm_leads.source value belongs to. */
    public static function keyForLeadSource(string $leadSource): string
    {
        return array_search($leadSource, self::LEAD_SOURCE_ALIASES, true) ?: $leadSource;
    }
}
