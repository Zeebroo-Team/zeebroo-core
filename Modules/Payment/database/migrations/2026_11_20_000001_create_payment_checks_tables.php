<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row, admin-editable config for the scheduled Stripe payment check.
        Schema::create('payment_check_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('interval_minutes')->default(20);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->string('last_webhook_event')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One row per check run (scheduled or "Run now"), with per-business results.
        Schema::create('payment_check_runs', function (Blueprint $table) {
            $table->id();
            $table->string('trigger', 20); // scheduled | manual
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('period', 7); // YYYY-MM the run checked
            $table->string('status', 20)->default('running'); // running | completed | failed
            $table->unsignedInteger('checked_count')->default(0);
            $table->unsignedInteger('ok_count')->default(0);
            $table->unsignedInteger('fixed_count')->default(0);
            $table->unsignedInteger('issue_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('results')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['period', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_check_runs');
        Schema::dropIfExists('payment_check_settings');
    }
};
