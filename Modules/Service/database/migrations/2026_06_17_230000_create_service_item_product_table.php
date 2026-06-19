<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_item_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_item_id')->constrained('service_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('qty', 12, 3)->default(1);
            $table->timestamps();

            $table->unique(['service_item_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_item_product');
    }
};
