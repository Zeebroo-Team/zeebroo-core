<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;
use Modules\FileManager\Models\FileManagerFile;
use Modules\Product\Models\ProductStockLayer;

class Product extends Model
{
    protected $fillable = [
        'business_id',
        'file_manager_file_id',
        'product_unit_id',
        'name',
        'sku',
        'description',
        'unit',
        'unit_price',
        'stock_quantity',
        'is_active',
        'is_bundle',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'stock_quantity' => 'decimal:3',
            'is_active' => 'boolean',
            'is_bundle' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductCategory::class,
            'product_product_category',
            'product_id',
            'product_category_id',
        )->withTimestamps()
            ->orderBy('product_categories.parent_id')
            ->orderBy('product_categories.sort_order')
            ->orderBy('product_categories.name');
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductBrand::class,
            'product_product_brand',
            'product_id',
            'product_brand_id',
        )->withTimestamps()->orderBy('product_brands.name');
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(FileManagerFile::class, 'file_manager_file_id');
    }

    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function bundleItems(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_product_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function stockLayers(): HasMany
    {
        return $this->hasMany(ProductStockLayer::class)->orderByDesc('received_at')->orderByDesc('id');
    }

    public function imageUrl(): ?string
    {
        return $this->imageFile?->publicUrl();
    }
}
