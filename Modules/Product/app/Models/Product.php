<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Branch;
use Modules\Business\Models\Business;
use Modules\FileManager\Models\FileManagerFile;
use Modules\Product\Models\ProductSellingUnit;
use Modules\Product\Models\ProductStockLayer;

class Product extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'file_manager_file_id',
        'product_unit_id',
        'name',
        'sku',
        'model_no',
        'size',
        'mfg_date',
        'exp_date',
        'description',
        'tags',
        'unit',
        'unit_price',
        'cost_price',
        'wholesale_price',
        'stock_quantity',
        'is_active',
        'is_bundle',
        'has_warranty',
        'warranty_duration',
        'track_expiry',
        'courier_delivery',
        'delivery_methods',
        'loyalty_redeemable',
        'is_customer_required',
        'is_rental',
        'rental_daily_rate',
        'rental_max_days',
        'rental_late_fee_multiplier',
        'rental_needs_cleaning',
        'is_subscription',
        'subscription_recurring_period',
        'subscription_free_trial',
        'item_wise_tax',
        'item_wise_discount',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'                 => 'decimal:2',
            'cost_price'                 => 'decimal:2',
            'wholesale_price'            => 'decimal:2',
            'stock_quantity'             => 'decimal:3',
            'mfg_date'                   => 'date:Y-m-d',
            'exp_date'                   => 'date:Y-m-d',
            'tags'                       => 'array',
            'is_active'                  => 'boolean',
            'is_bundle'                  => 'boolean',
            'has_warranty'               => 'boolean',
            'track_expiry'               => 'boolean',
            'courier_delivery'           => 'boolean',
            'delivery_methods'           => 'array',
            'loyalty_redeemable'         => 'boolean',
            'is_customer_required'       => 'boolean',
            'is_rental'                  => 'boolean',
            'rental_daily_rate'          => 'decimal:2',
            'rental_max_days'            => 'integer',
            'rental_late_fee_multiplier' => 'decimal:2',
            'rental_needs_cleaning'      => 'boolean',
            'is_subscription'            => 'boolean',
            'subscription_free_trial'    => 'boolean',
            'item_wise_tax'              => 'boolean',
            'item_wise_discount'         => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    public function sellingUnits(): HasMany
    {
        return $this->hasMany(ProductSellingUnit::class)->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        return $this->imageFile?->publicUrl();
    }
}
