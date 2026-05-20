<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->after('branch_id')->constrained('hr_departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
