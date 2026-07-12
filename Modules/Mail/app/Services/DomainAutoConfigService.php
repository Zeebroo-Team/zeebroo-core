<?php

namespace Modules\Mail\Services;

use Modules\Business\Models\Business;

class DomainAutoConfigService
{
    public function __construct(
        private readonly BusinessMailConfig $config,
        private readonly ResendDomainService $resend,
        private readonly CloudflareDnsService $cloudflare,
    ) {}

    /**
     * Register (or fetch) the sending domain with Resend, then push every DNS
     * record Resend requires straight into the matching Cloudflare zone — no
     * manual copy-paste. Safe to re-run: existing records are updated in place.
     *
     * @return array{success: bool, created: int, error: ?string}
     */
    public function configure(Business $business, string $domainName, ?string $cloudflareToken): array
    {
        $settings = $this->config->get($business);

        if (!filled($settings['resend_api_key'])) {
            return ['success' => false, 'created' => 0, 'error' => 'Add and save your Resend API key above first.'];
        }

        $token = filled($cloudflareToken) ? $cloudflareToken : $settings['cloudflare_api_token'];
        if (!filled($token)) {
            return ['success' => false, 'created' => 0, 'error' => 'A Cloudflare API token is required.'];
        }

        $domainResult = $this->resend->createOrGetDomain($settings['resend_api_key'], $domainName);
        if (!$domainResult['success']) {
            return ['success' => false, 'created' => 0, 'error' => 'Resend: ' . $domainResult['error']];
        }
        $domain = $domainResult['domain'];

        $zoneId = $this->cloudflare->findZoneId($token, $domainName);
        if (!$zoneId) {
            return ['success' => false, 'created' => 0, 'error' => "Could not find a Cloudflare zone for \"{$domainName}\" — make sure the domain is in your Cloudflare account and the token has Zone / DNS:Edit permission for it."];
        }

        $created = 0;
        $failures = [];

        foreach ((array) ($domain['records'] ?? []) as $record) {
            $subdomain = trim((string) ($record['name'] ?? ''), '.');
            $fullName  = $subdomain === '' ? $domainName : $subdomain . '.' . $domainName;
            $value     = trim((string) ($record['value'] ?? ''), '"');

            $result = $this->cloudflare->upsertRecord($token, $zoneId, array_filter([
                'type'     => $record['type'] ?? 'TXT',
                'name'     => $fullName,
                'content'  => $value,
                'priority' => $record['priority'] ?? null,
            ], fn ($v) => $v !== null));

            if ($result['success']) {
                $created++;
            } else {
                $failures[] = "{$record['type']} {$fullName}: {$result['error']}";
            }
        }

        $this->config->saveCloudflareToken($business, $token);
        $this->config->saveDomainConfig(
            $business,
            $domainName,
            (string) $domain['id'],
            (string) ($domain['status'] ?? 'not_started'),
            $zoneId,
        );

        if (!empty($failures)) {
            return ['success' => false, 'created' => $created, 'error' => 'Some records failed: ' . implode('; ', $failures)];
        }

        return ['success' => true, 'created' => $created, 'error' => null];
    }

    /**
     * Re-poll Resend for the domain's current verification status.
     *
     * @return array{success: bool, status: ?string, error: ?string}
     */
    public function checkStatus(Business $business): array
    {
        $settings = $this->config->get($business);

        if (!filled($settings['resend_domain_id']) || !filled($settings['resend_api_key'])) {
            return ['success' => false, 'status' => null, 'error' => 'No domain configured yet.'];
        }

        $result = $this->resend->getDomain($settings['resend_api_key'], $settings['resend_domain_id']);
        if (!$result['success']) {
            return ['success' => false, 'status' => null, 'error' => $result['error']];
        }

        $status = (string) ($result['domain']['status'] ?? 'unknown');
        $this->config->updateDomainStatus($business, $status);

        return ['success' => true, 'status' => $status, 'error' => null];
    }
}
