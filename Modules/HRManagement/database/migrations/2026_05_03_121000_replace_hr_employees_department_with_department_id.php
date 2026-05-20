<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->dropColumn('department');
        });

        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->foreignId('department_id')->after('job_title')->constrained('hr_departments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->string('department')->after('job_title');
        });
    }
};
