<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_departments', function (Blueprint $table): void {
            $table->decimal('salary_range_min', 14, 2)->nullable();
            $table->decimal('salary_range_max', 14, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hr_departments', function (Blueprint $table): void {
            $table->dropColumn(['salary_range_min', 'salary_range_max']);
        });
    }
};
