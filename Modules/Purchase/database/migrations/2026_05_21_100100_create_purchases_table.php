<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable();
            $table->date('purchase_date');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->boolean('stock_applied')->default(false);
            $table->timestamps();

            $table->index(['business_id', 'purchase_date']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
