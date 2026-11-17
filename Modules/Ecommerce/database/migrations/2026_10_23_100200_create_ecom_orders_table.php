<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecom_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('order_no', 30);
            $table->string('customer_name', 150);
            $table->string('customer_email', 150)->nullable();
            $table->string('customer_phone', 40);
            $table->string('shipping_address', 500);
            $table->string('shipping_city', 120)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');          // pending|confirmed|processing|shipped|delivered|cancelled
            $table->string('payment_method', 20)->default('cod');      // cod|bank_transfer
            $table->string('payment_status', 20)->default('unpaid');   // unpaid|paid|refunded
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('shipping_fee', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('tracking_no', 80)->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'order_no']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'placed_at']);
        });

        Schema::create('ecom_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('ecom_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('name', 200);
            $table->string('sku', 100)->nullable();
            $table->decimal('unit_price', 14, 2);
            $table->decimal('quantity', 14, 3);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecom_order_items');
        Schema::dropIfExists('ecom_orders');
    }
};
