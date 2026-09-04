<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_customer_id')->nullable()->constrained('pos_customers')->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $table->foreignId('pos_sale_item_id')->nullable()->constrained('pos_sale_items')->nullOnDelete();
            $table->string('recurring_period', 30);
            $table->boolean('free_trial')->default(false);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('quantity', 10, 3)->default(1);
            $table->string('status', 20)->default('active');
            $table->date('started_at');
            $table->date('next_billing_at')->nullable();
            $table->date('last_renewed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_subscriptions');
    }
};
