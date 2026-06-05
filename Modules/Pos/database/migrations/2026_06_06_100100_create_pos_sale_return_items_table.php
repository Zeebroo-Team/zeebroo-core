<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sale_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_sale_return_id')->constrained('pos_sale_returns')->cascadeOnDelete();
            $table->foreignId('pos_sale_item_id')->constrained('pos_sale_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_stock_layer_id')->nullable()->constrained('product_stock_layers')->nullOnDelete();
            $table->string('product_name', 255);
            $table->string('sku', 120)->nullable();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_sell_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->index(['pos_sale_return_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_return_items');
    }
};
