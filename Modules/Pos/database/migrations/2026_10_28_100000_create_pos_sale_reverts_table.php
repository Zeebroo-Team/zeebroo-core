<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per bill reverted because it was rung up by mistake. The sale itself
        // is marked void; this row keeps the reason and a snapshot of what was undone.
        Schema::create('pos_sale_reverts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('pos_sale_id')->unique()->constrained('pos_sales')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revert_number', 40);
            $table->string('reason', 40);
            $table->text('notes')->nullable();

            // Snapshot of the bill at the moment it was reverted
            $table->string('sale_number', 40)->nullable();
            $table->string('customer_name')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->timestamp('sale_sold_at')->nullable();
            $table->decimal('sale_total', 14, 2)->default(0);
            $table->decimal('sale_cost', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('gift_card_refunded', 14, 2)->default(0);
            $table->decimal('returned_total', 14, 2)->default(0);
            $table->decimal('amount_refunded', 14, 2)->default(0);
            $table->decimal('account_reversed', 14, 2)->default(0);
            $table->decimal('stock_restored_qty', 14, 3)->default(0);
            $table->json('items')->nullable();

            $table->timestamp('reverted_at');
            $table->timestamps();

            $table->unique(['business_id', 'revert_number']);
            $table->index(['business_id', 'reverted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_reverts');
    }
};
