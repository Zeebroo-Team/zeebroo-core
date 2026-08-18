<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasUnique = Schema::hasIndex('hr_employees', 'hr_employees_user_id_unique');
        $hasIndex = Schema::hasIndex('hr_employees', 'hr_employees_user_id_index');

        Schema::table('hr_employees', function (Blueprint $table) use ($hasUnique, $hasIndex): void {
            if ($hasUnique) {
                $table->dropUnique(['user_id']);
            }
            if (! $hasIndex && Schema::hasColumn('hr_employees', 'user_id')) {
                $table->index('user_id');
            }
        });
    }

    public function down(): void
    {
        $db = Schema::getConnection()->getDatabaseName();
        $hasIndex = \DB::selectOne(
            "SELECT 1 FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = 'hr_employees'
               AND index_name = 'hr_employees_user_id_index'",
            [$db]
        );

        if ($hasIndex) {
            Schema::table('hr_employees', function (Blueprint $table): void {
                $table->dropIndex(['user_id']);
            });
        }

        if (Schema::hasColumn('hr_employees', 'user_id')) {
            Schema::table('hr_employees', function (Blueprint $table): void {
                $table->unique('user_id');
            });
        }
    }
};
