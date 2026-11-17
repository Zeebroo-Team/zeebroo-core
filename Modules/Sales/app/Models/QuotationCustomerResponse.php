<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The customer's answer to a sent lead quotation (see QuotationNegotiationService in the CRM). */
class QuotationCustomerResponse extends Model
{
    const RESPONSE_ACCEPT           = 'accept';
    const RESPONSE_REJECT           = 'reject';
    const RESPONSE_DISCOUNT_REQUEST = 'discount_request';

    /** response => [label, Font Awesome icon, colour] */
    const RESPONSES = [
        self::RESPONSE_ACCEPT           => ['Customer accepted',          'fa-handshake',    '#10b981'],
        self::RESPONSE_REJECT           => ['Customer rejected',          'fa-thumbs-down',  '#ef4444'],
        self::RESPONSE_DISCOUNT_REQUEST => ['Customer asked for a discount', 'fa-percent',   '#f59e0b'],
    ];

    protected $fillable = [
        'quotation_id',
        'version',
        'response',
        'requested_discount',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'version'            => 'integer',
        'requested_discount' => 'decimal:2',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'recorded_by');
    }

    public function label(): string
    {
        return self::RESPONSES[$this->response][0] ?? ucfirst(str_replace('_', ' ', $this->response));
    }
}
