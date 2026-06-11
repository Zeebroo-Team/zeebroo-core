<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('quote_number', 40)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('pos_customers')->nullOnDelete();
            $table->string('reference', 120)->nullable();
            $table->date('quote_date');
            $table->date('expiry_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['business_id', 'quote_date']);
            $table->index(['business_id', 'status']);
            $table->unique(['business_id', 'quote_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
