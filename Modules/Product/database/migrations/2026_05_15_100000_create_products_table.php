<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('unit', 40)->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('stock_quantity', 14, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'is_active']);
            $table->index(['business_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
