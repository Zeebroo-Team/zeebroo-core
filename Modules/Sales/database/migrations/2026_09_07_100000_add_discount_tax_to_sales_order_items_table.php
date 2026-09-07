<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->string('discount_type', 10)->default('pct')->after('unit_price');
            $table->decimal('discount_value', 10, 2)->default(0)->after('discount_type');
            $table->decimal('tax_pct', 5, 2)->default(0)->after('discount_value');
            // tax_type distinguishes percentage from flat-amount tax rules
            $table->string('tax_type', 10)->default('pct')->after('tax_pct');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'tax_pct', 'tax_type']);
        });
    }
};
