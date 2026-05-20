<?php

namespace Modules\FileManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Business;

class FileManagerFolder extends Model
{
    protected $fillable = [
        'business_id',
        'parent_id',
        'name',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function files(): HasMany
    {
        return $this->hasMany(FileManagerFile::class, 'folder_id')->orderByDesc('created_at');
    }
}
