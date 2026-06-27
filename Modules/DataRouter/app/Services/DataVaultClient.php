<?php

declare(strict_types=1);

namespace Modules\DataRouter\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\DataRouter\Exceptions\VaultUnavailableException;

/**
 * Signs and dispatches HTTP requests to a self-hosted Data Vault endpoint.
 *
 * HMAC-SHA256 signature scheme:
 *   payload   = "{unix_timestamp}\n{raw_json_body}"
 *   signature = hash_hmac('sha256', payload, sharedSecret)
 *   Headers:   X-Vault-Timestamp, X-Vault-Signature
 */
final class DataVaultClient
{
    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws VaultUnavailableException
     */
    public function get(
        string $vaultUrl,
        string $sharedSecret,
        string $path,
        array $query = [],
        string $module = 'unknown'
    ): array {
        return $this->send('GET', $vaultUrl, $sharedSecret, $path, [], $query, $module);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws VaultUnavailableException
     */
    public function post(
        string $vaultUrl,
        string $sharedSecret,
        string $path,
        array $body = [],
        string $module = 'unknown'
    ): array {
        return $this->send('POST', $vaultUrl, $sharedSecret, $path, $body, [], $module);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws VaultUnavailableException
     */
    public function patch(
        string $vaultUrl,
        string $sharedSecret,
        string $path,
        array $body = [],
        string $module = 'unknown'
    ): array {
        return $this->send('PATCH', $vaultUrl, $sharedSecret, $path, $body, [], $module);
    }

    /**
     * @throws VaultUnavailableException
     */
    public function delete(
        string $vaultUrl,
        string $sharedSecret,
        string $path,
        string $module = 'unknown'
    ): void {
        $this->send('DELETE', $vaultUrl, $sharedSecret, $path, [], [], $module);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws VaultUnavailableException
     */
    private function send(
        string $method,
        string $vaultUrl,
        string $sharedSecret,
        string $path,
        array $body,
        array $query,
        string $module
    ): array {
        $rawBody    = $body !== [] ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : '';
        $sigHeaders = $this->signingHeaders($sharedSecret, $rawBody);

        $url     = rtrim($vaultUrl, '/') . $path;
        $timeout = (int) config('datarouter.vault.timeout', 15);
        $retries = (int) config('datarouter.vault.retries', 0);

        try {
            $pending = Http::timeout($timeout)
                ->retry($retries > 0 ? $retries : 1, 500, null, false)
                ->acceptJson()
                ->withHeaders($sigHeaders + ['Content-Type' => 'application/json'])
                ->withQueryParameters($query);

            $response = match (strtoupper($method)) {
                'GET'    => $pending->get($url),
                'POST'   => $pending->post($url, $body),
                'PATCH'  => $pending->patch($url, $body),
                'DELETE' => $pending->delete($url),
                default  => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };
        } catch (ConnectionException $e) {
            throw new VaultUnavailableException($module, 'Connection failed: ' . $e->getMessage(), $e);
        }

        if (! $response->successful()) {
            throw new VaultUnavailableException(
                $module,
                "Vault returned HTTP {$response->status()}: " . $response->body()
            );
        }

        return $response->json() ?? [];
    }

    /**
     * @return array{X-Vault-Timestamp: string, X-Vault-Signature: string}
     */
    private function signingHeaders(string $sharedSecret, string $rawBody): array
    {
        $timestamp = (string) time();
        $payload   = $timestamp . "\n" . $rawBody;
        $signature = hash_hmac('sha256', $payload, $sharedSecret);

        return [
            'X-Vault-Timestamp' => $timestamp,
            'X-Vault-Signature' => $signature,
        ];
    }
}
