<?php

namespace Modules\HRManagement\Services;

use Modules\Business\Models\Business;
use Modules\HRManagement\Models\JobTitle;

class JobTitleService
{
    public function create(Business $business, string $name, ?int $leavePolicyId = null): JobTitle
    {
        return $business->jobTitles()->create([
            'name'            => trim($name),
            'leave_policy_id' => $leavePolicyId,
        ]);
    }

    public function rename(JobTitle $jobTitle, string $name): JobTitle
    {
        $jobTitle->update(['name' => trim($name)]);

        return $jobTitle;
    }

    public function update(JobTitle $jobTitle, string $name, ?int $leavePolicyId): JobTitle
    {
        $jobTitle->update([
            'name'            => trim($name),
            'leave_policy_id' => $leavePolicyId,
        ]);

        return $jobTitle;
    }

    public function delete(JobTitle $jobTitle): void
    {
        $jobTitle->delete();
    }
}
