<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->decimal('discount_percent', 6, 2)->nullable()->after('subtotal');
            $table->decimal('discount_amount', 14, 2)->default(0)->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_amount']);
        });
    }
};
