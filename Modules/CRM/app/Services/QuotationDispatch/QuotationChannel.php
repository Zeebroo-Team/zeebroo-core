<?php

namespace Modules\CRM\Services\QuotationDispatch;

use Modules\Sales\Models\Quotation;
use Modules\Sales\Models\QuotationDispatch;

/**
 * One way of getting an approved quotation's PDF to the customer (email, WhatsApp, …).
 * Register more with QuotationDispatcher::extend() from a service provider.
 */
interface QuotationChannel
{
    /** Stored in quotation_dispatches.channel and quotations.sent_via, e.g. "email". */
    public function key(): string;

    public function label(): string;

    /** Validation rules for the recipient ("to") on this channel. */
    public function recipientRules(): array;

    /** Deliver the dispatch (its file_path is the PDF). Throw on failure — the message is stored as the error. */
    public function send(QuotationDispatch $dispatch, Quotation $quotation): void;
}
