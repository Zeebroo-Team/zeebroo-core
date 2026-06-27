<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('restaurant_ingredient_purchase_order_items');

        Schema::create('restaurant_ingredient_purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->foreign('purchase_order_id', 'ripo_items_po_id_fk')
                ->references('id')
                ->on('restaurant_ingredient_purchase_orders')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('ingredient_id');
            $table->foreign('ingredient_id', 'ripo_items_ingredient_fk')
                ->references('id')
                ->on('restaurant_ingredients')
                ->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_ingredient_purchase_order_items');
    }
};
