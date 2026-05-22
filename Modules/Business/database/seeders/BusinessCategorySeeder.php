<?php

declare(strict_types=1);

namespace Modules\Business\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Business\Models\BusinessCategory;
use Modules\Business\Support\BrandCompanyCategoryCatalog;

class BusinessCategorySeeder extends Seeder
{
    public function run(): void
    {
        $sort = 0;
        foreach (BrandCompanyCategoryCatalog::defaultOptions() as $row) {
            BusinessCategory::query()->updateOrCreate(
                ['slug' => $row['value']],
                [
                    'name' => $row['label'],
                    'sort_order' => $sort,
                    'is_active' => true,
                ]
            );
            $sort += 10;
        }
    }
}
