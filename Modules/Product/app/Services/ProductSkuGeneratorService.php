<?php

namespace Modules\Product\Services;

use Illuminate\Support\Str;
use Modules\Business\Models\Business;
use Modules\Product\Models\Product;

class ProductSkuGeneratorService
{
    private const DEFAULT_PREFIX = 'SKU-';

    public function generate(Business $business, ?Product $excluding = null, ?string $productName = null): string
    {
        $prefix = $this->resolvePrefix($productName);

        for ($attempt = 0; $attempt < 25; $attempt++) {
            $candidate = $prefix.str_pad((string) ($this->maxSequenceForPrefix($business, $prefix) + 1 + $attempt), 6, '0', STR_PAD_LEFT);
            if (!$this->skuExists($business, $candidate, $excluding)) {
                return $candidate;
            }
        }

        return self::DEFAULT_PREFIX.strtoupper(Str::random(8));
    }

    private function resolvePrefix(?string $productName): string
    {
        $name = trim((string) $productName);
        if ($name === '') {
            return self::DEFAULT_PREFIX;
        }

        $compact = strtoupper(preg_replace('/[^A-Z0-9]/', '', Str::ascii($name) ?? ''));
        $token = substr($compact, 0, 4);
        if (strlen($token) >= 2) {
            return 'SKU-'.$token.'-';
        }

        return self::DEFAULT_PREFIX;
    }

    private function maxSequenceForPrefix(Business $business, string $prefix): int
    {
        $max = 0;
        $pattern = '/^'.preg_quote($prefix, '/').'(\d+)$/';

        $business->products()
            ->whereNotNull('sku')
            ->where('sku', 'like', $prefix.'%')
            ->pluck('sku')
            ->each(function (string $sku) use ($pattern, &$max): void {
                if (preg_match($pattern, $sku, $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            });

        return $max;
    }

    private function skuExists(Business $business, string $sku, ?Product $excluding): bool
    {
        $query = $business->products()->where('sku', $sku);

        if ($excluding instanceof Product) {
            $query->whereKeyNot($excluding->id);
        }

        return $query->exists();
    }
}
