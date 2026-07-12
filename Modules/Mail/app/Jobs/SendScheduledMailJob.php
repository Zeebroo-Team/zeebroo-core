<?php

namespace Modules\Mail\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Mail\Mail\ComposedMail;
use Modules\Mail\Models\MailMessage;
use Modules\Mail\Models\ScheduledMail;
use Modules\Mail\Services\BusinessMailerService;
use Throwable;

class SendScheduledMailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;

    public int $tries = 1;

    public function __construct(public readonly int $scheduledMailId) {}

    public function handle(BusinessMailerService $mailer): void
    {
        $scheduled = ScheduledMail::find($this->scheduledMailId);

        if (!$scheduled instanceof ScheduledMail || $scheduled->status !== ScheduledMail::STATUS_PENDING) {
            return;
        }

        // Claim it first so a second worker picking up the same due-list can't double-send.
        $claimed = ScheduledMail::whereKey($scheduled->id)
            ->where('status', ScheduledMail::STATUS_PENDING)
            ->update(['status' => ScheduledMail::STATUS_SENDING]);

        if ($claimed === 0) {
            return;
        }

        $bodyHtml = nl2br(e($scheduled->body));
        $result = $mailer->send($scheduled->business, new ComposedMail($scheduled->subject, $bodyHtml), $scheduled->to_address);

        if ($result['success']) {
            $scheduled->update([
                'status'  => ScheduledMail::STATUS_SENT,
                'sent_at' => now(),
                'error'   => null,
            ]);

            MailMessage::create([
                'business_id'  => $scheduled->business_id,
                'direction'    => MailMessage::DIRECTION_OUTBOUND,
                'from_address' => $scheduled->business?->user?->email,
                'to_address'   => $scheduled->to_address,
                'subject'      => $scheduled->subject,
                'body_text'    => $scheduled->body,
                'body_html'    => $bodyHtml,
                'is_read'      => true,
                'occurred_at'  => now(),
            ]);
        } else {
            $scheduled->update([
                'status' => ScheduledMail::STATUS_FAILED,
                'error'  => $result['error'],
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('SendScheduledMailJob failed: ' . $e->getMessage(), ['scheduled_mail_id' => $this->scheduledMailId]);

        ScheduledMail::where('id', $this->scheduledMailId)
            ->whereIn('status', [ScheduledMail::STATUS_PENDING, ScheduledMail::STATUS_SENDING])
            ->update(['status' => ScheduledMail::STATUS_FAILED, 'error' => $e->getMessage()]);
    }
}
