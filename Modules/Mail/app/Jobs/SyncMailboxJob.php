<?php

namespace Modules\Mail\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Mail\Models\Mailbox;
use Modules\Mail\Services\MailboxImapService;
use Throwable;

class SyncMailboxJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** IMAP connections over a slow/unresponsive server can take a while — give the worker room beyond the default 60s. */
    public int $timeout = 180;

    public int $tries = 1;

    public function __construct(public readonly int $mailboxId) {}

    public function handle(MailboxImapService $imap): void
    {
        $mailbox = Mailbox::find($this->mailboxId);

        if (!$mailbox instanceof Mailbox) {
            return;
        }

        // MailboxImapService::sync() already catches its own failures and
        // records them on the mailbox (last_sync_error) — nothing to rethrow here.
        $imap->sync($mailbox);
    }

    public function failed(Throwable $e): void
    {
        Log::error('SyncMailboxJob failed: ' . $e->getMessage(), ['mailbox_id' => $this->mailboxId]);

        $mailbox = Mailbox::find($this->mailboxId);
        $mailbox?->update(['last_sync_error' => $e->getMessage()]);
    }
}
