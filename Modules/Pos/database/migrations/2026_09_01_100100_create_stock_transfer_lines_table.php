<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name', 255);
            $table->string('sku', 100)->nullable();
            $table->string('unit', 60)->nullable();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->json('consumed_breakdown')->nullable();
            $table->foreignId('destination_layer_id')->nullable()->constrained('product_stock_layers')->nullOnDelete();
            $table->timestamps();

            $table->index('stock_transfer_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_lines');
    }
};
