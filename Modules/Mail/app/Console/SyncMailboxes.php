<?php

namespace Modules\Mail\Console;

use Illuminate\Console\Command;
use Modules\Mail\Jobs\SyncMailboxJob;
use Modules\Mail\Models\Mailbox;

class SyncMailboxes extends Command
{
    protected $signature = 'mail:sync-mailboxes';

    protected $description = 'Queue an IMAP sync job for every active business mailbox';

    public function handle(): int
    {
        $mailboxes = Mailbox::where('is_active', true)->with('business')->get()
            ->filter(fn (Mailbox $mailbox) => $mailbox->business?->hasFeature('mail') ?? true);

        if ($mailboxes->isEmpty()) {
            $this->line('No active mailboxes to sync.');

            return self::SUCCESS;
        }

        // Dispatched as jobs (not run inline) so a slow/unresponsive IMAP server
        // on one mailbox can't block — or blow past the scheduler's own runtime for — the rest.
        foreach ($mailboxes as $mailbox) {
            SyncMailboxJob::dispatch($mailbox->id);
            $this->line("Queued sync for {$mailbox->email_address}");
        }

        return self::SUCCESS;
    }
}
