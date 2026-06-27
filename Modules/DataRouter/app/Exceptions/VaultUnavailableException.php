<?php

declare(strict_types=1);

namespace Modules\DataRouter\Exceptions;

use RuntimeException;

/**
 * Thrown when the Data Vault is enabled for a module but cannot be reached
 * or returns a non-2xx status. Callers must NOT fall back to local DB — the
 * customer chose self-hosting for data privacy reasons.
 */
class VaultUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly string $module,
        string $reason,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            "Data Vault unreachable for module [{$module}]: {$reason}",
            0,
            $previous
        );
    }
}
