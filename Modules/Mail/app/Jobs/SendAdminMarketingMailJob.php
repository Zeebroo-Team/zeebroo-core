<?php

namespace Modules\Mail\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Mail\Models\AdminEmailCampaignRecipient;
use Modules\Mail\Services\AdminEmailMarketingService;
use Throwable;

class SendAdminMarketingMailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;

    public int $tries = 1;

    public function __construct(public readonly int $recipientId) {}

    public function handle(AdminEmailMarketingService $service): void
    {
        // Claim the row first so a duplicate job (retry, double dispatch) can't send twice.
        $claimed = AdminEmailCampaignRecipient::whereKey($this->recipientId)
            ->where('status', AdminEmailCampaignRecipient::STATUS_PENDING)
            ->update(['status' => AdminEmailCampaignRecipient::STATUS_SENDING]);

        if ($claimed === 0) {
            return;
        }

        $recipient = AdminEmailCampaignRecipient::with(['campaign', 'user'])->find($this->recipientId);

        if ($recipient === null || $recipient->campaign === null) {
            return;
        }

        // The user may have unsubscribed between queueing and sending.
        if ($recipient->user?->marketing_opt_out_at !== null) {
            $service->recordResult($recipient, false, 'Recipient unsubscribed before delivery.');

            return;
        }

        try {
            $mail = $service->buildMail(
                $recipient->campaign->subject,
                $recipient->campaign->body,
                $recipient->user,
                $recipient->email,
                $recipient->name,
            );

            Mail::to($recipient->email, $recipient->name)->send($mail);

            $service->recordResult($recipient, true);
        } catch (Throwable $e) {
            Log::warning('Admin marketing email failed', ['recipient_id' => $recipient->id, 'error' => $e->getMessage()]);

            $service->recordResult($recipient, false, $e->getMessage());
        }
    }

    public function failed(Throwable $e): void
    {
        // Timeout / worker crash after the claim: don't leave the row stuck in "sending".
        $recipient = AdminEmailCampaignRecipient::whereKey($this->recipientId)->where('status', AdminEmailCampaignRecipient::STATUS_SENDING)->first();

        if ($recipient !== null) {
            app(AdminEmailMarketingService::class)->recordResult($recipient, false, $e->getMessage());
        }
    }
}
