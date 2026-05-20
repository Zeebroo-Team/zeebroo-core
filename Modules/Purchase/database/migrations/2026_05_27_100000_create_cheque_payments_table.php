<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheque_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('goods_receive_note_id')->nullable()->constrained('goods_receive_notes')->nullOnDelete();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->foreignId('deduct_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('cheque_number', 120);
            $table->date('due_date');
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('pending');
            $table->timestamp('cleared_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'due_date']);
            $table->unique('ledger_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheque_payments');
    }
};
