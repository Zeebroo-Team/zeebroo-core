<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Product;

class StockAuditLine extends Model
{
    protected $fillable = [
        'stock_audit_id', 'product_id', 'product_name', 'sku', 'unit',
        'expected_qty', 'counted_qty', 'notes', 'sort_order',
    ];

    protected $casts = [
        'expected_qty' => 'decimal:3',
        'counted_qty'  => 'decimal:3',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(StockAudit::class, 'stock_audit_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variance(): float
    {
        if ($this->counted_qty === null) {
            return 0.0;
        }

        return round((float) $this->counted_qty - (float) $this->expected_qty, 3);
    }

    public function varianceLabel(): string
    {
        $v = $this->variance();
        if ($v === 0.0) {
            return '0';
        }
        $prefix = $v > 0 ? '+' : '';

        return $prefix . rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }

    public function varianceClass(): string
    {
        $v = $this->variance();
        if ($v > 0) return 'surplus';
        if ($v < 0) return 'deficit';
        return 'ok';
    }
}
