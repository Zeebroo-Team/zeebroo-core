<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->foreignId('modification_id')->nullable()->after('employee_id')->constrained('modifications')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('modification_id');
        });
    }
};
