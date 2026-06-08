<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Business;

class ProductBarcodeSheet extends Model
{
    protected $fillable = [
        'business_id',
        'product_id',
        'name',
        'encode_type',
        'page_size',
        'page_orientation',
        'label_type',
        'labels_per_page',
        'total_quantity',
    ];

    protected function casts(): array
    {
        return [
            'labels_per_page' => 'integer',
            'total_quantity'  => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function totalPages(): int
    {
        if ($this->labels_per_page <= 0) {
            return 1;
        }
        return (int) ceil($this->total_quantity / $this->labels_per_page);
    }

    public function barcodeValue(): string
    {
        $sku = $this->product?->sku;
        if ($sku) {
            return $sku;
        }
        return (string) ($this->product?->id ?? '0');
    }
}
