<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bm_salary_sheet_rows', function (Blueprint $table) {
            $table->unsignedSmallInteger('total_days')->default(0)->after('bank_account');
            $table->decimal('attendance_amount', 12, 2)->default(0)->after('total_days');
            $table->decimal('base_amount',       12, 2)->default(0)->after('attendance_amount');
            $table->decimal('net_amount',        12, 2)->default(0)->after('base_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bm_salary_sheet_rows', function (Blueprint $table) {
            $table->dropColumn(['total_days', 'attendance_amount', 'base_amount', 'net_amount']);
        });
    }
};
