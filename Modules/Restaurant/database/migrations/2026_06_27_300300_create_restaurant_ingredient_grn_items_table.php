<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_ingredient_grn_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grn_id')
                ->constrained('restaurant_ingredient_grns')
                ->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')
                ->nullable()
                ->constrained('restaurant_ingredient_purchase_order_items')
                ->nullOnDelete();
            $table->foreignId('ingredient_id')
                ->constrained('restaurant_ingredients')
                ->cascadeOnDelete();
            $table->decimal('quantity_received', 12, 3);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_ingredient_grn_items');
    }
};
