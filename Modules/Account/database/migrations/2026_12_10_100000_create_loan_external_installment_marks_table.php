<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_external_installment_marks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->date('due_date');
            $table->timestamps();

            $table->unique(['loan_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_external_installment_marks');
    }
};
