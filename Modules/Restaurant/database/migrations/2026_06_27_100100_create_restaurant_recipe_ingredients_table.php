<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('restaurant_menu_items')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('restaurant_ingredients')->cascadeOnDelete();
            $table->decimal('quantity_required', 12, 3);
            $table->timestamps();

            $table->unique(['menu_item_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_recipe_ingredients');
    }
};
