<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductStockLayer;

class StockTransferLine extends Model
{
    protected $fillable = [
        'stock_transfer_id', 'product_id', 'product_name', 'sku', 'unit',
        'quantity', 'unit_cost', 'consumed_breakdown', 'destination_layer_id',
    ];

    protected $casts = [
        'quantity'           => 'decimal:3',
        'unit_cost'          => 'decimal:2',
        'consumed_breakdown' => 'array',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function destinationLayer(): BelongsTo
    {
        return $this->belongsTo(ProductStockLayer::class, 'destination_layer_id');
    }
}
