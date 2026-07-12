<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_lead_stage_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('crm_lead_stages')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('recipient_type', 20)->default('lead');
            $table->string('recipient_email', 190)->nullable();
            $table->string('subject', 200);
            $table->text('body');
            $table->timestamps();

            $table->index(['project_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_stage_automations');
    }
};
