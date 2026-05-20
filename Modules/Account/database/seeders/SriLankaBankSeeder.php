<?php

namespace Modules\Account\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Account\Models\Bank;

class SriLankaBankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            ['name' => 'Bank of Ceylon', 'code' => 'BOC'],
            ['name' => 'People\'s Bank', 'code' => 'PEOPLES_BANK_LK'],
            ['name' => 'Commercial Bank of Ceylon', 'code' => 'COMBANK'],
            ['name' => 'Hatton National Bank', 'code' => 'HNB'],
            ['name' => 'Sampath Bank', 'code' => 'SAMPATH'],
            ['name' => 'National Development Bank', 'code' => 'NDB'],
            ['name' => 'Seylan Bank', 'code' => 'SEYLAN'],
            ['name' => 'DFCC Bank', 'code' => 'DFCC'],
            ['name' => 'Nations Trust Bank', 'code' => 'NTB'],
            ['name' => 'Pan Asia Banking Corporation', 'code' => 'PAN_ASIA'],
            ['name' => 'Union Bank of Colombo', 'code' => 'UNION_BANK_LK'],
            ['name' => 'National Savings Bank', 'code' => 'NSB'],
            ['name' => 'Regional Development Bank', 'code' => 'RDB'],
            ['name' => 'Sanasa Development Bank', 'code' => 'SANASA'],
            ['name' => 'Amana Bank', 'code' => 'AMANA'],
        ];

        foreach ($banks as $bank) {
            Bank::updateOrCreate(['code' => $bank['code']], $bank);
        }
    }
}
