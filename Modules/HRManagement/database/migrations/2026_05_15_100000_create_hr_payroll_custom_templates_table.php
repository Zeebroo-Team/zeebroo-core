<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_payroll_custom_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->json('highlights')->nullable();
            $table->string('rule_set_name', 140);
            $table->string('currency', 16)->nullable();
            $table->json('rules');
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_custom_templates');
    }
};
