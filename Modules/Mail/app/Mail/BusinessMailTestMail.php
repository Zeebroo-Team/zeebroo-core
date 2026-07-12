<?php

namespace Modules\Mail\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Business\Models\Business;

class BusinessMailTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Business $business) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test email from ' . $this->business->name);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail::emails.test',
            with: ['business' => $this->business],
        );
    }
}
