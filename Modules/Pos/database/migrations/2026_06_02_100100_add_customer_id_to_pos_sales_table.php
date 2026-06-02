<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->foreignId('pos_customer_id')->nullable()->after('credit_account_id')
                ->constrained('pos_customers')->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropForeign(['pos_customer_id']);
            $table->dropColumn('pos_customer_id');
        });
    }
};
