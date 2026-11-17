<?php

namespace Modules\CRM\Services\QuotationDispatch;

use Modules\CRM\Mail\QuotationMail;
use Modules\Mail\Services\BusinessMailerService;
use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationDispatch;
use RuntimeException;

/** Emails the PDF through the business's own mail settings (BusinessMailerService). */
final class EmailQuotationChannel implements QuotationChannel
{
    public function __construct(private readonly BusinessMailerService $mailer) {}

    public function key(): string { return 'email'; }

    public function label(): string { return 'Email'; }

    public function recipientRules(): array
    {
        return ['required', 'email', 'max:191'];
    }

    public function send(QuotationDispatch $dispatch, Quotation $quotation): void
    {
        $result = $this->mailer->send(
            $quotation->business,
            new QuotationMail((string) $dispatch->subject, (string) $dispatch->message, (string) $dispatch->file_path, $quotation->quote_number . '.pdf'),
            $dispatch->to,
            $dispatch->cc ?? [],
        );

        if (! $result['success']) {
            throw new RuntimeException($result['error'] ?: 'The email could not be sent.');
        }
    }
}
