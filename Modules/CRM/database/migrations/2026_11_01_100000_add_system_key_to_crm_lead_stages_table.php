<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each pipeline stage maps to one of the standard statuses (LeadStage::STATUSES);
        // null = not mapped yet (see crm:map-stage-statuses)
        Schema::table('crm_lead_stages', function (Blueprint $table) {
            $table->string('system_key', 30)->nullable()->after('name');
            $table->boolean('requires_reason')->default(false)->after('is_lost');

            $table->index(['project_id', 'system_key']);
        });

        // Why a lead entered a stage that asks for a reason (lost reason, on-hold note, …)
        Schema::table('crm_lead_stage_logs', function (Blueprint $table) {
            $table->string('reason', 500)->nullable()->after('changed_by');
        });
    }

    public function down(): void
    {
        Schema::table('crm_lead_stage_logs', function (Blueprint $table) {
            $table->dropColumn('reason');
        });

        Schema::table('crm_lead_stages', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'system_key']);
            $table->dropColumn(['system_key', 'requires_reason']);
        });
    }
};
