<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_campaign_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_campaign_id')->constrained('sale_campaigns')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // null = applies to the product base unit_price; set = applies to that selling unit
            $table->foreignId('product_selling_unit_id')
                  ->nullable()
                  ->constrained('product_selling_units')
                  ->nullOnDelete();
            $table->string('discount_type', 12)->default('percentage'); // flat | percentage
            $table->decimal('discount_value', 10, 2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['sale_campaign_id']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_campaign_items');
    }
};
