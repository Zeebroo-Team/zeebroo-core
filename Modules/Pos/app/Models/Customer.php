<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class Customer extends Model
{
    protected $table = 'pos_customers';

    protected $fillable = ['business_id', 'name', 'phone', 'email', 'address', 'notes', 'customer_type', 'customer_category_id'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'pos_customer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CustomerCategory::class, 'customer_category_id');
    }

    public function displayLabel(): string
    {
        return $this->name . ($this->phone ? ' · ' . $this->phone : '');
    }
}
