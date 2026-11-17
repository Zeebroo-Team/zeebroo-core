<?php

namespace Modules\CRM\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\CRM\Models\Lead;
use Modules\CRM\Services\QuotationApprovalBrief;
use Modules\CRM\Services\QuotationApprovalMailer;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationApproval;
use Modules\Sales\Services\QuotationPrintService;

/**
 * A submitted quotation to a head (QuotationApprovalMailer): everything needed to decide from the
 * inbox (QuotationApprovalBrief), the PDF, and the link to approve or reject it.
 */
class QuotationApprovalRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{id: int, name: string, email: string, role: string}  $approver
     * @param  list<array{id: int, name: string, email: string, role: string}>  $approvers  everyone emailed for this submission
     */
    public function __construct(
        public Quotation $quotation,
        public Lead $lead,
        public array $approver,
        public array $approvers,
        public QuotationApproval $submission,
        public string $reviewUrl,
        public ?string $pdfPath,
    ) {}

    public function envelope(): Envelope
    {
        $q        = $this->quotation;
        $currency = (string) (get_settings('business.currency', '', $q->business) ?: '');
        $lead     = $this->lead->company ? $this->lead->name . ' · ' . $this->lead->company : $this->lead->name;

        return new Envelope(subject: 'Approval required: Quotation ' . $q->quote_number
            . ($q->version > 1 ? ' (v' . (int) $q->version . ')' : '')
            . ' — ' . $lead . ' — ' . trim(number_format((float) $q->total, 2) . ' ' . $currency)
            . ($q->business?->name ? ' | ' . $q->business->name : ''));
    }

    public function content(): Content
    {
        $brief = app(QuotationApprovalBrief::class)->build(
            $this->quotation, $this->lead, $this->approver['id'], $this->submission, array_column($this->approvers, 'id'),
        );

        return new Content(view: 'crm::emails.quotation-approval-request', with: $brief + [
            'approver'  => $this->approver,
            'others'    => array_values(array_filter($this->approvers, fn ($a) => $a['id'] !== $this->approver['id'])),
            'reviewUrl' => $this->reviewUrl,
            'hasPdf'    => $this->pdfPath !== null,
            'linkDays'  => QuotationApprovalMailer::LINK_DAYS,
        ]);
    }

    public function attachments(): array
    {
        return $this->pdfPath === null ? [] : [
            Attachment::fromStorageDisk(QuotationPrintService::disk(), $this->pdfPath)
                ->as($this->quotation->quote_number . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
