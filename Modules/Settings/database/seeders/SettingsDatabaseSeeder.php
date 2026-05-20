<?php

namespace Modules\Settings\Database\Seeders;

use Illuminate\Database\Seeder;

class SettingsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsFieldSeeder::class,
        ]);
    }
}
