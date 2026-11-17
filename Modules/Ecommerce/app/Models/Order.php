<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES         = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
    public const PAYMENT_METHODS  = ['cod', 'bank_transfer', 'stripe', 'payhere'];
    public const ONLINE_METHODS   = ['stripe', 'payhere'];
    public const PAYMENT_STATUSES = ['unpaid', 'paid', 'refunded'];

    protected $table = 'ecom_orders';

    protected $fillable = [
        'business_id',
        'order_no',
        'access_token',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_address',
        'shipping_city',
        'notes',
        'status',
        'payment_method',
        'payment_status',
        'payment_reference',
        'paid_at',
        'subtotal',
        'shipping_fee',
        'total',
        'tracking_no',
        'internal_note',
        'placed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'     => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total'        => 'decimal:2',
            'placed_at'    => 'datetime',
            'paid_at'      => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }
}
