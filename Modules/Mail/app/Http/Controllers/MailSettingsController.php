<?php

namespace Modules\Mail\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Mail\Http\Controllers\Concerns\ResolvesMailBusiness;
use Modules\Mail\Services\BusinessMailConfig;
use Modules\Mail\Services\BusinessMailerService;
use Modules\Mail\Services\DomainAutoConfigService;
use Modules\Mail\Services\MailboxService;

class MailSettingsController extends Controller
{
    use ResolvesMailBusiness;

    public function __construct(
        private readonly BusinessMailConfig $config,
        private readonly BusinessMailerService $mailer,
        private readonly MailboxService $mailboxes,
        private readonly DomainAutoConfigService $domainAutoConfig,
    ) {}

    public function edit(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('mail::settings', [
            'business'         => $business,
            'settings'         => $this->config->get($business),
            'providers'        => BusinessMailConfig::providers(),
            'hasResendKey'     => $this->config->hasSecret($business, 'mail.resend_api_key'),
            'hasSmtpPassword'  => $this->config->hasSecret($business, 'mail.smtp_password'),
            'hasCloudflareToken' => $this->config->hasSecret($business, 'mail.cloudflare_api_token'),
            'mailbox'          => $this->mailboxes->forBusiness($business),
        ]);
    }

    public function autoConfigureDomain(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'domain_name'          => ['required', 'string', 'max:190'],
            'cloudflare_api_token' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->domainAutoConfig->configure($business, $data['domain_name'], $data['cloudflare_api_token'] ?? null);

        return redirect()->route('mail.settings.edit')->with(
            $result['success'] ? 'status' : 'error',
            $result['success']
                ? "Domain configured — {$result['created']} DNS record(s) added to Cloudflare. Verification can take a few minutes; check status below."
                : 'Could not auto-configure: ' . $result['error']
        );
    }

    public function checkDomainStatus(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $result = $this->domainAutoConfig->checkStatus($business);

        return redirect()->route('mail.settings.edit')->with(
            $result['success'] ? 'status' : 'error',
            $result['success'] ? 'Domain status: ' . $result['status'] . '.' : $result['error']
        );
    }

    public function connectMailbox(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'email_address'   => ['required', 'email', 'max:190'],
            'imap_host'       => ['required', 'string', 'max:190'],
            'imap_port'       => ['nullable', 'integer', 'min:1', 'max:65535'],
            'imap_username'   => ['required', 'string', 'max:190'],
            'imap_password'   => ['nullable', 'string', 'max:255'],
            'imap_encryption' => ['required', 'string', Rule::in(['ssl', 'tls', 'none'])],
        ]);

        $result = $this->mailboxes->connect($business, $data);

        return redirect()->route('mail.settings.edit')->with(
            $result['success'] ? 'status' : 'error',
            $result['success'] ? 'Mailbox connected — it will sync automatically every few minutes.' : 'Could not connect: ' . $result['error']
        );
    }

    public function disconnectMailbox(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->mailboxes->disconnect($business);

        return redirect()->route('mail.settings.edit')->with('status', 'Mailbox disconnected.');
    }

    public function syncMailboxNow(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $result = $this->mailboxes->syncNow($business);

        return redirect()->route('mail.settings.edit')->with(
            $result['success'] ? 'status' : 'error',
            $result['success'] ? 'Sync queued — new messages will appear in a moment.' : $result['error']
        );
    }

    public function update(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'provider'        => ['required', 'string', Rule::in(array_keys(BusinessMailConfig::providers()))],
            'from_address'    => ['nullable', 'email', 'max:190'],
            'from_name'       => ['nullable', 'string', 'max:150'],
            'resend_api_key'  => ['nullable', 'string', 'max:255'],
            'smtp_host'       => ['nullable', 'string', 'max:190'],
            'smtp_port'       => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username'   => ['nullable', 'string', 'max:190'],
            'smtp_password'   => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', Rule::in(['tls', 'ssl', 'none'])],
            'letterhead_enabled' => ['nullable', 'boolean'],
        ]);

        $this->config->save($business, $data);

        return redirect()->route('mail.settings.edit')->with('status', 'Mail settings saved.');
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:190'],
        ]);

        $result = $this->mailer->sendTest($business, $data['test_email']);

        return redirect()->route('mail.settings.edit')->with(
            $result['success'] ? 'status' : 'error',
            $result['success'] ? 'Test email sent to ' . $data['test_email'] . '.' : $result['error']
        );
    }

}
