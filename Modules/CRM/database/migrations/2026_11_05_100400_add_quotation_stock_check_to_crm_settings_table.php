<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a lead quotation does when a line asks for more than is in stock: warn | block | off
        Schema::table('crm_settings', function (Blueprint $table) {
            $table->string('quotation_stock_check', 10)->default('warn')->after('phone_country_code');
        });
    }

    public function down(): void
    {
        Schema::table('crm_settings', function (Blueprint $table) {
            $table->dropColumn('quotation_stock_check');
        });
    }
};
