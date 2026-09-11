<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->decimal('monthly_amount', 14, 2)->default(0);
            $table->decimal('yearly_amount', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['budget_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
    }
};
