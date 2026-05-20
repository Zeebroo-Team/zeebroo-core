<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_attendance_device_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 100);
            $table->string('employee_code', 120);
            $table->dateTime('punch_time');
            $table->string('punch_type', 20)->default('auto');
            $table->string('external_event_id', 191)->nullable();
            $table->string('event_uid', 64)->unique();
            $table->json('payload')->nullable();
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->foreignId('attendance_record_id')->nullable()->constrained('hr_attendance_records')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'device_id', 'punch_time'], 'hr_attendance_device_logs_device_time_idx');
            $table->index(['business_id', 'processed'], 'hr_attendance_device_logs_processed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_device_logs');
    }
};
