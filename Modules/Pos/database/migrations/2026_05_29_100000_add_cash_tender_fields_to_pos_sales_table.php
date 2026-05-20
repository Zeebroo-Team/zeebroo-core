<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->decimal('amount_tendered', 14, 2)->nullable()->after('amount_paid');
            $table->decimal('change_amount', 14, 2)->nullable()->after('amount_tendered');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropColumn(['amount_tendered', 'change_amount']);
        });
    }
};
