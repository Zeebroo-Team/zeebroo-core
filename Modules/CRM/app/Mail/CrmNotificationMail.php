<?php

namespace Modules\CRM\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The email copy of a CRM in-app notification (CrmNotifier), for users who keep CRM emails on. */
class CrmNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $messageText,
        public string $businessName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title . ($this->businessName !== '' ? ' — ' . $this->businessName : ''));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail::emails.composed',
            with: ['bodyHtml' => '<p><strong>' . e($this->title) . '</strong></p><p>' . nl2br(e($this->messageText)) . '</p>'
                . '<p style="color:#64748b;font-size:12px">Open the CRM in Zeebroo to act on it. You can turn these emails off in CRM → My Follow-ups.</p>'],
        );
    }
}
