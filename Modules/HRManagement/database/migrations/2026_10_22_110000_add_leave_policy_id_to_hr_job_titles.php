<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_job_titles', function (Blueprint $table): void {
            // null = the business default leave rule applies to this position
            $table->foreignId('leave_policy_id')->nullable()->after('name')
                ->constrained('hr_leave_policies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_job_titles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('leave_policy_id');
        });
    }
};
