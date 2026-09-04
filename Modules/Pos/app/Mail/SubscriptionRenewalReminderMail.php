<?php

namespace Modules\Pos\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewalReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $businessName,
        public string $customerName,
        public string $productName,
        public string $priceLabel,
        public string $periodLabel,
        public string $nextBillingLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Upcoming renewal for {$this->productName}");
    }

    public function content(): Content
    {
        return new Content(view: 'pos::emails.subscription-reminder');
    }
}
