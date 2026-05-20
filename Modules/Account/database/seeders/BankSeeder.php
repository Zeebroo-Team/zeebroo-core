<?php

namespace Modules\Account\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Account\Models\Bank;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            ['name' => 'State Bank', 'code' => 'STATE_BANK'],
            ['name' => 'City National Bank', 'code' => 'CITY_NATIONAL'],
            ['name' => 'Union Bank', 'code' => 'UNION_BANK'],
            ['name' => 'People Trust Bank', 'code' => 'PEOPLE_TRUST'],
            ['name' => 'Global Commerce Bank', 'code' => 'GLOBAL_COMMERCE'],
        ];

        foreach ($banks as $bank) {
            Bank::updateOrCreate(['code' => $bank['code']], $bank);
        }
    }
}
