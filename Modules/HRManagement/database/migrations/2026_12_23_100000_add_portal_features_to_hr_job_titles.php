<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_job_titles', function (Blueprint $table) {
            // null = all features enabled (backward compatible); array = explicit allowed list
            $table->json('portal_features')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('hr_job_titles', function (Blueprint $table) {
            $table->dropColumn('portal_features');
        });
    }
};
