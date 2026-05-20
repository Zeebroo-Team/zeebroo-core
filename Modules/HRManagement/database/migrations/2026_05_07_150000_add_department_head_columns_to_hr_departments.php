<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_departments', function (Blueprint $table): void {
            $table->foreignId('head_employee_id')->nullable()->after('name')->constrained('hr_employees')->nullOnDelete();
            $table->foreignId('co_head_employee_id')->nullable()->after('head_employee_id')->constrained('hr_employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_departments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('co_head_employee_id');
            $table->dropConstrainedForeignId('head_employee_id');
        });
    }
};
