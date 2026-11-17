<?php

namespace Modules\CRM\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Sales\Services\QuotationPrintService;

/** An approved quotation to the customer: the message the staff wrote, with the PDF attached. */
class QuotationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $emailSubject,
        public string $messageText,
        public string $pdfPath,
        public string $pdfName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail::emails.composed',
            with: ['bodyHtml' => nl2br(e($this->messageText))],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk(QuotationPrintService::disk(), $this->pdfPath)
                ->as($this->pdfName)
                ->withMime('application/pdf'),
        ];
    }
}
