<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->dropForeign(['department_id']);
        });

        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->change();
            $table->foreign('department_id')->references('id')->on('hr_departments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->dropForeign(['department_id']);
        });

        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable(false)->change();
            $table->foreign('department_id')->references('id')->on('hr_departments')->restrictOnDelete();
        });
    }
};
