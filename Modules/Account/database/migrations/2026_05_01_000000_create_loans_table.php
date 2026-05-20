<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_id')->constrained('banks')->restrictOnDelete();
            $table->string('name');
            $table->decimal('borrowed_amount', 15, 2);
            $table->string('interest_rate_type', 20);
            $table->decimal('interest_rate', 12, 4);
            $table->timestamps();

            $table->index(['business_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
