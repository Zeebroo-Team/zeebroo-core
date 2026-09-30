<?php

namespace Modules\Payment\Exceptions;

use RuntimeException;

/**
 * Carries an HTTP status alongside the message so both the JSON (POS API)
 * and redirect (web billing page) controllers can report the same failure
 * in their own response shape without duplicating the validation itself.
 */
class SubscriptionActionException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
