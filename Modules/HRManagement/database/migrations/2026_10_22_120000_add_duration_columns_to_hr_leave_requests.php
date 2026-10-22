<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_leave_requests', function (Blueprint $table): void {
            // Working days charged against the balance; null on legacy rows (calendar days are used for those).
            $table->decimal('days', 5, 1)->nullable()->after('ends_on');
            $table->boolean('is_half_day')->default(false)->after('days');
            $table->string('half_day_period', 8)->nullable()->after('is_half_day');
            $table->time('short_leave_start')->nullable()->after('half_day_period');
            $table->unsignedSmallInteger('short_leave_minutes')->nullable()->after('short_leave_start');
            $table->foreignId('decided_by_user_id')->nullable()->after('recorded_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable()->after('decided_by_user_id');

            $table->index(['employee_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::table('hr_leave_requests', function (Blueprint $table): void {
            $table->dropIndex(['employee_id', 'starts_on']);
            $table->dropConstrainedForeignId('decided_by_user_id');
            $table->dropColumn(['days', 'is_half_day', 'half_day_period', 'short_leave_start', 'short_leave_minutes', 'decided_at']);
        });
    }
};
