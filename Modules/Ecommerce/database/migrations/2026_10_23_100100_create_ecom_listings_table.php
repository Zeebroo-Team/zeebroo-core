<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A catalog product published to the online store, with web-only overrides.
        Schema::create('ecom_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->decimal('online_price', 14, 2)->nullable();       // null → product unit_price
            $table->decimal('compare_at_price', 14, 2)->nullable();   // struck-through "was" price
            $table->string('title', 200)->nullable();                 // null → product name
            $table->text('description')->nullable();                  // null → product description
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'product_id']);
            $table->index(['business_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecom_listings');
    }
};
