<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Support\BrandCompanyCategoryCatalog;

class BusinessCategory extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function optionsForSelect(): array
    {
        if (! Schema::hasTable('business_categories')) {
            return BrandCompanyCategoryCatalog::defaultOptions();
        }

        $rows = static::query()->active()->ordered()->get(['slug', 'name']);
        if ($rows->isEmpty()) {
            return BrandCompanyCategoryCatalog::defaultOptions();
        }

        return $rows->map(static fn (self $row): array => [
            'value' => $row->slug,
            'label' => $row->name,
        ])->all();
    }

    /**
     * @return array<string, string> slug => name
     */
    public static function labelsBySlug(): array
    {
        $map = [];
        foreach (self::optionsForSelect() as $row) {
            $map[$row['value']] = $row['label'];
        }

        return $map;
    }

    public static function labelForSlug(string $slug): ?string
    {
        return self::labelsBySlug()[$slug] ?? null;
    }
}
