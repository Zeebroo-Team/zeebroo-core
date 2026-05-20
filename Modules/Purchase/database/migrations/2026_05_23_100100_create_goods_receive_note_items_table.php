<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receive_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receive_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_received', 14, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receive_note_items');
    }
};
