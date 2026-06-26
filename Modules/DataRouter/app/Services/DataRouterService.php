<?php

declare(strict_types=1);

namespace Modules\DataRouter\Services;

use Modules\Business\Models\Business;
use Modules\DataRouter\Exceptions\VaultUnavailableException;
use Modules\DataRouter\Models\BusinessDataVaultConfig;

/**
 * Central routing hub. Each method returns null to signal "use local DB",
 * or returns vault data. Throws VaultUnavailableException when the vault is
 * configured but unreachable — callers must NOT fall back to local DB.
 */
final class DataRouterService
{
    public const MODULE_SALES     = 'sales';
    public const MODULE_PAYROLL   = 'payroll';
    public const MODULE_EMPLOYEES = 'employees';

    public function __construct(
        private readonly DataRouterSettingsService $settings,
        private readonly DataVaultClient $client,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null  null = use local DB
     *
     * @throws VaultUnavailableException
     */
    public function list(
        Business $business,
        string $module,
        string $vaultPath,
        array $params = []
    ): ?array {
        $config = $this->resolveConfig($business, $module);
        if ($config === null) {
            return null;
        }

        return $this->client->get($config->vault_url, $config->shared_secret, $vaultPath, $params, $module);
    }

    /**
     * @return array<string, mixed>|null  null = use local DB
     *
     * @throws VaultUnavailableException
     */
    public function find(
        Business $business,
        string $module,
        string $vaultPath
    ): ?array {
        $config = $this->resolveConfig($business, $module);
        if ($config === null) {
            return null;
        }

        return $this->client->get($config->vault_url, $config->shared_secret, $vaultPath, [], $module);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null  null = use local DB
     *
     * @throws VaultUnavailableException
     */
    public function create(
        Business $business,
        string $module,
        string $vaultPath,
        array $payload
    ): ?array {
        $config = $this->resolveConfig($business, $module);
        if ($config === null) {
            return null;
        }

        return $this->client->post($config->vault_url, $config->shared_secret, $vaultPath, $payload, $module);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null  null = use local DB
     *
     * @throws VaultUnavailableException
     */
    public function update(
        Business $business,
        string $module,
        string $vaultPath,
        array $payload
    ): ?array {
        $config = $this->resolveConfig($business, $module);
        if ($config === null) {
            return null;
        }

        return $this->client->patch($config->vault_url, $config->shared_secret, $vaultPath, $payload, $module);
    }

    /**
     * Returns false when vault routing is not active (caller uses local DB).
     * Returns true when vault deletion succeeded.
     *
     * @throws VaultUnavailableException
     */
    public function delete(
        Business $business,
        string $module,
        string $vaultPath
    ): bool {
        $config = $this->resolveConfig($business, $module);
        if ($config === null) {
            return false;
        }

        $this->client->delete($config->vault_url, $config->shared_secret, $vaultPath, $module);

        return true;
    }

    /**
     * Cheap boolean check using the in-memory cached config lookup.
     */
    public function isRoutedToVault(Business $business, string $module): bool
    {
        return $this->resolveConfig($business, $module) !== null;
    }

    private function resolveConfig(Business $business, string $module): ?BusinessDataVaultConfig
    {
        return $this->settings->activeConfigForModule($business, $module);
    }
}
