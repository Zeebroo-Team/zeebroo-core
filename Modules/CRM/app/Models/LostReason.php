<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

/** Why a lead was lost — picked whenever a lead moves to a lost stage. */
class LostReason extends Model
{
    protected $table = 'crm_lost_reasons';

    /** Seeded for every business. */
    public const DEFAULTS = [
        'Price too high',
        'Went with a competitor',
        'No budget',
        'No response from customer',
        'Requirement changed / cancelled',
        'Product or service not available',
        'Delivery time too long',
        'Duplicate or invalid lead',
        'Other',
    ];

    protected $fillable = [
        'business_id',
        'label',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
