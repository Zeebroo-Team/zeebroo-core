<?php

namespace Modules\CRM\Services\QuotationDispatch;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Events\QuotationSent;
use Modules\CRM\Jobs\SendQuotationDispatchJob;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadStage;
use Modules\CRM\Services\LeadQuotationService;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationDispatch;
use Modules\Sales\Services\QuotationPrintService;
use Throwable;

/**
 * Sends an approved lead quotation's PDF to the customer over a channel. queue() records a
 * quotation_dispatches row and queues SendQuotationDispatchJob; deliver() (the job) hands it to
 * the channel and, on success, marks the quotation sent and moves the lead to "Quotation Sent".
 * A sent quotation can be sent again (a reminder, another address); an unapproved one cannot.
 */
class QuotationDispatcher
{
    /** @var array<string, QuotationChannel> */
    private array $channels = [];

    /** @param iterable<QuotationChannel> $channels */
    public function __construct(
        private readonly QuotationPrintService $print,
        private readonly LeadQuotationService $leadQuotations,
        iterable $channels = [],
    ) {
        foreach ($channels as $channel) {
            $this->extend($channel);
        }
    }

    public function extend(QuotationChannel $channel): static
    {
        $this->channels[$channel->key()] = $channel;

        return $this;
    }

    /** @return list<array{key: string, label: string}> */
    public function channels(): array
    {
        return array_values(array_map(fn (QuotationChannel $c) => ['key' => $c->key(), 'label' => $c->label()], $this->channels));
    }

    public function channel(string $key): QuotationChannel
    {
        return $this->channels[$key] ?? throw ValidationException::withMessages(['channel' => 'Unknown channel "' . $key . '".']);
    }

    /**
     * @param  array{to: string, cc?: string[], subject?: ?string, message?: ?string}  $payload
     */
    public function queue(Quotation $quotation, string $channelKey, array $payload, ?int $userId): QuotationDispatch
    {
        if (! $quotation->isLeadQuotation() || ! in_array($quotation->status, [Quotation::STATUS_APPROVED, Quotation::STATUS_SENT], true)) {
            throw ValidationException::withMessages(['quotation' => 'This quotation must be approved before it is sent.']);
        }
        $this->channel($channelKey);

        $dispatch = QuotationDispatch::create([
            'quotation_id' => $quotation->id,
            'channel'      => $channelKey,
            'to'           => trim($payload['to']),
            'cc'           => array_values(array_filter(array_map('trim', $payload['cc'] ?? []))) ?: null,
            'subject'      => $payload['subject'] ?? null,
            'message'      => $payload['message'] ?? null,
            'file_path'    => $this->print->pdfPath($quotation),
            'status'       => QuotationDispatch::STATUS_QUEUED,
            'sent_by'      => $userId,
        ]);

        SendQuotationDispatchJob::dispatch($dispatch->id);

        return $dispatch->fresh();
    }

    public function deliver(QuotationDispatch $dispatch): QuotationDispatch
    {
        $quotation = $dispatch->quotation()->with('business')->firstOrFail();

        try {
            $this->channel($dispatch->channel)->send($dispatch, $quotation);
        } catch (Throwable $e) {
            $dispatch->update(['status' => QuotationDispatch::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 2000)]);

            return $dispatch;
        }

        DB::transaction(function () use ($dispatch, $quotation) {
            $dispatch->update(['status' => QuotationDispatch::STATUS_SENT, 'error' => null, 'sent_at' => now()]);
            $firstSend = $quotation->status === Quotation::STATUS_APPROVED;

            $quotation->update([
                'status'   => $quotation->status === Quotation::STATUS_APPROVED ? Quotation::STATUS_SENT : $quotation->status,
                'sent_at'  => $quotation->sent_at ?? now(),
                'sent_via' => $dispatch->channel,
            ]);

            $lead = Lead::find($quotation->lead_id);
            if ($lead !== null) {
                $this->leadQuotations->moveLeadTo($lead, LeadStage::STATUS_QUOTATION_SENT, $dispatch->sent_by);
                // Follow-ups are planned from the first send of each version, not from re-sends
                if ($firstSend) {
                    QuotationSent::dispatch($lead, $quotation, $dispatch->sent_by);
                }
            }
        });

        return $dispatch->fresh();
    }

    /**
     * What the send form starts with: the customer's (or the lead's) email, a subject and a
     * short message the staff can change.
     *
     * @return array{to: ?string, subject: string, message: string}
     */
    public function defaults(Lead $lead, Quotation $quotation): array
    {
        $quotation->loadMissing(['customer', 'business']);
        $business = $quotation->business?->name ?? '';
        $name     = $quotation->customer?->name ?: $lead->name;

        return [
            'to'      => $quotation->customer?->email ?: $lead->email,
            'subject' => 'Quotation ' . $quotation->quote_number . ($business !== '' ? ' from ' . $business : ''),
            'message' => "Dear {$name},\n\nPlease find attached our quotation {$quotation->quote_number}"
                . ($quotation->expiry_date ? ', valid until ' . $quotation->expiry_date->format('M j, Y') : '')
                . ".\n\nDo let us know if you have any questions.\n\nKind regards,\n{$business}",
        ];
    }
}
