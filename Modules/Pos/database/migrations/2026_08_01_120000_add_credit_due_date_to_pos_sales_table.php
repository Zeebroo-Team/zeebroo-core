<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->date('credit_due_date')->nullable()->after('pos_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropColumn('credit_due_date');
        });
    }
};
