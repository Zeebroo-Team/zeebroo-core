<?php

namespace Modules\Mail\Services;

use Illuminate\Support\Collection;
use Modules\Business\Models\Business;
use Modules\Mail\Models\ScheduledMail;

class ScheduledMailService
{
    public function listForBusiness(Business $business): Collection
    {
        return ScheduledMail::where('business_id', $business->id)
            ->orderByDesc('scheduled_at')
            ->get();
    }

    public function schedule(Business $business, array $data): ScheduledMail
    {
        return ScheduledMail::create([
            'business_id'  => $business->id,
            'to_address'   => $data['to'],
            'subject'      => $data['subject'],
            'body'         => $data['body'],
            'scheduled_at' => $data['scheduled_at'],
            'status'       => ScheduledMail::STATUS_PENDING,
        ]);
    }

    public function cancel(ScheduledMail $scheduled): bool
    {
        if ($scheduled->status !== ScheduledMail::STATUS_PENDING) {
            return false;
        }

        $scheduled->update(['status' => ScheduledMail::STATUS_CANCELLED]);

        return true;
    }

    public function scheduledForBusiness(Business $business, ScheduledMail $scheduled): ?ScheduledMail
    {
        return $scheduled->business_id === $business->id ? $scheduled : null;
    }

    /**
     * @return Collection<int, ScheduledMail>
     */
    public function due(): Collection
    {
        return ScheduledMail::where('status', ScheduledMail::STATUS_PENDING)
            ->where('scheduled_at', '<=', now())
            ->get();
    }
}
