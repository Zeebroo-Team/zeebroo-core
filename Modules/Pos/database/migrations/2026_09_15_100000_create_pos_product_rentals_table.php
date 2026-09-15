<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_product_rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_customer_id')->nullable()->constrained('pos_customers')->nullOnDelete();
            $table->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $table->foreignId('pos_sale_item_id')->nullable()->constrained('pos_sale_items')->nullOnDelete();
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->decimal('quantity', 10, 3)->default(1);
            $table->date('rented_at');
            $table->date('due_at');
            $table->date('returned_at')->nullable();
            $table->decimal('late_fee', 10, 2)->default(0);
            $table->decimal('late_fee_multiplier', 10, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_product_rentals');
    }
};
