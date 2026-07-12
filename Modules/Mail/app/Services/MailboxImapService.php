<?php

namespace Modules\Mail\Services;

use Illuminate\Support\Facades\Log;
use Modules\Mail\Models\MailFilter;
use Modules\Mail\Models\MailMessage;
use Modules\Mail\Models\Mailbox;
use Throwable;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

class MailboxImapService
{
    /**
     * Only the most recent N messages are ever considered — keeps each sync
     * bounded regardless of how large or old the mailbox is.
     */
    private const SYNC_MESSAGE_LIMIT = 50;

    public function __construct(
        private readonly MailFilterService $filters,
    ) {}

    /**
     * Attempt a connection with the given (not-yet-saved) credentials — used to
     * validate the settings form before a mailbox is saved.
     *
     * @return array{success: bool, error: ?string}
     */
    public function testConnection(array $config): array
    {
        try {
            $client = $this->makeClient($config);
            $client->connect();
            $client->getFolder('INBOX');
            $client->disconnect();

            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $this->friendlyError($e->getMessage(), (string) ($config['host'] ?? ''))];
        }
    }

    /**
     * IMAP servers report auth failures as a raw protocol string ("NO
     * [AUTHENTICATIONFAILED] Invalid credentials"), which isn't actionable on
     * its own — the overwhelmingly common cause is that the provider requires
     * a separate app-specific password instead of the normal account password.
     */
    private function friendlyError(string $rawError, string $host): string
    {
        $isAuthFailure = str_contains(strtoupper($rawError), 'AUTHENTICATIONFAILED')
            || str_contains(strtolower($rawError), 'invalid credentials')
            || str_contains(strtolower($rawError), 'login failed');

        if (!$isAuthFailure) {
            return $rawError;
        }

        $host = strtolower($host);
        $hint = match (true) {
            str_contains($host, 'gmail') || str_contains($host, 'googlemail') =>
                'Gmail rejects your normal password for IMAP — generate an App Password at myaccount.google.com/apppasswords (requires 2-Step Verification to be turned on) and use that instead.',
            str_contains($host, 'yahoo') =>
                'Yahoo requires an App Password for IMAP — generate one at account.yahoo.com under Account Security → Generate app password.',
            str_contains($host, 'outlook') || str_contains($host, 'office365') || str_contains($host, 'hotmail') =>
                'Microsoft accounts with 2-step verification need an App Password — generate one at account.microsoft.com/security instead of using your normal password.',
            str_contains($host, 'icloud') || str_contains($host, 'me.com') =>
                'iCloud requires an app-specific password — generate one at appleid.apple.com under Sign-In and Security → App-Specific Passwords.',
            default =>
                'Double-check the username and password, and confirm IMAP access is enabled for this mailbox. If your provider supports 2-factor authentication, it may require a separate app-specific password instead of your normal one.',
        };

        return $rawError . ' — ' . $hint;
    }

    /**
     * Pull the most recent messages into mail_messages — only the ones not
     * already stored (UID newer than the mailbox's last known UID) are saved.
     *
     * @return array{success: bool, fetched: int, error: ?string}
     */
    public function sync(Mailbox $mailbox): array
    {
        try {
            $client = $this->makeClient([
                'host'          => $mailbox->imap_host,
                'port'          => $mailbox->imap_port,
                'encryption'    => $mailbox->imap_encryption === 'none' ? false : $mailbox->imap_encryption,
                'validate_cert' => true,
                'username'      => $mailbox->imap_username,
                'password'      => $mailbox->getDecryptedPassword(),
                'protocol'      => 'imap',
            ]);
            $client->connect();

            $folder = $client->getFolder('INBOX');
            if (!$folder) {
                throw new \RuntimeException('INBOX folder not found.');
            }

            $messages = $folder->query()->whereAll()->fetchOrderDesc()->limit(self::SYNC_MESSAGE_LIMIT)->get();

            $fetched = 0;
            $maxUid  = $mailbox->last_uid ?? 0;

            foreach ($messages as $message) {
                /** @var Message $message */
                $uid = (int) $message->getUid();

                if ($uid <= ($mailbox->last_uid ?? 0)) {
                    continue;
                }

                if ($this->storeMessage($mailbox, $message, $uid)) {
                    $fetched++;
                }
                $maxUid = max($maxUid, $uid);
            }

            $client->disconnect();

            $mailbox->update([
                'last_uid'        => $maxUid,
                'last_synced_at'  => now(),
                'last_sync_error' => null,
            ]);

            return ['success' => true, 'fetched' => $fetched, 'error' => null];
        } catch (Throwable $e) {
            $friendlyError = $this->friendlyError($e->getMessage(), $mailbox->imap_host);
            $mailbox->update(['last_sync_error' => $friendlyError]);
            Log::error('Mailbox sync failed: ' . $e->getMessage(), ['mailbox_id' => $mailbox->id]);

            return ['success' => false, 'fetched' => 0, 'error' => $friendlyError];
        }
    }

    /**
     * @return bool whether the message was actually stored (false if a filter deleted it)
     */
    private function storeMessage(Mailbox $mailbox, Message $message, int $uid): bool
    {
        /** @var ?Address $from */
        $from = $message->getFrom()->first();
        $subject = (string) $message->getSubject();

        $action = $mailbox->business
            ? $this->filters->resolveAction($mailbox->business, $from?->mail, $from?->personal, $subject)
            : null;

        if ($action === MailFilter::ACTION_DELETE) {
            return false;
        }

        $toAddresses = collect($message->getTo()->all())
            ->map(fn (Address $address) => (string) $address)
            ->implode(', ');

        $occurredAt = null;
        try {
            $occurredAt = $message->getDate()->toDate();
        } catch (Throwable) {
            // Some senders omit or malform the Date header — leave it null rather than fail the sync.
        }

        MailMessage::updateOrCreate(
            ['mailbox_id' => $mailbox->id, 'uid' => $uid],
            [
                'business_id'  => $mailbox->business_id,
                'direction'    => MailMessage::DIRECTION_INBOUND,
                'message_id'   => (string) $message->getMessageId(),
                'from_address' => $from?->mail,
                'from_name'    => $from?->personal,
                'to_address'   => $toAddresses ?: null,
                'subject'      => $subject,
                'body_text'    => $message->getTextBody(),
                'body_html'    => $message->getHTMLBody(),
                'is_read'      => $action === MailFilter::ACTION_MARK_READ,
                'occurred_at'  => $occurredAt,
            ]
        );

        return true;
    }

    private function makeClient(array $config): Client
    {
        return (new ClientManager())->make($config);
    }
}
