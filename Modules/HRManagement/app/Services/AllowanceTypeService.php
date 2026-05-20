<?php

declare(strict_types=1);

namespace Modules\HRManagement\Services;

use Modules\Business\Models\Business;
use Modules\HRManagement\Models\AllowanceType;

final class AllowanceTypeService
{
    public function create(Business $business, string $name): AllowanceType
    {
        $next = (int) ($business->allowanceTypes()->max('sort_order') ?? 0) + 1;

        return $business->allowanceTypes()->create([
            'name' => $name,
            'sort_order' => $next,
        ]);
    }
}
