<?php

namespace Modules\Pos\Services\Backup;

use RuntimeException;

/** The uploaded backup cannot be imported; the message is shown to the user. */
class BackupInvalid extends RuntimeException
{
}
