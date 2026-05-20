<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_bundle_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bundle_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('item_product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 14, 3)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['bundle_product_id', 'item_product_id']);
            $table->index(['bundle_product_id', 'sort_order']);
            $table->index('item_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bundle_items');
    }
};
