<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('business_id')->constrained('users')->nullOnDelete();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
