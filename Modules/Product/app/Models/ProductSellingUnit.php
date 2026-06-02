<?php
namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class ProductSellingUnit extends Model
{
    protected $table = 'product_selling_units';

    protected $fillable = ['product_id', 'business_id', 'label', 'conversion_factor', 'selling_price', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:6',
            'selling_price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function business(): BelongsTo { return $this->belongsTo(Business::class); }

    public function baseUnitPrice(?float $baseUnitSellPrice): float
    {
        if ($this->selling_price !== null) {
            $factor = (float) $this->conversion_factor;
            return $factor > 0 ? round((float) $this->selling_price / $factor, 6) : 0.0;
        }
        return $baseUnitSellPrice ?? 0.0;
    }

    public function displaySellingPrice(?float $baseUnitSellPrice): float
    {
        if ($this->selling_price !== null) {
            return round((float) $this->selling_price, 2);
        }
        return round(($baseUnitSellPrice ?? 0.0) * (float) $this->conversion_factor, 2);
    }
}
