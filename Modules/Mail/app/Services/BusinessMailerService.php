<?php

namespace Modules\Mail\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Business\Models\Business;
use Modules\Mail\Mail\BusinessMailTestMail;
use Throwable;

class BusinessMailerService
{
    public function __construct(
        private readonly BusinessMailConfig $config,
        private readonly LetterheadService $letterhead,
    ) {}

    /**
     * Send a Mailable on behalf of a business, using that business's own
     * provider (Resend / custom SMTP) when configured, falling back to the
     * platform's default mailer otherwise. Failures are logged and returned
     * as a friendly, actionable message — never thrown — so a failed
     * automation/notification email never breaks the surrounding request.
     *
     * @return array{success: bool, error: ?string}
     */
    public function send(?Business $business, Mailable $mailable, string $to): array
    {
        $settings = $business ? $this->config->get($business) : null;

        try {
            $mailerName = $this->resolveMailerName($business, $settings);

            if ($settings && filled($settings['from_address'])) {
                $mailable->from($settings['from_address'], $settings['from_name'] ?: null);
            }

            if ($business && ($settings['letterhead_enabled'] ?? false)) {
                $mailable->with('letterheadHtml', $this->letterhead->render($business));
            }

            Mail::mailer($mailerName)->to($to)->send($mailable);

            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            Log::error('Business mail send failed: ' . $e->getMessage(), [
                'business_id' => $business?->id,
            ]);

            return ['success' => false, 'error' => $this->friendlyError($e->getMessage(), $settings)];
        }
    }

    /**
     * @return array{success: bool, error: ?string}
     */
    public function sendTest(Business $business, string $to): array
    {
        return $this->send($business, new BusinessMailTestMail($business), $to);
    }

    /**
     * The two most common failures — an SMTP auth rejection and a Resend
     * "domain not verified" error — are protocol-level messages that aren't
     * actionable on their own. Point at the actual fix instead.
     */
    private function friendlyError(string $rawError, ?array $settings): string
    {
        $lower = strtolower($rawError);

        if (str_contains($rawError, '535') || str_contains($lower, 'authenticate') || str_contains($lower, 'not accepted')) {
            $host = strtolower((string) ($settings['smtp_host'] ?? ''));
            $hint = match (true) {
                str_contains($host, 'gmail') || str_contains($host, 'googlemail') =>
                    'Gmail rejects your normal password for SMTP — generate an App Password at myaccount.google.com/apppasswords (requires 2-Step Verification to be turned on) and use that instead.',
                str_contains($host, 'yahoo') =>
                    'Yahoo requires an App Password for SMTP — generate one at account.yahoo.com under Account Security → Generate app password.',
                str_contains($host, 'outlook') || str_contains($host, 'office365') || str_contains($host, 'hotmail') =>
                    'Microsoft accounts with 2-step verification need an App Password — generate one at account.microsoft.com/security instead of using your normal password.',
                str_contains($host, 'icloud') || str_contains($host, 'me.com') =>
                    'iCloud requires an app-specific password — generate one at appleid.apple.com under Sign-In and Security → App-Specific Passwords.',
                $host !== '' =>
                    'Double-check the SMTP username and password — your provider may require a separate app-specific password instead of your normal one.',
                default => '',
            };

            return $hint !== '' ? $rawError . ' — ' . $hint : $rawError;
        }

        if (str_contains($lower, 'domain is not verified') || str_contains($lower, 'resend.com/domains')) {
            return $rawError . ' — Use the "Domain authentication" section above to auto-configure this domain, then wait for it to verify before sending from that address.';
        }

        return $rawError;
    }

    private function resolveMailerName(?Business $business, ?array $settings): string
    {
        if (!$business || !$settings) {
            return config('mail.default');
        }

        return match ($settings['provider']) {
            BusinessMailConfig::PROVIDER_RESEND => $this->registerResendMailer($business, $settings),
            BusinessMailConfig::PROVIDER_SMTP   => $this->registerSmtpMailer($business, $settings),
            default                             => config('mail.default'),
        };
    }

    private function registerResendMailer(Business $business, array $settings): string
    {
        if (!filled($settings['resend_api_key'])) {
            return config('mail.default');
        }

        $name = 'business_resend_' . $business->id;

        config(["mail.mailers.{$name}" => [
            'transport' => 'resend',
            'key'       => $settings['resend_api_key'],
        ]]);

        return $name;
    }

    private function registerSmtpMailer(Business $business, array $settings): string
    {
        if (!filled($settings['smtp_host'])) {
            return config('mail.default');
        }

        $name = 'business_smtp_' . $business->id;

        config(["mail.mailers.{$name}" => [
            'transport'  => 'smtp',
            'host'       => $settings['smtp_host'],
            'port'       => $settings['smtp_port'] ?? 587,
            'username'   => $settings['smtp_username'],
            'password'   => $settings['smtp_password'],
            'encryption' => $settings['smtp_encryption'] ?: 'tls',
        ]]);

        return $name;
    }
}
