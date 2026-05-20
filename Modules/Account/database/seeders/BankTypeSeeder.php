<?php

namespace Modules\Account\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Account\Models\BankType;

class BankTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Current Account',
                'slug' => 'current-account',
                'description' => 'Best for daily transactions, payments, and business cash flow.',
            ],
            [
                'name' => 'Saving Account',
                'slug' => 'saving-account',
                'description' => 'Ideal for holding funds securely and earning savings benefits.',
            ],
        ];

        foreach ($types as $type) {
            BankType::updateOrCreate(['slug' => $type['slug']], $type);
        }
    }
}
