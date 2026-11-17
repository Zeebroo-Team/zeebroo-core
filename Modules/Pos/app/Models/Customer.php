<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class Customer extends Model
{
    protected $table = 'pos_customers';

    protected $fillable = ['business_id', 'client_uuid', 'name', 'phone', 'email', 'address', 'notes', 'customer_type', 'customer_category_id', 'credit_limit'];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'pos_customer_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class, 'pos_customer_id');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(ProductRental::class, 'pos_customer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CustomerCategory::class, 'customer_category_id');
    }

    /**
     * Adds a `credit_outstanding` column: the unpaid balance of the customer's completed credit sales.
     */
    public function scopeWithCreditOutstanding(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select($query->getModel()->getTable().'.*');
        }

        return $query->selectSub(
            Sale::query()
                ->selectRaw('COALESCE(SUM(total - amount_paid), 0)')
                ->whereColumn('pos_sales.pos_customer_id', 'pos_customers.id')
                ->where('payment_method', Sale::PAYMENT_CREDIT)
                ->where('status', Sale::STATUS_COMPLETED),
            'credit_outstanding',
        );
    }

    public function displayLabel(): string
    {
        return $this->name . ($this->phone ? ' · ' . $this->phone : '');
    }
}
