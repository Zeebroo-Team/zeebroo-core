<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stock_layers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receive_note_item_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->decimal('quantity_received', 14, 3);
            $table->decimal('quantity_remaining', 14, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('selling_unit_price', 14, 2)->nullable();
            $table->date('received_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'received_at']);
            $table->index(['business_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_layers');
    }
};
