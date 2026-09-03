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

    public function rename(JobTitle $jobTitle, string $name): JobTitle
    {
        $jobTitle->update(['name' => trim($name)]);

        return $jobTitle;
    }

    public function delete(JobTitle $jobTitle): void
    {
        $jobTitle->delete();
    }
}
