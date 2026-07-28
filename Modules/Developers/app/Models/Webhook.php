<?php

namespace Modules\Developers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Business\Models\Business;

class Webhook extends Model
{
    protected $table = 'developer_webhooks';

    protected $fillable = [
        'business_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'failure_count',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'events'            => 'array',
            'is_active'         => 'boolean',
            'failure_count'     => 'integer',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public static function availableEvents(): array
    {
        return [
            'sale.created'     => 'Sale Created',
            'sale.voided'      => 'Sale Voided',
            'sale.refunded'    => 'Sale Refunded',
            'invoice.created'  => 'Invoice Created',
            'invoice.paid'     => 'Invoice Paid',
            'product.created'  => 'Product Created',
            'product.updated'  => 'Product Updated',
            'stock.updated'    => 'Stock Updated',
            'grn.created'      => 'GRN Created',
            'customer.created' => 'Customer Created',
            'order.created'    => 'Purchase Order Created',
        ];
    }

    public static function generateSecret(): string
    {
        return Str::random(32);
    }
}
