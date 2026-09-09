<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_external_billing_marks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->cascadeOnDelete();
            $table->date('due_date');
            $table->timestamps();

            $table->unique(['rental_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_external_billing_marks');
    }
};
