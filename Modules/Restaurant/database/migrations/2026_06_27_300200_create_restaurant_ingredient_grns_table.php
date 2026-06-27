<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_ingredient_grns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')
                ->nullable()
                ->constrained('restaurant_ingredient_purchase_orders')
                ->nullOnDelete();
            $table->string('grn_number', 50)->nullable();
            $table->date('received_date');
            $table->string('payment_method', 30)->default('credit');
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_ingredient_grns');
    }
};
