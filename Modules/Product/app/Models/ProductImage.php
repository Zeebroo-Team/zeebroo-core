<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\FileManager\Models\FileManagerFile;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'file_manager_file_id',
        'sort_order',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(FileManagerFile::class, 'file_manager_file_id');
    }
}
