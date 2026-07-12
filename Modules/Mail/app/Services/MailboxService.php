<?php

namespace Modules\Mail\Services;

use Modules\Business\Models\Business;
use Modules\Mail\Jobs\SyncMailboxJob;
use Modules\Mail\Models\Mailbox;

class MailboxService
{
    public function __construct(
        private readonly MailboxImapService $imap,
    ) {}

    public function forBusiness(Business $business): ?Mailbox
    {
        return Mailbox::where('business_id', $business->id)->first();
    }

    /**
     * Test the given credentials, and only save them if the connection actually
     * works — a mailbox is never stored in a state we already know is broken.
     * Leaving imap_password blank keeps whatever password is already saved.
     *
     * @return array{success: bool, error: ?string}
     */
    public function connect(Business $business, array $data): array
    {
        $existing = $this->forBusiness($business);
        $password = filled($data['imap_password'] ?? '') ? $data['imap_password'] : $existing?->getDecryptedPassword();

        if (!filled($password)) {
            return ['success' => false, 'error' => 'A password is required to connect this mailbox.'];
        }

        $encryption = $data['imap_encryption'] ?? 'ssl';
        $test = $this->imap->testConnection([
            'host'          => $data['imap_host'],
            'port'          => (int) ($data['imap_port'] ?? 993),
            'encryption'    => $encryption === 'none' ? false : $encryption,
            'validate_cert' => true,
            'username'      => $data['imap_username'],
            'password'      => $password,
            'protocol'      => 'imap',
        ]);

        if (!$test['success']) {
            return $test;
        }

        Mailbox::updateOrCreate(
            ['business_id' => $business->id],
            [
                'email_address'   => $data['email_address'],
                'imap_host'       => $data['imap_host'],
                'imap_port'       => (int) ($data['imap_port'] ?? 993),
                'imap_username'   => $data['imap_username'],
                'imap_password'   => $password,
                'imap_encryption' => $encryption,
                'is_active'       => true,
                'last_sync_error' => null,
            ]
        );

        return ['success' => true, 'error' => null];
    }

    public function disconnect(Business $business): void
    {
        Mailbox::where('business_id', $business->id)->delete();
    }

    /**
     * Queue the sync rather than running it inline — a slow/unresponsive IMAP
     * server can take well past a web request's execution time limit.
     *
     * @return array{success: bool, queued: bool, error: ?string}
     */
    public function syncNow(Business $business): array
    {
        $mailbox = $this->forBusiness($business);
        if (!$mailbox) {
            return ['success' => false, 'queued' => false, 'error' => 'No mailbox connected.'];
        }

        SyncMailboxJob::dispatch($mailbox->id);

        return ['success' => true, 'queued' => true, 'error' => null];
    }
}
