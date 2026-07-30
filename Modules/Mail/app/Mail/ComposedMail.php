<?php

namespace Modules\Mail\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ComposedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $emailSubject,
        public string  $bodyHtml,
        public ?string $attachmentBase64 = null,
        public ?string $attachmentName   = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail::emails.composed',
            with: ['bodyHtml' => $this->bodyHtml],
        );
    }

    public function attachments(): array
    {
        if (! filled($this->attachmentBase64)) {
            return [];
        }

        $name = filled($this->attachmentName) ? $this->attachmentName : 'design.pdf';
        $data = base64_decode($this->attachmentBase64, strict: true);

        if ($data === false) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $data, $name)->withMime('application/pdf'),
        ];
    }
}
