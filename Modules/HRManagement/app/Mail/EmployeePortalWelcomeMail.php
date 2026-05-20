<?php

namespace Modules\HRManagement\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Business\Models\Business;
use Modules\HRManagement\Models\Employee;

class EmployeePortalWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{scenario: string, temporary_password: ?string}  $payload
     */
    public function __construct(
        public Employee $employee,
        public Business $business,
        public string $portalLoginUrl,
        public array $payload,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->payload['scenario']) {
            'email_conflict' => __('HR portal — account notice'),
            default => __(':org — your HR portal access', ['org' => $this->business->name]),
        };

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'hrmanagement::emails.employee-portal-welcome',
            with: [
                'employee' => $this->employee,
                'business' => $this->business,
                'portalLoginUrl' => $this->portalLoginUrl,
                'payload' => $this->payload,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
