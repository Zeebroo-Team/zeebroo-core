<?php

namespace Modules\Pos\Services\Backup;

use RuntimeException;

/** Unknown import id, or one that belongs to another business. */
class BackupNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Import not found.');
    }
}
