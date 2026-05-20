<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->string('profile_photo_path', 512)->nullable()->after('full_name');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->dropColumn('profile_photo_path');
        });
    }
};
