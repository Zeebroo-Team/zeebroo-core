<?php

namespace Modules\HRManagement\Services;

use Modules\Business\Models\Business;
use Modules\HRManagement\Models\JobTitle;

class JobTitleService
{
    public function create(Business $business, string $name): JobTitle
    {
        return $business->jobTitles()->create([
            'name' => trim($name),
        ]);
    }
}
