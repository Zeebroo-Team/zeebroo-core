<?php

namespace Modules\Restaurant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class IngredientGrn extends Model
{
    protected $table = 'restaurant_ingredient_grns';

    protected $fillable = [
        'business_id',
        'purchase_order_id',
        'grn_number',
        'received_date',
        'payment_method',
        'reference',
        'notes',
        'subtotal',
        'total',
    ];

    protected $casts = [
        'received_date' => 'date',
        'subtotal'      => 'decimal:2',
        'total'         => 'decimal:2',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(IngredientPurchaseOrder::class, 'purchase_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(IngredientGrnItem::class, 'grn_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'cash'   => 'Cash',
            'cheque' => 'Cheque',
            'credit' => 'Credit / On Account',
            default  => ucfirst((string) $this->payment_method),
        };
    }
}
