<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_order_items', function (Blueprint $table): void {
            $table->foreignId('product_id')
                ->nullable()
                ->after('menu_item_id')
                ->constrained('products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_order_items', function (Blueprint $table): void {
            $table->dropForeignIdFor(\Modules\Product\Models\Product::class);
            $table->dropColumn('product_id');
        });
    }
};
