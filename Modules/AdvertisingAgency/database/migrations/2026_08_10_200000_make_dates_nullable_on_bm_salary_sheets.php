<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bm_salary_sheets', function (Blueprint $table) {
            $table->date('date_from')->nullable()->change();
            $table->date('date_to')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bm_salary_sheets', function (Blueprint $table) {
            $table->date('date_from')->nullable(false)->change();
            $table->date('date_to')->nullable(false)->change();
        });
    }
};
