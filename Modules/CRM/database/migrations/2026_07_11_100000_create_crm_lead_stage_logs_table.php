<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_lead_stage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('crm_lead_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->nullable()->constrained('crm_lead_stages')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_stage_logs');
    }
};
