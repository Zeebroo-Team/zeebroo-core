<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

/**
 * A delivery term picked for a quotation line, e.g. "Within 04-06 weeks from confirmed PO".
 * Printed in the line's note as "Delivery: <label>".
 */
class DeliveryTerm extends Model
{
    protected $table = 'crm_delivery_terms';

    /** Seeded for every business. */
    public const DEFAULTS = [
        'Ex-stock subject to prior sales',
        'Within 02-03 weeks from confirmed PO',
        'Within 04-06 weeks from confirmed PO',
        'Within 06-08 weeks from confirmed PO',
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
