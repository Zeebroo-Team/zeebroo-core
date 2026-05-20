<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('agreement_valid_until_year');
            $table->foreignId('deduct_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('recurring_cost', 15, 2);
            $table->string('recurring_type', 32);
            $table->unsignedSmallInteger('remind_before_days')->nullable();
            $table->date('due_date')->nullable();
            $table->date('first_installment_due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
