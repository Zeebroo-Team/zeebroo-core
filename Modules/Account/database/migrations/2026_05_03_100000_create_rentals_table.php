<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('property_type');
            $table->string('purpose', 2000)->nullable();
            $table->decimal('key_money', 15, 2)->nullable();
            $table->unsignedSmallInteger('agreement_valid_until_year');
            $table->foreignId('deduct_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('recurring_cost', 15, 2);
            $table->string('recurring_type', 32);
            $table->timestamps();

            $table->index(['business_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
