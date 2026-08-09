<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bm_salary_sheets', function (Blueprint $table) {
            $table->decimal('default_coordinator_fee', 12, 2)->nullable()->default(0)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('bm_salary_sheets', function (Blueprint $table) {
            $table->dropColumn('default_coordinator_fee');
        });
    }
};
