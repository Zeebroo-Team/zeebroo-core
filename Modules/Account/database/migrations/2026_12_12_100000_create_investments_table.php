<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('investment_type', 32);
            $table->string('investment_type_other')->nullable();
            $table->string('provider')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('description')->nullable();
            $table->string('payment_mode', 16);
            $table->string('recurring_type', 32)->nullable();
            $table->decimal('contribution_amount', 15, 2)->default(0);
            $table->unsignedSmallInteger('schedule_valid_until_year')->nullable();
            $table->date('start_date');
            $table->date('maturity_date')->nullable();
            $table->decimal('expected_return_rate', 6, 2)->nullable();
            $table->decimal('target_amount', 15, 2)->nullable();
            $table->foreignId('deduct_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->unsignedSmallInteger('remind_before_days')->nullable();
            $table->string('status', 16)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
