<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Product;

class Listing extends Model
{
    protected $table = 'ecom_listings';

    protected $fillable = [
        'business_id',
        'product_id',
        'is_published',
        'is_featured',
        'online_price',
        'compare_at_price',
        'title',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_published'     => 'boolean',
            'is_featured'      => 'boolean',
            'online_price'     => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'sort_order'       => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function displayTitle(): string
    {
        return (string) ($this->title ?: $this->product?->name ?: 'Product');
    }

    public function displayDescription(): ?string
    {
        return $this->description ?: $this->product?->description;
    }

    /** Effective selling price on the website. */
    public function price(): float
    {
        return $this->online_price !== null
            ? (float) $this->online_price
            : (float) ($this->product?->unit_price ?? 0);
    }
}
