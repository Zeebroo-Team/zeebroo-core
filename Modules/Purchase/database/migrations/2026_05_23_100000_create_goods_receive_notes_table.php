<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receive_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->string('grn_number', 40);
            $table->date('received_date');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->boolean('stock_applied')->default(false);
            $table->timestamps();

            $table->unique(['business_id', 'grn_number']);
            $table->index(['business_id', 'received_date']);
            $table->index(['purchase_id', 'received_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receive_notes');
    }
};
