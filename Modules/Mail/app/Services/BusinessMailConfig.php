<?php

namespace Modules\Mail\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Modules\Business\Models\Business;

class BusinessMailConfig
{
    const PROVIDER_PLATFORM = 'platform';
    const PROVIDER_RESEND   = 'resend';
    const PROVIDER_SMTP     = 'smtp';

    /**
     * Sensitive keys are encrypted at rest — the generic Settings table stores
     * plain values, so encryption/decryption happens here, not there.
     */
    private const SECRET_KEYS = ['mail.resend_api_key', 'mail.smtp_password'];

    public static function providers(): array
    {
        return [
            self::PROVIDER_PLATFORM => 'Use platform default',
            self::PROVIDER_RESEND   => 'Resend',
            self::PROVIDER_SMTP     => 'Custom SMTP',
        ];
    }

    /**
     * Decoded, decrypted settings for use when actually sending mail.
     *
     * @return array{provider:string,from_address:?string,from_name:?string,resend_api_key:?string,smtp_host:?string,smtp_port:?int,smtp_username:?string,smtp_password:?string,smtp_encryption:?string,domain_name:?string,resend_domain_id:?string,resend_domain_status:?string,cloudflare_api_token:?string,cloudflare_zone_id:?string}
     */
    public function get(Business $business): array
    {
        return [
            'provider'             => $business->getSetting('mail.provider', self::PROVIDER_PLATFORM),
            'from_address'         => $business->getSetting('mail.from_address'),
            'from_name'            => $business->getSetting('mail.from_name'),
            'resend_api_key'       => $this->decrypt($business->getSetting('mail.resend_api_key')),
            'smtp_host'            => $business->getSetting('mail.smtp_host'),
            'smtp_port'            => $business->getSetting('mail.smtp_port') !== null ? (int) $business->getSetting('mail.smtp_port') : null,
            'smtp_username'        => $business->getSetting('mail.smtp_username'),
            'smtp_password'        => $this->decrypt($business->getSetting('mail.smtp_password')),
            'smtp_encryption'      => $business->getSetting('mail.smtp_encryption'),
            'domain_name'          => $business->getSetting('mail.domain_name'),
            'resend_domain_id'     => $business->getSetting('mail.resend_domain_id'),
            'resend_domain_status' => $business->getSetting('mail.resend_domain_status'),
            'cloudflare_api_token' => $this->decrypt($business->getSetting('mail.cloudflare_api_token')),
            'cloudflare_zone_id'   => $business->getSetting('mail.cloudflare_zone_id'),
        ];
    }

    /**
     * Whether a secret is already saved — used by the settings form to show a
     * "•••• saved" placeholder instead of ever echoing the real value back.
     */
    public function hasSecret(Business $business, string $key): bool
    {
        return filled($business->getSetting($key));
    }

    /**
     * Persist settings from the edit form. Blank secret fields mean "keep the
     * existing value" — they never overwrite a saved key with an empty one.
     */
    public function save(Business $business, array $data): void
    {
        $business->setSetting('mail.provider', $data['provider'] ?? self::PROVIDER_PLATFORM);
        $business->setSetting('mail.from_address', (string) ($data['from_address'] ?? ''));
        $business->setSetting('mail.from_name', (string) ($data['from_name'] ?? ''));
        $business->setSetting('mail.smtp_host', (string) ($data['smtp_host'] ?? ''));
        $business->setSetting('mail.smtp_port', (int) ($data['smtp_port'] ?? 587));
        $business->setSetting('mail.smtp_username', (string) ($data['smtp_username'] ?? ''));
        $business->setSetting('mail.smtp_encryption', (string) ($data['smtp_encryption'] ?? 'tls'));

        if (filled($data['resend_api_key'] ?? '')) {
            $business->setSetting('mail.resend_api_key', Crypt::encryptString($data['resend_api_key']));
        }

        if (filled($data['smtp_password'] ?? '')) {
            $business->setSetting('mail.smtp_password', Crypt::encryptString($data['smtp_password']));
        }
    }

    /**
     * Record the result of a Resend domain registration/auto-configure run.
     */
    public function saveDomainConfig(Business $business, string $domainName, string $resendDomainId, string $status, string $cloudflareZoneId): void
    {
        $business->setSetting('mail.domain_name', $domainName);
        $business->setSetting('mail.resend_domain_id', $resendDomainId);
        $business->setSetting('mail.resend_domain_status', $status);
        $business->setSetting('mail.cloudflare_zone_id', $cloudflareZoneId);
    }

    public function saveCloudflareToken(Business $business, string $token): void
    {
        $business->setSetting('mail.cloudflare_api_token', Crypt::encryptString($token));
    }

    public function updateDomainStatus(Business $business, string $status): void
    {
        $business->setSetting('mail.resend_domain_status', $status);
    }

    private function decrypt(?string $value): ?string
    {
        if (!filled($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}
