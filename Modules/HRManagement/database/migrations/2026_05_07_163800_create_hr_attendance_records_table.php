<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->string('status', 24);
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->unsignedInteger('worked_minutes')->nullable();
            $table->string('source', 24)->default('manual');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['business_id', 'employee_id', 'work_date'], 'hr_attendance_unique_per_day');
            $table->index(['business_id', 'work_date']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_records');
    }
};
