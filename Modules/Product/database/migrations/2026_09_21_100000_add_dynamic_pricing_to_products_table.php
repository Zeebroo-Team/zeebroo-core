<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_dynamic_pricing')->default(false)->after('item_wise_discount');
            $table->boolean('dynamic_price_qty_linked')->default(false)->after('is_dynamic_pricing');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_dynamic_pricing', 'dynamic_price_qty_linked']);
        });
    }
};
