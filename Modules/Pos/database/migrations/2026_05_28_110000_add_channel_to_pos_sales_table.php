<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->string('channel', 20)->default('retail')->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
