<?php

namespace Modules\Mail\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class CloudflareDnsService
{
    /**
     * Look up the zone ID for a domain the token has access to.
     */
    public function findZoneId(string $token, string $domainName): ?string
    {
        $response = $this->client($token)->get('/zones', ['name' => $domainName]);
        if (!$response->successful()) {
            return null;
        }

        $zones = (array) $response->json('result');

        return $zones[0]['id'] ?? null;
    }

    /**
     * Create the DNS record, or update it in place if one with the same
     * type+name already exists — safe to call repeatedly without creating
     * duplicate/conflicting records (which is invalid for SPF/DKIM TXT records).
     *
     * @param  array{type:string,name:string,content:string,priority?:int}  $record
     * @return array{success: bool, error: ?string}
     */
    public function upsertRecord(string $token, string $zoneId, array $record): array
    {
        $existing = $this->client($token)->get("/zones/{$zoneId}/dns_records", [
            'type' => $record['type'],
            'name' => $record['name'],
        ]);
        $existingRecords = $existing->successful() ? (array) $existing->json('result') : [];

        $payload = array_filter([
            'type'     => $record['type'],
            'name'     => $record['name'],
            'content'  => $record['content'],
            'ttl'      => 1, // Cloudflare's "Automatic" TTL
            'priority' => $record['priority'] ?? null,
        ], fn ($value) => $value !== null);

        $response = !empty($existingRecords)
            ? $this->client($token)->put("/zones/{$zoneId}/dns_records/{$existingRecords[0]['id']}", $payload)
            : $this->client($token)->post("/zones/{$zoneId}/dns_records", $payload);

        if (!$response->successful()) {
            return ['success' => false, 'error' => $this->extractError($response)];
        }

        return ['success' => true, 'error' => null];
    }

    private function client(string $token): PendingRequest
    {
        return Http::withToken($token)->baseUrl('https://api.cloudflare.com/client/v4')->acceptJson()->timeout(15);
    }

    private function extractError(Response $response): string
    {
        $errors = (array) $response->json('errors');

        return $errors[0]['message'] ?? ('Cloudflare API returned HTTP ' . $response->status());
    }
}
