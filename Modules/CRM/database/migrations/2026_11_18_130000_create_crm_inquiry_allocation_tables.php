<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daily inquiry allocation: the per-user daily limit and the management report settings
        Schema::table('crm_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('daily_inquiry_limit')->default(10)->after('quotation_stock_check');
            $table->boolean('inquiry_report_enabled')->default(false)->after('daily_inquiry_limit');
            // HH:MM in the business's timezone
            $table->string('inquiry_report_time', 5)->default('18:00')->after('inquiry_report_enabled');
            // true = the head reviews the day's report and sends it; false = sent automatically
            $table->boolean('inquiry_report_review')->default(false)->after('inquiry_report_time');
            $table->json('inquiry_report_recipients')->nullable()->after('inquiry_report_review');
            // The business day the scheduled report last ran for
            $table->date('inquiry_report_last_run')->nullable()->after('inquiry_report_recipients');
        });

        // One row per lead handed to a user on a day (the business's calendar day). A same-day
        // un-assignment sets released_at, which gives the slot back.
        Schema::create('crm_inquiry_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->date('allocation_date');
            $table->unsignedBigInteger('assigned_by')->nullable();
            // Allocated beyond the daily limit under an exception
            $table->boolean('over_limit')->default(false);
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique(['lead_id', 'user_id', 'allocation_date'], 'crm_inq_alloc_lead_user_day_unique');
            $table->index(['business_id', 'allocation_date', 'user_id'], 'crm_inq_alloc_day_user_index');
            $table->index(['business_id', 'department_id', 'allocation_date'], 'crm_inq_alloc_dept_day_index');
        });

        // Extra inquiries an administrator allows one user on one day, on top of the limit
        Schema::create('crm_inquiry_limit_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->date('exception_date');
            $table->unsignedSmallInteger('extra_allowed');
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('granted_by')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'user_id', 'exception_date'], 'crm_inq_exc_user_day_unique');
        });

        // The daily inquiry status report, one per department per day
        Schema::create('crm_inquiry_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->date('report_date');
            // draft (waiting for the head) | sent | failed
            $table->string('status', 12)->default('draft');
            $table->json('data');
            $table->json('recipients')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'department_id', 'report_date'], 'crm_inq_report_dept_day_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_inquiry_reports');
        Schema::dropIfExists('crm_inquiry_limit_exceptions');
        Schema::dropIfExists('crm_inquiry_allocations');
        Schema::table('crm_settings', function (Blueprint $table) {
            $table->dropColumn(['daily_inquiry_limit', 'inquiry_report_enabled', 'inquiry_report_time', 'inquiry_report_review', 'inquiry_report_recipients', 'inquiry_report_last_run']);
        });
    }
};
