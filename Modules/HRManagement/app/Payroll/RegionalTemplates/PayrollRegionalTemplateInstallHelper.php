<?php

declare(strict_types=1);

namespace Modules\HRManagement\Payroll\RegionalTemplates;

use Modules\Business\Models\Business;
use Modules\HRManagement\Models\PayrollRuleSet;

final class PayrollRegionalTemplateInstallHelper
{
    public function makeRuleSetSoleDefault(Business $business, PayrollRuleSet $primary): void
    {
        PayrollRuleSet::query()
            ->where('business_id', $business->id)
            ->whereKeyNot($primary->id)
            ->update(['is_default' => false]);

        $primary->forceFill(['is_default' => true])->save();
    }
}
