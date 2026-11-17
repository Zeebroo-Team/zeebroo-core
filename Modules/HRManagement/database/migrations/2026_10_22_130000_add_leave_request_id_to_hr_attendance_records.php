<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_attendance_records', function (Blueprint $table): void {
            // Set when the row was written for an approved leave day; deleting the leave removes the row.
            $table->foreignId('leave_request_id')->nullable()->after('employee_id')
                ->constrained('hr_leave_requests')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendance_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('leave_request_id');
        });
    }
};
