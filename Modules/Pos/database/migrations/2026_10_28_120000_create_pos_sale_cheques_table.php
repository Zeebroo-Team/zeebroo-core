<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A customer cheque taken at the POS. The sale is paid by cheque; the money only
        // reaches a deposit account when the cheque is claimed (deposited and cleared).
        Schema::create('pos_sale_cheques', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('pos_sale_id')->unique()->constrained('pos_sales')->cascadeOnDelete();
            $table->foreignId('pos_customer_id')->nullable()->constrained('pos_customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cheque_number', 60);
            $table->string('bank_name', 120)->nullable();
            $table->date('cheque_date');
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('pending');

            // Claim (deposit) details
            $table->foreignId('deposit_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();

            // Bounce details
            $table->timestamp('bounced_at')->nullable();
            $table->string('bounce_reason', 255)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status', 'cheque_date']);
            $table->index(['business_id', 'pos_customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_cheques');
    }
};
