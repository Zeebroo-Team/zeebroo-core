<?php

declare(strict_types=1);

namespace Modules\DataRouter\Services;

use Modules\Business\Models\Business;
use Modules\DataRouter\Models\BusinessDataVaultConfig;

final class DataRouterSettingsService
{
    /** Per-request in-memory cache: "{businessId}.{module}" => config|null */
    private array $cache = [];

    public function getConfig(Business $business): ?BusinessDataVaultConfig
    {
        return BusinessDataVaultConfig::query()
            ->where('business_id', $business->id)
            ->first();
    }

    /**
     * Upsert vault configuration for a business.
     * If shared_secret is null/empty and a record already exists, the
     * existing encrypted secret is preserved unchanged.
     *
     * @param  array{
     *     vault_url: string,
     *     shared_secret?: string|null,
     *     is_enabled: bool,
     *     enabled_modules: list<string>,
     *     label?: string|null,
     * }  $data
     */
    public function upsertConfig(Business $business, array $data): BusinessDataVaultConfig
    {
        $existing = $this->getConfig($business);

        if (empty($data['shared_secret']) && $existing !== null) {
            unset($data['shared_secret']);
        }

        $this->clearCache($business);

        return BusinessDataVaultConfig::updateOrCreate(
            ['business_id' => $business->id],
            array_merge(['business_id' => $business->id], $data)
        );
    }

    public function deleteConfig(Business $business): void
    {
        BusinessDataVaultConfig::query()
            ->where('business_id', $business->id)
            ->delete();

        $this->clearCache($business);
    }

    /**
     * Fast path called on every routed request. Returns the config only if
     * vault routing is active for this business and this module slug.
     * Results are cached in-memory for the lifetime of the request.
     */
    public function activeConfigForModule(Business $business, string $module): ?BusinessDataVaultConfig
    {
        $cacheKey = "{$business->id}.{$module}";

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $config = $this->getConfig($business);

        $result = ($config !== null && $config->isModuleEnabled($module)) ? $config : null;

        $this->cache[$cacheKey] = $result;

        return $result;
    }

    private function clearCache(Business $business): void
    {
        foreach (array_keys($this->cache) as $key) {
            if (str_starts_with($key, "{$business->id}.")) {
                unset($this->cache[$key]);
            }
        }
    }
}
