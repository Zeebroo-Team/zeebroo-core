<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class CustomerCategory extends Model
{
    protected $table = 'pos_customer_categories';

    protected $fillable = ['business_id', 'name', 'description'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'customer_category_id');
    }
}
