<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('crm_lead_stage_automations', 'crm_lead_stage_mail_templates');

        Schema::table('crm_lead_stage_mail_templates', function (Blueprint $table) {
            $table->unique(['project_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_lead_stage_mail_templates', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'stage_id']);
        });

        Schema::rename('crm_lead_stage_mail_templates', 'crm_lead_stage_automations');
    }
};
