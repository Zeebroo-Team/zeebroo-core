<?php

namespace Modules\Settings\Observers;

use Modules\Business\Models\Business;
use Modules\Settings\Jobs\SeedBusinessSettingsJob;

class BusinessObserver
{
    public function created(Business $business): void
    {
        SeedBusinessSettingsJob::dispatch((int) $business->id);
    }
}
