<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->foreignId('property_id')->nullable()->after('rental_id')->constrained('properties')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->after('property_id')->constrained('hr_employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
