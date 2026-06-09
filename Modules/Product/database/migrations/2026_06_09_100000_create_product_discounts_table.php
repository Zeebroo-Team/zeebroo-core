<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_discounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // null = applies to the product base unit_price; set = applies to that selling unit
            $table->foreignId('product_selling_unit_id')
                  ->nullable()
                  ->constrained('product_selling_units')
                  ->nullOnDelete();
            $table->string('name', 255);
            $table->string('discount_type', 12)->default('percentage'); // flat | percentage
            $table->decimal('discount_value', 10, 2);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'product_id']);
            $table->index(['business_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_discounts');
    }
};
