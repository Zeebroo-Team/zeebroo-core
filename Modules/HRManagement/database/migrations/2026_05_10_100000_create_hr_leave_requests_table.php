<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('leave_type', 32);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('note')->nullable();
            $table->string('status', 24)->default('pending');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_requests');
    }
};
