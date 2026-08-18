<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bm_salary_sheet_rows', function (Blueprint $table) {
            $table->string('bank_name',    150)->nullable()->after('promoter_name');
            $table->string('bank_branch',  150)->nullable()->after('bank_name');
            $table->string('bank_account', 100)->nullable()->after('bank_branch');
        });
    }

    public function down(): void
    {
        Schema::table('bm_salary_sheet_rows', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_branch', 'bank_account']);
        });
    }
};
