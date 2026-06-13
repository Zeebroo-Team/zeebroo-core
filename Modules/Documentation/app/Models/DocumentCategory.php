<?php

namespace Modules\Documentation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'color',
        'description',
        'sort_order',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'document_category_id');
    }

    public function publishedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'document_category_id')
            ->where('status', Document::STATUS_PUBLISHED);
    }

    public function iconClass(): string
    {
        $icon = $this->icon ?: 'fa-folder-open';
        return str_starts_with($icon, 'fa') ? "fa {$icon}" : "fa {$icon}";
    }
}
