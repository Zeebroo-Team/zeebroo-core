<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where a lead was sent and who works it: forwarded (Marketing → department) now,
        // assigned / reassigned / unassigned once leads get several assignees.
        Schema::create('crm_lead_assignment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->string('action', 20);
            $table->foreignId('from_department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('from_division_id')->nullable()->constrained('crm_divisions')->nullOnDelete();
            $table->foreignId('to_division_id')->nullable()->constrained('crm_divisions')->nullOnDelete();
            $table->json('user_ids')->nullable();
            $table->foreignId('by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['lead_id', 'created_at']);
            $table->index(['business_id', 'action', 'by_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_assignment_logs');
    }
};
