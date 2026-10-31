<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\LeadStage;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationCustomerResponse;

/**
 * The customer's answer to a sent lead quotation, recorded by the staff on the lead. Each one
 * is a quotation_customer_responses row and moves the quotation and the lead:
 *
 *   accept           quotation → accepted      lead → Won
 *   reject           quotation → rejected      lead → Lost (a lost reason is required)
 *   discount_request quotation stays sent      lead → Negotiation; the staff then make a
 *                                              revised version (LeadQuotationService::revise)
 *
 * Who may do it is LeadPolicy::canQuote, checked by the caller.
 */
class QuotationNegotiationService
{
    public function __construct(
        private readonly LeadQuotationService $leadQuotations,
        private readonly LeadService $leads,
    ) {}

    /**
     * @param  array{response: string, requested_discount?: float|string|null, notes?: ?string, lost_reason_id?: int|string|null, lost_reason?: ?string}  $data
     */
    public function record(Lead $lead, Quotation $quotation, array $data, int $userId): QuotationCustomerResponse
    {
        if (! $quotation->isLeadQuotation() || $quotation->status !== Quotation::STATUS_SENT) {
            throw ValidationException::withMessages(['quotation' => $quotation->isSuperseded()
                ? 'This version has been replaced — record the response on the latest version.'
                : 'Only a quotation that was sent to the customer can get a response.']);
        }

        $response = (string) ($data['response'] ?? '');
        if (! array_key_exists($response, QuotationCustomerResponse::RESPONSES)) {
            throw ValidationException::withMessages(['response' => 'Pick accept, reject or discount request.']);
        }

        $requested = $data['requested_discount'] ?? null;
        $requested = $requested === null || $requested === '' ? null : round((float) $requested, 2);
        if ($response === QuotationCustomerResponse::RESPONSE_DISCOUNT_REQUEST && ($requested === null || $requested <= 0 || $requested > 100)) {
            throw ValidationException::withMessages(['requested_discount' => 'Enter the discount the customer asked for (a % between 0 and 100).']);
        }

        $notes = trim((string) ($data['notes'] ?? ''));
        $notes = $notes === '' ? null : mb_substr($notes, 0, 2000);

        return DB::transaction(function () use ($lead, $quotation, $response, $requested, $notes, $data, $userId) {
            $row = QuotationCustomerResponse::create([
                'quotation_id'       => $quotation->id,
                'version'            => (int) $quotation->version,
                'response'           => $response,
                'requested_discount' => $response === QuotationCustomerResponse::RESPONSE_DISCOUNT_REQUEST ? $requested : null,
                'notes'              => $notes,
                'recorded_by'        => $userId,
            ]);

            match ($response) {
                QuotationCustomerResponse::RESPONSE_ACCEPT => $this->accept($lead, $quotation, $userId, $notes),
                QuotationCustomerResponse::RESPONSE_REJECT => $this->reject($lead, $quotation, $userId, $data),
                default => $this->leadQuotations->moveLeadTo($lead, LeadStage::STATUS_NEGOTIATION, $userId,
                    'Customer asked for ' . rtrim(rtrim(number_format($requested, 2, '.', ''), '0'), '.') . '% discount' . ($notes !== null ? ': ' . $notes : '')),
            };

            return $row;
        });
    }

    private function accept(Lead $lead, Quotation $quotation, int $userId, ?string $notes): void
    {
        $quotation->update(['status' => Quotation::STATUS_ACCEPTED]);
        $this->leadQuotations->moveLeadTo($lead, LeadStage::STATUS_WON, $userId, 'Quotation ' . $quotation->quote_number . ' accepted' . ($notes !== null ? ': ' . $notes : ''));
    }

    private function reject(Lead $lead, Quotation $quotation, int $userId, array $data): void
    {
        $quotation->update(['status' => Quotation::STATUS_REJECTED]);

        $lead->loadMissing('stage');
        if ($lead->isOpen()) {
            $this->leads->markLost($lead, $data['lost_reason_id'] ?? null, $data['lost_reason'] ?? null, $userId);
        }
    }
}
