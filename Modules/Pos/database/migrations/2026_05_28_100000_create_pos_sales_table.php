<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sale_number', 40);
            $table->string('status', 20)->default('completed');
            $table->string('payment_method', 20)->default('cash');
            $table->foreignId('credit_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('sold_at');
            $table->timestamps();

            $table->unique(['business_id', 'sale_number']);
            $table->index(['business_id', 'sold_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sales');
    }
};
