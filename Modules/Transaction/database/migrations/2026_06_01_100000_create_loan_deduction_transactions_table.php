<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_deduction_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deduct_account_id')->constrained('accounts')->restrictOnDelete();
            $table->date('deduction_date');
            $table->unsignedSmallInteger('period_number')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 8)->nullable();
            $table->string('cadence_snapshot', 32)->nullable();
            $table->unsignedSmallInteger('periods_total_snapshot')->nullable();
            $table->decimal('borrowed_principal_snapshot', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'deduction_date']);
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_deduction_transactions');
    }
};
