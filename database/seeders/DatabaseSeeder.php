<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Account\Database\Seeders\AccountDatabaseSeeder;
use Modules\Business\Database\Seeders\BusinessCategorySeeder;
use Modules\Settings\Database\Seeders\SettingsDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AuthLoginSeeder::class,
            BusinessCategorySeeder::class,
            AccountDatabaseSeeder::class,
            SettingsDatabaseSeeder::class,
        ]);
    }
}
