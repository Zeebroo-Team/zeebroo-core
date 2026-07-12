<?php

namespace Modules\Mail\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ResendDomainService
{
    /**
     * Find an existing domain by name, or register a new one — returns Resend's
     * domain object, including the DNS records Resend requires for verification.
     *
     * @return array{success: bool, domain: ?array, error: ?string}
     */
    public function createOrGetDomain(string $apiKey, string $domainName): array
    {
        $list = $this->client($apiKey)->get('/domains');
        if ($list->successful()) {
            foreach ((array) $list->json('data') as $existing) {
                if (($existing['name'] ?? null) === $domainName) {
                    return $this->getDomain($apiKey, $existing['id']);
                }
            }
        }

        $response = $this->client($apiKey)->post('/domains', ['name' => $domainName]);
        if (!$response->successful()) {
            return ['success' => false, 'domain' => null, 'error' => $this->extractError($response)];
        }

        return ['success' => true, 'domain' => $response->json(), 'error' => null];
    }

    /**
     * @return array{success: bool, domain: ?array, error: ?string}
     */
    public function getDomain(string $apiKey, string $domainId): array
    {
        $response = $this->client($apiKey)->get("/domains/{$domainId}");
        if (!$response->successful()) {
            return ['success' => false, 'domain' => null, 'error' => $this->extractError($response)];
        }

        return ['success' => true, 'domain' => $response->json(), 'error' => null];
    }

    /**
     * @return array{success: bool, error: ?string}
     */
    public function verifyDomain(string $apiKey, string $domainId): array
    {
        $response = $this->client($apiKey)->post("/domains/{$domainId}/verify");
        if (!$response->successful()) {
            return ['success' => false, 'error' => $this->extractError($response)];
        }

        return ['success' => true, 'error' => null];
    }

    private function client(string $apiKey): PendingRequest
    {
        return Http::withToken($apiKey)->baseUrl('https://api.resend.com')->acceptJson()->timeout(15);
    }

    private function extractError(\Illuminate\Http\Client\Response $response): string
    {
        return $response->json('message') ?? ('Resend API returned HTTP ' . $response->status());
    }
}
