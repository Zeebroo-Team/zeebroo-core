<?php

declare(strict_types=1);

namespace Modules\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeVerifyOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $otp) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your verification code'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'auth::emails.employee-verify-otp',
            with: ['otp' => $this->otp],
        );
    }
}
