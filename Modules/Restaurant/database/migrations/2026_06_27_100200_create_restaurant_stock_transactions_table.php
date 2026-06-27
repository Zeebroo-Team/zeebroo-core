<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained('restaurant_ingredients')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->enum('type', ['purchase', 'deduction', 'adjustment', 'waste']);
            $table->decimal('quantity_change', 12, 3); // positive = add, negative = deduct
            $table->decimal('quantity_after', 12, 3);
            $table->string('notes')->nullable();
            $table->nullableMorphs('reference'); // order, manual, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_stock_transactions');
    }
};
