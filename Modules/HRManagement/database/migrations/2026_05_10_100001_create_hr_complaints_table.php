<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_complaints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('subject', 255);
            $table->text('body');
            $table->string('status', 24)->default('open');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_complaints');
    }
};
